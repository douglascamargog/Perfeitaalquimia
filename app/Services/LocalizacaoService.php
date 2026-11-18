<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;

/**
 * Localização exata das lojas da Carteira (V14.2): endereço → coordenada.
 *  - Serviço (V15.5): Geoapify (padrão, plano grátis sem pré-pagamento) ou Google Maps — "mapas.provedor" em ⚙️ Administração › Mapas.
 *  - As chaves ficam em config/segredos.php, nunca no código.
 *  - rodada(): o cron/geocodificar.php localiza aos poucos as lojas sem coordenada ou com endereço mudado.
 *  - Só grava quando o Google acha o endereço na MESMA cidade e com precisão de rua ou melhor;
 *    senão o ponto continua aproximado pela cidade (como antes).
 *  - Ajuste à mão (arrastar no mapa) fica salvo e o automático não mexe mais.
 * Só vai para o Google o endereço comercial da loja (rua, bairro, cidade, UF, CEP) — nada de contato ou dado pessoal.
 */
class LocalizacaoService
{
    /** Precisão do Google → nossa */
    private const PRECISAO = ['ROOFTOP' => 'telhado', 'RANGE_INTERPOLATED' => 'rua', 'GEOMETRIC_CENTER' => 'rua'];

    /** Serviço de mapas em uso: 'geoapify' (padrão) ou 'google' */
    public static function provedor(): string
    {
        return Config::get('mapas.provedor', 'geoapify') === 'google' ? 'google' : 'geoapify';
    }

    public static function nomeProvedor(): string
    {
        return self::provedor() === 'google' ? 'Google Maps' : 'Geoapify';
    }

    /** Chave do serviço em uso ('' = sem chave: o mapa fica com os pontos aproximados pela cidade) */
    public static function chave(): string
    {
        return self::provedor() === 'google' ? self::chaveGoogle() : (string) config('integracoes.geoapify.chave', '');
    }

    public static function chaveGoogle(): string
    {
        return (string) config('integracoes.google.maps_chave', '');
    }

    private static function url(): string
    {
        return (string) config('integracoes.google.geocode_url', 'https://maps.googleapis.com/maps/api/geocode/json');
    }

    /** Assinatura do endereço (mudou → localiza de novo) — a mesma conta no PHP e no SQL */
    private const SQL_HASH = "SHA1(CONCAT_WS('|', COALESCE(c.endereco, ''), COALESCE(c.bairro, ''), COALESCE(c.cep, ''), COALESCE(c.cidade, ''), COALESCE(c.uf, '')))";

    /** Lojas que precisam ser localizadas */
    public static function pendentes(int $limite): array
    {
        return Banco::todos("SELECT c.id, c.endereco, c.bairro, c.cep, c.cidade, c.uf, " . self::SQL_HASH . " AS hash
            FROM clientes c WHERE c.excluido_em IS NULL AND c.tipo <> 'consumidor' AND c.geo_manual = 0
              AND COALESCE(c.endereco, '') <> '' AND COALESCE(c.cidade, '') <> ''
              AND (c.geo_hash IS NULL OR c.geo_hash <> " . self::SQL_HASH . ")
            ORDER BY c.geo_hash IS NULL, c.id LIMIT " . (int) $limite); // endereço corrigido primeiro, depois as nunca localizadas
    }

    /** Situação para a tela: [com local exato, aproximado, total com endereço, pendentes] */
    public static function resumo(): array
    {
        $r = Banco::um("SELECT COUNT(*) AS total, SUM(c.lat IS NOT NULL) AS exatos, SUM(c.geo_manual = 1) AS manuais,
                SUM(c.geo_precisao = 'nao_encontrado') AS nao_achados
              FROM clientes c WHERE c.excluido_em IS NULL AND c.tipo <> 'consumidor' AND COALESCE(c.endereco, '') <> ''");
        $r['pendentes'] = (int) Banco::valor("SELECT COUNT(*) FROM clientes c WHERE c.excluido_em IS NULL AND c.tipo <> 'consumidor' AND c.geo_manual = 0
            AND COALESCE(c.endereco, '') <> '' AND COALESCE(c.cidade, '') <> '' AND (c.geo_hash IS NULL OR c.geo_hash <> " . self::SQL_HASH . ')');
        return array_map('intval', $r);
    }

    /**
     * Localiza até $limite lojas. Devolve ['achadas', 'nao_achadas', 'erro' (texto ou null)].
     * Para na hora se a chave for recusada ou a cota do Google acabar (tenta de novo na próxima rodada).
     */
    public static function rodada(int $limite = 200): array
    {
        $r = ['achadas' => 0, 'nao_achadas' => 0, 'erro' => null];
        if (self::chave() === '') {
            $r['erro'] = 'Falta a chave do ' . self::nomeProvedor() . ' (⚙️ Administração › Mapas).';
            return $r;
        }
        foreach (self::pendentes($limite) as $c) {
            try {
                $achou = self::localizar($c);
            } catch (\RuntimeException $erro) {
                $r['erro'] = $erro->getMessage();
                break;
            }
            if ($achou) {
                Banco::executar('UPDATE clientes SET lat = ?, lng = ?, geo_precisao = ?, geo_hash = ?, geo_em = NOW() WHERE id = ? AND geo_manual = 0',
                    [$achou['lat'], $achou['lng'], $achou['precisao'], $c['hash'], $c['id']]);
                $r['achadas']++;
            } else {
                // Não achou com precisão: fica aproximado pela cidade (lat vazio) e não tenta de novo até o endereço mudar
                Banco::executar("UPDATE clientes SET lat = NULL, lng = NULL, geo_precisao = 'nao_encontrado', geo_hash = ?, geo_em = NOW() WHERE id = ? AND geo_manual = 0",
                    [$c['hash'], $c['id']]);
                $r['nao_achadas']++;
            }
            usleep(self::provedor() === 'google' ? 60_000 : 250_000); // abaixo do limite (Google 50/s; Geoapify grátis 5/s)
        }
        return $r;
    }

    /** Um endereço → ['lat', 'lng', 'precisao'] ou null (não achou na cidade com precisão de rua) */
    public static function localizar(array $c): ?array
    {
        if (self::provedor() === 'geoapify') {
            return self::localizarGeoapify($c);
        }
        $endereco = implode(', ', array_filter([trim((string) $c['endereco']), trim((string) ($c['bairro'] ?? '')),
            trim((string) $c['cidade']) . ' - ' . strtoupper(trim((string) $c['uf'])), preg_replace('/\D/', '', (string) ($c['cep'] ?? '')), 'Brasil']));
        $dados = self::consultar(['address' => $endereco, 'components' => 'country:BR' . ($c['uf'] ? '|administrative_area:' . strtoupper($c['uf']) : '')]);
        foreach ($dados['results'] ?? [] as $res) {
            $tipo = (string) ($res['geometry']['location_type'] ?? '');
            if (!isset(self::PRECISAO[$tipo]) || !self::mesmaCidade($res, (string) $c['cidade'])) {
                continue;
            }
            return ['lat' => round((float) $res['geometry']['location']['lat'], 6), 'lng' => round((float) $res['geometry']['location']['lng'], 6),
                'precisao' => self::PRECISAO[$tipo]];
        }
        return null;
    }

    /**
     * Centro de uma CIDADE (Planejador de visitas): ['lat', 'lng'] ou null. Aceita o ponto aproximado do Google
     * (o centro da cidade), desde que seja a mesma cidade.
     */
    public static function localizarCidade(string $cidade, string $uf): ?array
    {
        if (self::chave() === '') {
            return null;
        }
        if (self::provedor() === 'geoapify') {
            $p = \App\Integrations\Mapas\GeoapifyClient::daConfig()->cidade(trim($cidade), strtoupper($uf));
            return $p ? ['lat' => round($p['lat'], 6), 'lng' => round($p['lng'], 6)] : null;
        }
        $dados = self::consultar(['address' => trim($cidade) . ' - ' . strtoupper($uf) . ', Brasil', 'components' => 'country:BR|administrative_area:' . strtoupper($uf)]);
        foreach ($dados['results'] ?? [] as $res) {
            if (self::mesmaCidade($res, $cidade)) {
                return ['lat' => round((float) $res['geometry']['location']['lat'], 6), 'lng' => round((float) $res['geometry']['location']['lng'], 6)];
            }
        }
        return null;
    }

    /**
     * Geoapify: aceita só prédio/estabelecimento ("telhado") ou rua ("rua"), na MESMA cidade do cadastro.
     * Cidade, CEP ou bairro sozinhos não servem: a loja continua aproximada pela cidade.
     */
    private static function localizarGeoapify(array $c): ?array
    {
        $cep = preg_replace('/\D/', '', (string) ($c['cep'] ?? ''));
        $texto = implode(', ', array_filter([trim((string) $c['endereco']), trim((string) ($c['bairro'] ?? '')),
            trim((string) $c['cidade']) . ' - ' . strtoupper(trim((string) $c['uf'])), strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : '', 'Brasil']));
        $alvo = self::semAcento((string) $c['cidade']);
        foreach (\App\Integrations\Mapas\GeoapifyClient::daConfig()->localizar($texto, (string) $c['uf']) as $r) {
            $precisao = ['building' => 'telhado', 'amenity' => 'telhado', 'street' => 'rua'][$r['tipo']] ?? null;
            $mesmaCidade = in_array($alvo, array_map([self::class, 'semAcento'], $r['cidades']), true);
            if ($precisao && $mesmaCidade && ($r['uf'] === '' || $r['uf'] === strtoupper((string) $c['uf']))) {
                return ['lat' => round($r['lat'], 6), 'lng' => round($r['lng'], 6), 'precisao' => $precisao];
            }
        }
        return null;
    }

    private static function semAcento(string $t): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9 ]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t)));
    }

    /** O resultado do Google é da mesma cidade do cadastro? (sem acento, sem maiúscula) */
    private static function mesmaCidade(array $res, string $cidade): bool
    {
        $limpa = fn(string $t) => strtolower(trim(preg_replace('/[^a-z0-9 ]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t)));
        $alvo = $limpa($cidade);
        foreach ($res['address_components'] ?? [] as $comp) {
            $tipos = (array) ($comp['types'] ?? []);
            if (array_intersect($tipos, ['locality', 'administrative_area_level_2']) && $limpa((string) ($comp['long_name'] ?? '')) === $alvo) {
                return true;
            }
        }
        return false;
    }

    /** Chamada ao Google (erros de chave/cota viram exceção com mensagem em português) */
    private static function consultar(array $parametros): array
    {
        $curl = curl_init(self::url() . '?' . http_build_query($parametros + ['key' => self::chave(), 'region' => 'br', 'language' => 'pt-BR']));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
        $resposta = curl_exec($curl);
        $erroCurl = curl_error($curl);
        curl_close($curl);
        if ($resposta === false) {
            throw new \RuntimeException('Sem conexão com o Google Maps: ' . $erroCurl);
        }
        $dados = json_decode((string) $resposta, true);
        $status = (string) ($dados['status'] ?? '');
        return match ($status) {
            'OK', 'ZERO_RESULTS' => $dados,
            'REQUEST_DENIED' => throw new \RuntimeException('O Google recusou a chave: confira se ela está certa e se a "Geocoding API" está ativada no Google Cloud.'
                . (!empty($dados['error_message']) ? ' (Google: ' . mb_substr((string) $dados['error_message'], 0, 160) . ')' : '')),
            'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT' => throw new \RuntimeException('A cota do Google Maps acabou por agora. O One continua na próxima rodada.'),
            default => throw new \RuntimeException('O Google Maps respondeu com erro (' . ($status ?: 'sem resposta') . ').'),
        };
    }

    /** Teste da chave com um endereço conhecido */
    public static function testar(): array
    {
        if (self::provedor() === 'geoapify') {
            return \App\Integrations\Mapas\GeoapifyClient::daConfig()->testar();
        }
        if (self::chave() === '') {
            return [false, 'Falta a chave do Google Maps.'];
        }
        try {
            $r = self::localizar(['endereco' => 'Avenida Borges de Medeiros, 1501', 'bairro' => 'Praia de Belas', 'cidade' => 'Porto Alegre', 'uf' => 'RS', 'cep' => '90119-900']);
            return $r ? [true, 'Chave funcionando: endereço de teste localizado (' . $r['lat'] . ', ' . $r['lng'] . ').'] : [false, 'A chave funcionou, mas o endereço de teste não foi achado com precisão.'];
        } catch (\RuntimeException $erro) {
            return [false, $erro->getMessage()];
        }
    }

    /** Ajuste à mão (arrastar o ponto no mapa) */
    public static function ajustar(int $clienteId, float $lat, float $lng): bool
    {
        if ($lat < -34 || $lat > 6 || $lng < -74.5 || $lng > -28.5) { // fora do Brasil
            return false;
        }
        Banco::executar("UPDATE clientes SET lat = ?, lng = ?, geo_precisao = 'manual', geo_manual = 1, geo_em = NOW() WHERE id = ?", [round($lat, 6), round($lng, 6), $clienteId]);
        return true;
    }

    /** Volta para o automático (o cron localiza de novo pelo endereço) */
    public static function voltarAutomatico(int $clienteId): void
    {
        Banco::executar('UPDATE clientes SET lat = NULL, lng = NULL, geo_precisao = NULL, geo_hash = NULL, geo_manual = 0, geo_em = NULL WHERE id = ?', [$clienteId]);
    }
}
