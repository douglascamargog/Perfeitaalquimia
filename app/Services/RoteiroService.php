<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\ClienteEvento;
use RuntimeException;

/**
 * Planejador de visitas (roteiro de viagem do vendedor externo).
 *
 * - O externo escolhe as cidades do caminho, em ordem, e as datas. O ASSISTENTE procura as lojas que ficam
 *   até X km da rota (de QUALQUER carteira) e sugere as mais importantes: recompra atrasada, sem compra há 60/90 dias,
 *   maior média mensal e leads ainda não abordados.
 * - As paradas são ordenadas pela posição no caminho e divididas pelos dias da viagem (visitas por dia).
 * - A loja continua da carteira dela. Pedido fechado na visita vai para a DONA da carteira (meta dela);
 *   o externo fica como "tirado por" (pedidos.tirado_por_id) e vê esse valor no Meu mês, sem contar para a meta dele.
 * - A dona da loja recebe um aviso no sino quando a loja dela entra num roteiro e quando o externo fecha pedido.
 *
 * Distâncias em linha reta (pela localização das lojas e das cidades), sem custo de API.
 */
class RoteiroService
{
    public const SITUACOES_PARADA = ['pendente' => 'A visitar', 'visitado' => 'Visitado', 'nao_atendeu' => 'Não atendeu'];

    /** Depois do fim da viagem, o externo ainda acessa as lojas do roteiro por estes dias (para mandar pedidos anotados offline) */
    private const DIAS_DE_FOLGA = 7;

    // ------------------------------------------------------------------ quem pode

    public static function podeVer(array $r): bool
    {
        return (int) $r['usuario_id'] === Auth::id() || Auth::pode('roteiros.gerenciar');
    }

    public static function podeMexer(array $r): bool
    {
        return (int) $r['usuario_id'] === Auth::id() || Auth::pode('*');
    }

    /** Condição SQL: o cliente está num roteiro ativo desta pessoa (dá acesso à ficha, ao pedido e ao app) */
    public static function sqlNoRoteiro(string $colunaCliente = 'c.id'): string
    {
        return "EXISTS (SELECT 1 FROM roteiro_paradas rp JOIN roteiros ro ON ro.id = rp.roteiro_id
            WHERE rp.cliente_id = $colunaCliente AND ro.usuario_id = ? AND ro.situacao = 'planejado'
              AND ro.data_fim >= CURDATE() - INTERVAL " . self::DIAS_DE_FOLGA . " DAY)";
    }

    public static function clienteNoRoteiro(int $clienteId, ?int $usuarioId): bool
    {
        return $usuarioId && (bool) Banco::valor('SELECT ' . self::sqlNoRoteiro('?'), [$clienteId, $usuarioId]);
    }

    // ------------------------------------------------------------------ consultas

    public static function buscar(int $id): ?array
    {
        $r = Banco::um('SELECT r.*, u.nome AS usuario_nome FROM roteiros r JOIN usuarios u ON u.id = r.usuario_id WHERE r.id = ?', [$id]);
        if ($r) {
            $r['cidades'] = self::cidades($r);
        }
        return $r;
    }

    /** Roteiros (os da pessoa; quem gerencia vê todos ou de uma pessoa) */
    public static function lista(?int $usuarioId): array
    {
        $w = $usuarioId ? 'WHERE r.usuario_id = ' . (int) $usuarioId : '';
        $lista = Banco::todos("SELECT r.*, u.nome AS usuario_nome,
                (SELECT COUNT(*) FROM roteiro_paradas p WHERE p.roteiro_id = r.id) AS paradas,
                (SELECT COUNT(*) FROM roteiro_paradas p WHERE p.roteiro_id = r.id AND p.situacao <> 'pendente') AS feitas,
                (SELECT COALESCE(SUM(pe.total), 0) FROM roteiro_paradas p JOIN pedidos pe ON pe.id = p.pedido_id
                  WHERE p.roteiro_id = r.id AND pe.situacao NOT IN ('recusado', 'cancelado')) AS vendido
            FROM roteiros r JOIN usuarios u ON u.id = r.usuario_id $w
            ORDER BY r.situacao = 'planejado' DESC, r.data_inicio DESC, r.id DESC LIMIT 200");
        foreach ($lista as &$r) {
            $r['cidades'] = self::cidades($r);
        }
        return $lista;
    }

    /** Cidades do caminho, na ordem: [['id','nome','uf','lat','lng'], ...] */
    public static function cidades(array $r): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) json_decode((string) ($r['cidades_json'] ?? '[]'), true))));
        if (!$ids) {
            return [];
        }
        $porId = array_column(Banco::todos('SELECT id, nome, uf, lat, lng FROM cidades WHERE id IN (' . implode(',', $ids) . ')'), null, 'id');
        return array_values(array_filter(array_map(fn($id) => $porId[$id] ?? null, $ids)));
    }

    /** Paradas com os dados da loja (quem não gerencia nem é o dono só vê as lojas da própria carteira) */
    public static function paradas(array $r): array
    {
        $soMinhas = !self::podeVer($r);
        $lista = Banco::todos("SELECT p.*, c.razao_social, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome, c.endereco, c.bairro, c.cidade, c.uf,
                c.cep, c.telefone, c.whatsapp, c.responsavel_id, c.ultima_compra_em, c.tipo,
                COALESCE(c.lat, ci.lat) AS lat, COALESCE(c.lng, ci.lng) AS lng, c.lat IS NULL AS aproximada,
                u.nome AS vendedor, pe.total AS pedido_total, pe.situacao AS pedido_situacao
            FROM roteiro_paradas p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN cidades ci ON ci.id = c.cidade_id
            LEFT JOIN usuarios u ON u.id = c.responsavel_id LEFT JOIN pedidos pe ON pe.id = p.pedido_id
            WHERE p.roteiro_id = ? AND c.excluido_em IS NULL" . ($soMinhas ? ' AND c.responsavel_id = ' . (int) Auth::id() : '') . '
            ORDER BY p.dia IS NULL, p.dia, p.ordem, p.id', [$r['id']]);
        return $lista;
    }

    /** Dias da viagem (Y-m-d) */
    public static function dias(array $r): array
    {
        $dias = [];
        for ($t = strtotime($r['data_inicio']); $t <= strtotime($r['data_fim']) && count($dias) < 60; $t += 86400) {
            $dias[] = date('Y-m-d', $t);
        }
        return $dias;
    }

    // ------------------------------------------------------------------ criar e editar

    /**
     * Cria ou atualiza o roteiro. $d: nome, data_inicio, data_fim, cidades[] ("Cidade/UF" ou "Cidade" + uf_padrao), raio_km, visitas_dia, observacoes
     */
    public static function salvar(?int $id, array $d, int $usuarioId): int
    {
        $nome = mb_substr(trim((string) ($d['nome'] ?? '')), 0, 120);
        $inicio = (string) ($d['data_inicio'] ?? '');
        $fim = (string) ($d['data_fim'] ?? '') ?: $inicio;
        if ($nome === '') {
            throw new RuntimeException('Dê um nome ao roteiro (ex.: Serra gaúcha – dezembro).');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim) || $fim < $inicio) {
            throw new RuntimeException('Confira as datas: a volta não pode ser antes da ida.');
        }
        if ((strtotime($fim) - strtotime($inicio)) / 86400 > 30) {
            throw new RuntimeException('Um roteiro pode ter no máximo 31 dias. Divida a viagem em dois roteiros.');
        }
        $ufPadrao = preg_match('/^[A-Z]{2}$/', strtoupper((string) ($d['uf_padrao'] ?? 'RS'))) ? strtoupper((string) $d['uf_padrao']) : 'RS';
        $cidades = [];
        $semLugar = [];
        foreach ((array) ($d['cidades'] ?? []) as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            [$nomeCidade, $uf] = array_map('trim', array_pad(explode('/', $texto, 2), 2, $ufPadrao));
            $cidade = self::cidadeComLocalizacao($nomeCidade, strtoupper($uf ?: $ufPadrao));
            if ($cidade) {
                $cidades[] = (int) $cidade['id'];
            } else {
                $semLugar[] = $texto;
            }
        }
        if ($semLugar) {
            throw new RuntimeException('Não encontrei no mapa: ' . implode(', ', $semLugar) . '. Confira o nome (com a UF, ex.: Ijuí/RS)'
                . (LocalizacaoService::chave() === '' ? ' — para cidades que ainda não têm lojas no One, o administrador precisa cadastrar a chave do serviço de mapas (⚙️ Administração › Mapas).' : '.'));
        }
        if (!$cidades) {
            throw new RuntimeException('Escolha pelo menos uma cidade do caminho.');
        }
        $dados = [
            'nome' => $nome, 'data_inicio' => $inicio, 'data_fim' => $fim, 'cidades_json' => json_encode(array_slice($cidades, 0, 25)),
            'raio_km' => max(1, min(150, (int) ($d['raio_km'] ?? 0) ?: (int) Config::get('roteiros.raio_km', 15))),
            'visitas_dia' => max(1, min(40, (int) ($d['visitas_dia'] ?? 0) ?: (int) Config::get('roteiros.visitas_dia', 8))),
            'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 2000) ?: null,
        ];
        if ($id) {
            Banco::atualizar('roteiros', $id, $dados);
            // dias fora das novas datas voltam para "sem dia"
            Banco::executar("UPDATE roteiro_paradas SET dia = NULL WHERE roteiro_id = ? AND situacao = 'pendente' AND (dia < ? OR dia > ?)", [$id, $inicio, $fim]);
            Auditoria::registrar('roteiro', $id, 'editar', null, $dados);
            self::calcularTrajeto(self::buscar($id));
            return $id;
        }
        $id = Banco::inserir('roteiros', $dados + ['usuario_id' => $usuarioId]);
        Auditoria::registrar('roteiro', $id, 'criar', null, $dados);
        self::calcularTrajeto(self::buscar($id));
        return $id;
    }

    /** Cidade com latitude/longitude: da tabela de cidades ou, se faltar, do Google Maps (e guarda) */
    public static function cidadeComLocalizacao(string $nome, string $uf): ?array
    {
        if ($nome === '' || !preg_match('/^[A-Z]{2}$/', $uf)) {
            return null;
        }
        $c = Banco::um('SELECT id, nome, uf, lat, lng FROM cidades WHERE uf = ? AND nome = ? ORDER BY lat IS NULL LIMIT 1', [$uf, $nome]);
        if ($c && $c['lat'] !== null) {
            return $c;
        }
        // Centro das lojas que já estão localizadas nessa cidade
        $media = Banco::um('SELECT AVG(lat) AS lat, AVG(lng) AS lng FROM clientes WHERE uf = ? AND cidade = ? AND lat IS NOT NULL AND excluido_em IS NULL', [$uf, $nome]);
        $ponto = $media && $media['lat'] !== null ? ['lat' => round((float) $media['lat'], 6), 'lng' => round((float) $media['lng'], 6)] : null;
        if (!$ponto) {
            try {
                $ponto = LocalizacaoService::localizarCidade($nome, $uf);
            } catch (\Throwable $e) {
                error_log('Roteiro: cidade não localizada (' . $e->getMessage() . ')');
            }
        }
        if (!$ponto) {
            return null;
        }
        if ($c) {
            Banco::executar('UPDATE cidades SET lat = ?, lng = ? WHERE id = ?', [$ponto['lat'], $ponto['lng'], $c['id']]);
            return ['id' => $c['id'], 'nome' => $c['nome'], 'uf' => $uf] + $ponto;
        }
        $id = Banco::inserir('cidades', ['nome' => $nome, 'uf' => $uf] + $ponto);
        return ['id' => $id, 'nome' => $nome, 'uf' => $uf] + $ponto;
    }

    /** Cidades para a lista de sugestões do formulário ("Nome/UF") */
    public static function cidadesConhecidas(): array
    {
        return array_column(Banco::todos("SELECT DISTINCT CONCAT(nome, '/', uf) AS t FROM (
                SELECT nome, uf FROM cidades UNION SELECT cidade, uf FROM clientes WHERE excluido_em IS NULL AND cidade IS NOT NULL AND uf IS NOT NULL
            ) x ORDER BY t LIMIT 6000"), 't');
    }

    public static function concluir(array $r, bool $concluir = true): void
    {
        Banco::executar('UPDATE roteiros SET situacao = ? WHERE id = ?', [$concluir ? 'concluido' : 'planejado', $r['id']]);
        Auditoria::registrar('roteiro', (int) $r['id'], $concluir ? 'concluir' : 'reabrir');
    }

    public static function cancelar(array $r): void
    {
        Banco::executar("UPDATE roteiros SET situacao = 'cancelado' WHERE id = ?", [$r['id']]);
        Auditoria::registrar('roteiro', (int) $r['id'], 'cancelar');
    }

    // ------------------------------------------------------------------ assistente: lojas no caminho

    /**
     * Lojas a até raio_km da rota (todas as carteiras), com a distância, a posição no caminho e a prioridade.
     * Devolve ['lojas' => [...], 'sem_localizacao' => n]. $f: so ('sugeridas'|'todas'), tipo, vendedor
     */
    public static function lojasNoCaminho(array $r, array $f = []): array
    {
        $rota = self::rota($r);
        if (!$rota['pontos']) {
            return ['lojas' => [], 'sem_localizacao' => 0];
        }
        $raio = (float) $r['raio_km'];
        // Caixa em volta da rota (+ raio) para o banco já filtrar
        $lats = array_column($rota['pontos'], 'lat');
        $lngs = array_column($rota['pontos'], 'lng');
        $dLat = $raio / 110.57;
        $dLng = $raio / (111.32 * cos(deg2rad(array_sum($lats) / count($lats))));
        $caixa = [min($lats) - $dLat, max($lats) + $dLat, min($lngs) - $dLng, max($lngs) + $dLng];

        $tolerancia = (int) Config::get('recompra.tolerancia_dias', 3);
        $de = date('Y-m-d', strtotime('-12 months'));
        $candidatas = Banco::todos("SELECT c.id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome, c.cidade, c.uf, c.tipo, c.contatado,
                c.responsavel_id, u.nome AS vendedor, c.ultima_compra_em, c.credito_situacao,
                COALESCE(c.lat, ci.lat) AS lat, COALESCE(c.lng, ci.lng) AS lng, c.lat IS NULL AS aproximada,
                (c.intervalo_medio_dias > 0 AND c.ultima_compra_em + INTERVAL (c.intervalo_medio_dias + $tolerancia) DAY < CURDATE()) AS recompra_atrasada,
                COALESCE(v.total, 0) AS comprou_12m,
                EXISTS (SELECT 1 FROM roteiro_paradas rp WHERE rp.roteiro_id = ? AND rp.cliente_id = c.id) AS no_roteiro
            FROM clientes c LEFT JOIN cidades ci ON ci.id = c.cidade_id LEFT JOIN usuarios u ON u.id = c.responsavel_id
            LEFT JOIN (SELECT cliente_id, SUM(total) AS total FROM pedidos WHERE situacao = 'faturado' AND criado_em >= ? GROUP BY cliente_id) v ON v.cliente_id = c.id
            WHERE c.excluido_em IS NULL AND COALESCE(c.lat, ci.lat) BETWEEN ? AND ? AND COALESCE(c.lng, ci.lng) BETWEEN ? AND ?
            LIMIT 5000", [$r['id'], $de, $caixa[0], $caixa[1], $caixa[2], $caixa[3]]);

        $lojas = [];
        foreach ($candidatas as $c) {
            [$dist, $pos] = self::distanciaDaRota((float) $c['lat'], (float) $c['lng'], $rota);
            if ($dist > $raio) {
                continue;
            }
            $c['distancia_km'] = round($dist, 1);
            $c['posicao_km'] = round($pos, 1);
            $c['media_mensal'] = (float) $c['comprou_12m'] / 12;
            [$c['pontos'], $c['motivos']] = self::prioridade($c);
            if (($f['tipo'] ?? '') !== '' && $c['tipo'] !== $f['tipo']) {
                continue;
            }
            if ((int) ($f['vendedor'] ?? 0) && (int) $c['responsavel_id'] !== (int) $f['vendedor']) {
                continue;
            }
            $lojas[] = $c;
        }
        // Mais importantes primeiro; empate: na ordem do caminho
        usort($lojas, fn($a, $b) => [$b['pontos'], $a['posicao_km']] <=> [$a['pontos'], $b['posicao_km']]);
        // Sugeridas: o que cabe na viagem (visitas por dia × dias), só das que têm algum motivo
        $vagas = (int) $r['visitas_dia'] * count(self::dias($r));
        $sugeridas = 0;
        foreach ($lojas as &$l) {
            $l['sugerida'] = !$l['no_roteiro'] && $l['pontos'] > 0 && $sugeridas < $vagas;
            $sugeridas += $l['sugerida'] ? 1 : 0;
        }
        unset($l);
        if (($f['so'] ?? '') === 'sugeridas') {
            $lojas = array_values(array_filter($lojas, fn($l) => $l['sugerida']));
        }
        $semLocalizacao = (int) Banco::valor('SELECT COUNT(*) FROM clientes c LEFT JOIN cidades ci ON ci.id = c.cidade_id
            WHERE c.excluido_em IS NULL AND COALESCE(c.lat, ci.lat) IS NULL AND c.cidade IN (' . implode(',', array_fill(0, count($r['cidades']), '?')) . ')',
            array_column($r['cidades'], 'nome'));
        return ['lojas' => $lojas, 'sem_localizacao' => $semLocalizacao];
    }

    /** Pontos de prioridade e os motivos (aparecem na tela) */
    private static function prioridade(array $c): array
    {
        $pontos = 0;
        $motivos = [];
        $dias = $c['ultima_compra_em'] ? (int) floor((time() - strtotime($c['ultima_compra_em'])) / 86400) : null;
        if ($c['recompra_atrasada']) {
            $pontos += 40;
            $motivos[] = 'recompra atrasada';
        }
        if ($dias !== null && $dias >= 90) {
            $pontos += 30;
            $motivos[] = "sem compra há $dias dias";
        } elseif ($dias !== null && $dias >= 60) {
            $pontos += 20;
            $motivos[] = "sem compra há $dias dias";
        }
        if ($c['media_mensal'] > 0) {
            $pontos += (int) min(30, round(log10(1 + $c['media_mensal']) * 8)); // R$ 100/mês ≈ 16 · R$ 1.000/mês ≈ 24 · R$ 5.000+ = 30
            $motivos[] = 'compra ' . dinheiro($c['media_mensal']) . '/mês';
        }
        if ($c['tipo'] === 'lead' && !(int) $c['contatado']) {
            $pontos += 10;
            $motivos[] = 'lead ainda não abordado';
        }
        if ($c['credito_situacao'] === 'atraso') {
            $pontos -= 25;
            $motivos[] = 'título em atraso';
        }
        return [$pontos, $motivos];
    }

    /** Rota em km (projeção plana, ótima para distâncias de um estado): pontos, segmentos e km acumulados */
    private static function rota(array $r): array
    {
        // Trajeto real pelas estradas (Geoapify), quando calculado; senão, as cidades ligadas em linha reta
        $trajeto = array_map(fn($p) => ['lat' => (float) $p[0], 'lng' => (float) $p[1]], (array) json_decode((string) ($r['trajeto_json'] ?? ''), true));
        $pontos = count($trajeto) >= 2 ? $trajeto : array_values(array_filter($r['cidades'], fn($c) => $c['lat'] !== null));
        if (!$pontos) {
            return ['pontos' => []];
        }
        $lat0 = deg2rad(array_sum(array_column($pontos, 'lat')) / count($pontos));
        $xy = array_map(fn($p) => [(float) $p['lng'] * 111.32 * cos($lat0), (float) $p['lat'] * 110.57], $pontos);
        $acumulado = [0.0];
        for ($i = 1; $i < count($xy); $i++) {
            $acumulado[$i] = $acumulado[$i - 1] + hypot($xy[$i][0] - $xy[$i - 1][0], $xy[$i][1] - $xy[$i - 1][1]);
        }
        return ['pontos' => $pontos, 'xy' => $xy, 'acumulado' => $acumulado, 'lat0' => $lat0];
    }

    /** [distância até a rota (km), posição ao longo da rota (km desde a saída)] */
    private static function distanciaDaRota(float $lat, float $lng, array $rota): array
    {
        $p = [$lng * 111.32 * cos($rota['lat0']), $lat * 110.57];
        $xy = $rota['xy'];
        if (count($xy) === 1) {
            return [hypot($p[0] - $xy[0][0], $p[1] - $xy[0][1]), 0.0];
        }
        $melhor = [INF, 0.0];
        for ($i = 0; $i < count($xy) - 1; $i++) {
            [$ax, $ay] = $xy[$i];
            [$bx, $by] = $xy[$i + 1];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0, min(1, (($p[0] - $ax) * $dx + ($p[1] - $ay) * $dy) / $len2)) : 0;
            $d = hypot($p[0] - ($ax + $t * $dx), $p[1] - ($ay + $t * $dy));
            if ($d < $melhor[0]) {
                $melhor = [$d, $rota['acumulado'][$i] + $t * sqrt($len2)];
            }
        }
        return $melhor;
    }

    // ------------------------------------------------------------------ paradas

    /** Coloca lojas no roteiro e avisa as donas. Devolve quantas entraram. */
    public static function adicionar(array $r, array $clienteIds, int $usuarioId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));
        if (!$ids) {
            return 0;
        }
        $novas = [];
        Banco::transacao(function () use ($r, $ids, &$novas) {
            foreach (array_slice($ids, 0, 300) as $cid) {
                if (!Banco::valor('SELECT 1 FROM clientes WHERE id = ? AND excluido_em IS NULL', [$cid])) {
                    continue;
                }
                if (Banco::executar('INSERT IGNORE INTO roteiro_paradas (roteiro_id, cliente_id) VALUES (?, ?)', [$r['id'], $cid])) {
                    $novas[] = $cid;
                }
            }
        });
        if ($novas) {
            self::organizar($r);
            self::avisarDonas($r, $novas, $usuarioId);
            Auditoria::registrar('roteiro', (int) $r['id'], 'adicionar_lojas', null, ['clientes' => $novas]);
        }
        return count($novas);
    }

    /** A dona de cada loja recebe UM aviso por vez, com as lojas dela que entraram no roteiro */
    private static function avisarDonas(array $r, array $clienteIds, int $usuarioId): void
    {
        $lista = Banco::todos('SELECT c.id, c.responsavel_id, COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS nome, p.dia
            FROM clientes c JOIN roteiro_paradas p ON p.cliente_id = c.id AND p.roteiro_id = ?
            WHERE c.id IN (' . implode(',', array_map('intval', $clienteIds)) . ') AND c.responsavel_id IS NOT NULL AND c.responsavel_id <> ?', [$r['id'], $usuarioId]);
        $quem = EquipeService::comoChamar(Banco::um('SELECT * FROM usuarios WHERE id = ?', [$usuarioId]) ?: []) ?: 'O externo';
        $porDona = [];
        foreach ($lista as $l) {
            $porDona[(int) $l['responsavel_id']][] = $l;
        }
        $periodo = data_br($r['data_inicio']) . ($r['data_fim'] !== $r['data_inicio'] ? ' a ' . data_br($r['data_fim']) : '');
        foreach ($porDona as $dona => $lojas) {
            $nomes = array_map(fn($l) => $l['nome'] . ($l['dia'] ? ' (' . date('d/m', strtotime($l['dia'])) . ')' : ''), $lojas);
            $um = count($lojas) === 1;
            NotificacaoService::avisar([$dona], 'roteiro_visita',
                $um ? "$quem vai visitar {$lojas[0]['nome']}" : "$quem vai visitar " . count($lojas) . ' lojas da sua carteira',
                $um ? '/carteira/clientes/' . (int) $lojas[0]['id'] : '/roteiros/' . (int) $r['id'],
                ['texto' => 'Roteiro "' . $r['nome'] . '" · ' . $periodo . ' · ' . implode(', ', array_slice($nomes, 0, 8)) . (count($nomes) > 8 ? '…' : ''),
                 'cliente_id' => $um ? (int) $lojas[0]['id'] : null]);
        }
    }

    public static function remover(array $r, int $paradaId): void
    {
        Banco::executar("DELETE FROM roteiro_paradas WHERE id = ? AND roteiro_id = ? AND situacao = 'pendente' AND pedido_id IS NULL", [$paradaId, $r['id']]);
    }

    /**
     * Organiza as paradas a visitar pela posição no caminho e divide pelos dias (visitas por dia).
     * Paradas já visitadas ficam onde estão; as que a pessoa mudou de dia à mão também (fixado = dia escolhido).
     */
    public static function organizar(array $r, bool $refazerTudo = false): void
    {
        $r['cidades'] = $r['cidades'] ?? self::cidades($r);
        $rota = self::rota($r);
        $paradas = Banco::todos('SELECT p.id, p.dia, p.situacao, COALESCE(c.lat, ci.lat) AS lat, COALESCE(c.lng, ci.lng) AS lng
            FROM roteiro_paradas p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN cidades ci ON ci.id = c.cidade_id WHERE p.roteiro_id = ?', [$r['id']]);
        foreach ($paradas as &$p) {
            $p['pos'] = $rota['pontos'] && $p['lat'] !== null ? self::distanciaDaRota((float) $p['lat'], (float) $p['lng'], $rota)[1] : 1e9;
        }
        unset($p);
        usort($paradas, fn($a, $b) => $a['pos'] <=> $b['pos']);
        $dias = self::dias($r);
        $porDia = (int) $r['visitas_dia'];
        $ocupado = array_fill_keys($dias, 0);
        foreach ($paradas as $p) {
            if ($p['dia'] && isset($ocupado[$p['dia']]) && ($p['situacao'] !== 'pendente' || !$refazerTudo)) {
                $ocupado[$p['dia']]++;
            }
        }
        $ordem = array_fill_keys($dias, 0);
        foreach ($paradas as $p) {
            $dia = $p['dia'];
            if ($p['situacao'] === 'pendente' && ($refazerTudo || !$dia || !isset($ocupado[$dia]))) {
                $dia = null;
                foreach ($dias as $d) { // primeiro dia (pela ordem do caminho) que ainda tem vaga
                    if ($ocupado[$d] < $porDia) {
                        $dia = $d;
                        $ocupado[$d]++;
                        break;
                    }
                }
                $dia = $dia ?? end($dias); // passou do limite: vai para o último dia
            }
            $ordem[$dia] = ($ordem[$dia] ?? 0) + 1;
            Banco::executar('UPDATE roteiro_paradas SET dia = ?, ordem = ? WHERE id = ?', [$dia, $ordem[$dia], $p['id']]);
        }
        self::otimizarDias($r);
    }

    /** O serviço de rotas está disponível? (Geoapify com chave) */
    public static function rotasReais(): bool
    {
        return LocalizacaoService::provedor() === 'geoapify' && LocalizacaoService::chave() !== '';
    }

    /**
     * Trajeto pelas estradas entre as cidades do roteiro (uma consulta à Geoapify). Guarda os pontos, km e tempo.
     * Sem chave ou com erro, o roteiro continua em linha reta e o motivo aparece na tela.
     */
    public static function calcularTrajeto(array $r): void
    {
        $cidades = array_values(array_filter($r['cidades'] ?? self::cidades($r), fn($c) => $c['lat'] !== null));
        $limpar = ['trajeto_json' => null, 'km_total' => null, 'minutos_total' => null];
        if (!self::rotasReais()) {
            Banco::atualizar('roteiros', (int) $r['id'], $limpar + ['rota_aviso' => LocalizacaoService::provedor() === 'geoapify'
                ? 'Distâncias em linha reta: cadastre a chave da Geoapify em Administração › Mapas para usar as estradas.' : null]);
            return;
        }
        if (count($cidades) < 2) {
            Banco::atualizar('roteiros', (int) $r['id'], $limpar + ['rota_aviso' => null]);
            return;
        }
        try {
            $rota = \App\Integrations\Mapas\GeoapifyClient::daConfig()->rota($cidades);
            $pontos = $rota['trajeto'];
            $passo = max(1, (int) ceil(count($pontos) / 600)); // até ~600 pontos: leve para guardar e para o mapa
            $simples = [];
            foreach ($pontos as $i => $pt) {
                if ($i % $passo === 0 || $i === count($pontos) - 1) {
                    $simples[] = [round($pt[0], 5), round($pt[1], 5)];
                }
            }
            Banco::atualizar('roteiros', (int) $r['id'], ['trajeto_json' => json_encode($simples), 'km_total' => $rota['km'], 'minutos_total' => $rota['minutos'], 'rota_aviso' => null]);
        } catch (\RuntimeException $e) {
            error_log('Roteiro ' . $r['id'] . ': trajeto não calculado (' . $e->getMessage() . ')');
            Banco::atualizar('roteiros', (int) $r['id'], $limpar + ['rota_aviso' => 'Distâncias em linha reta: ' . mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    /**
     * Melhor ordem das visitas de cada dia (Geoapify Route Planner: uma consulta por dia), com km e tempo do dia.
     * O dia começa onde o anterior terminou (o primeiro, na cidade de saída). Visitas já feitas ficam no começo, como estão.
     */
    private static function otimizarDias(array $r): void
    {
        if (!self::rotasReais()) {
            return;
        }
        $r['cidades'] = $r['cidades'] ?? self::cidades($r);
        $inicio = array_values(array_filter($r['cidades'], fn($c) => $c['lat'] !== null))[0] ?? null;
        $resumo = [];
        foreach (self::dias($r) as $dia) {
            $lista = Banco::todos("SELECT p.id, p.situacao, p.ordem, COALESCE(c.lat, ci.lat) AS lat, COALESCE(c.lng, ci.lng) AS lng
                FROM roteiro_paradas p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN cidades ci ON ci.id = c.cidade_id
                WHERE p.roteiro_id = ? AND p.dia = ? ORDER BY p.situacao = 'pendente', p.ordem, p.id", [$r['id'], $dia]);
            $feitas = array_values(array_filter($lista, fn($p) => $p['situacao'] !== 'pendente' || $p['lat'] === null));
            $pendentes = array_values(array_filter($lista, fn($p) => $p['situacao'] === 'pendente' && $p['lat'] !== null));
            if (!$pendentes || !$inicio) {
                continue;
            }
            $saida = $feitas && end($feitas)['lat'] !== null ? end($feitas) : $inicio;
            try {
                $plano = \App\Integrations\Mapas\GeoapifyClient::daConfig()->ordenar(['lat' => (float) $saida['lat'], 'lng' => (float) $saida['lng']],
                    array_map(fn($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']], $pendentes));
            } catch (\RuntimeException $e) {
                error_log('Roteiro ' . $r['id'] . ': ordem do dia ' . $dia . ' não calculada (' . $e->getMessage() . ')');
                Banco::executar('UPDATE roteiros SET rota_aviso = ? WHERE id = ?', ['Melhor ordem das visitas não calculada: ' . mb_substr($e->getMessage(), 0, 180), $r['id']]);
                return;
            }
            $n = count($feitas);
            foreach ($plano['ordem'] as $i) {
                Banco::executar('UPDATE roteiro_paradas SET ordem = ? WHERE id = ?', [++$n, $pendentes[$i]['id']]);
            }
            $resumo[$dia] = ['km' => $plano['km'], 'minutos' => $plano['minutos']];
            $ultima = $pendentes[end($plano['ordem'])];
            $inicio = ['lat' => $ultima['lat'], 'lng' => $ultima['lng']]; // o próximo dia sai daqui
        }
        Banco::executar('UPDATE roteiros SET dias_json = ? WHERE id = ?', [$resumo ? json_encode($resumo) : null, $r['id']]);
    }

    public static function mudarDia(array $r, int $paradaId, string $dia): void
    {
        if (!in_array($dia, self::dias($r), true)) {
            throw new RuntimeException('Escolha um dia da viagem.');
        }
        $ordem = (int) Banco::valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM roteiro_paradas WHERE roteiro_id = ? AND dia = ?', [$r['id'], $dia]);
        Banco::executar('UPDATE roteiro_paradas SET dia = ?, ordem = ? WHERE id = ? AND roteiro_id = ?', [$dia, $ordem, $paradaId, $r['id']]);
    }

    /** Visitado / não atendeu (vai para a linha do tempo da loja, como visita presencial) */
    public static function registrarVisita(array $r, int $paradaId, string $situacao, string $observacao, int $usuarioId): void
    {
        if (!isset(self::SITUACOES_PARADA[$situacao])) {
            throw new RuntimeException('Situação inválida.');
        }
        $p = Banco::um('SELECT * FROM roteiro_paradas WHERE id = ? AND roteiro_id = ?', [$paradaId, $r['id']]);
        if (!$p) {
            throw new RuntimeException('Parada não encontrada.');
        }
        $observacao = mb_substr(trim($observacao), 0, 500);
        Banco::executar('UPDATE roteiro_paradas SET situacao = ?, observacao = ?, visitado_em = ? WHERE id = ?',
            [$situacao, $observacao ?: null, $situacao === 'pendente' ? null : date('Y-m-d H:i:s'), $paradaId]);
        if ($situacao !== 'pendente' && $p['situacao'] === 'pendente') {
            $texto = ($situacao === 'visitado' ? 'Visita do externo' : 'Visita do externo: loja fechada / não atendeu') . ' (roteiro "' . $r['nome'] . '")' . ($observacao ? ": $observacao" : '');
            if ($situacao === 'visitado') {
                CarteiraService::registrarContato((int) $p['cliente_id'], date('Y-m-d'), 'Visita presencial', $texto, $usuarioId);
            } else {
                ClienteEvento::registrar((int) $p['cliente_id'], 'visita', $texto, $usuarioId, 'Visita presencial');
            }
        }
    }

    /** Link do Google Maps com a rota (até 10 paradas por link: origem + 8 paradas + destino) */
    public static function linksGoogleMaps(array $paradas): array
    {
        $pontos = array_values(array_filter($paradas, fn($p) => $p['situacao'] === 'pendente' && self::destinoGoogle($p) !== ''));
        $links = [];
        foreach (array_chunk($pontos, 10) as $grupo) {
            $destino = array_pop($grupo);
            $links[] = 'https://www.google.com/maps/dir/?' . http_build_query(array_filter([
                'api' => 1, 'destination' => self::destinoGoogle($destino), 'travelmode' => 'driving',
                'waypoints' => $grupo ? implode('|', array_map([self::class, 'destinoGoogle'], $grupo)) : null,
            ]));
        }
        return $links;
    }

    /**
     * Para onde o Google Maps leva: o ENDEREÇO da loja (rua e número, bairro, cidade - UF, CEP), que o Google acha certinho.
     * Loja sem endereço: o ponto do mapa (latitude, longitude); sem ponto, só a cidade.
     */
    public static function destinoGoogle(array $p): string
    {
        $endereco = trim((string) ($p['endereco'] ?? ''));
        if ($endereco !== '') {
            $cep = preg_replace('/\D/', '', (string) ($p['cep'] ?? ''));
            return str_replace('|', ' ', implode(', ', array_filter([$endereco, trim((string) ($p['bairro'] ?? '')),
                trim((string) $p['cidade']) . ' - ' . $p['uf'], strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : '', 'Brasil'])));
        }
        if (($p['lat'] ?? null) !== null) {
            return number_format((float) $p['lat'], 6, '.', '') . ',' . number_format((float) $p['lng'], 6, '.', '');
        }
        return trim((string) ($p['cidade'] ?? '')) !== '' ? $p['cidade'] . ' - ' . $p['uf'] . ', Brasil' : '';
    }

    // ------------------------------------------------------------------ pedidos tirados em visita

    /**
     * Regra do pedido (PedidoService::criar): se quem monta o pedido NÃO é a dona da loja e a loja está num roteiro
     * ativo dessa pessoa, a venda vai para a dona e a pessoa fica como "tirado por". Devolve [vendedor_id, tirado_por_id].
     */
    public static function vendedorDoPedido(array $cliente, ?int $usuarioId): ?array
    {
        $dona = (int) ($cliente['responsavel_id'] ?? 0);
        if (!$usuarioId || !$dona || $dona === $usuarioId || !self::clienteNoRoteiro((int) $cliente['id'], $usuarioId)) {
            return null;
        }
        return [$dona, $usuarioId];
    }

    /** Depois de criar o pedido tirado em visita: marca a parada como visitada (com o pedido) e avisa a dona */
    public static function pedidoTirado(int $pedidoId, array $cliente, int $tiradoPor, float $total): void
    {
        $parada = Banco::um("SELECT p.id, r.nome FROM roteiro_paradas p JOIN roteiros r ON r.id = p.roteiro_id
            WHERE p.cliente_id = ? AND r.usuario_id = ? AND r.situacao = 'planejado' ORDER BY r.data_inicio DESC LIMIT 1", [$cliente['id'], $tiradoPor]);
        if ($parada) {
            Banco::executar("UPDATE roteiro_paradas SET pedido_id = ?, situacao = 'visitado', visitado_em = COALESCE(visitado_em, NOW()) WHERE id = ?", [$pedidoId, $parada['id']]);
        }
        $quem = EquipeService::comoChamar(Banco::um('SELECT * FROM usuarios WHERE id = ?', [$tiradoPor]) ?: []) ?: 'O externo';
        $loja = $cliente['nome_fantasia'] ?: $cliente['razao_social'];
        NotificacaoService::avisar([(int) $cliente['responsavel_id']], 'roteiro_pedido', "$quem fechou pedido na $loja",
            '/pedidos/' . $pedidoId, ['texto' => 'Pedido #' . $pedidoId . ' · ' . dinheiro($total) . ' em visita' . ($parada ? ' do roteiro "' . $parada['nome'] . '"' : '')
                . '. A venda é sua (conta para a sua meta).', 'cliente_id' => (int) $cliente['id'], 'pedido_id' => $pedidoId]);
    }

    /** Pedidos que a pessoa tirou em visitas de outras carteiras, no mês (Meu mês) */
    public static function pedidosTirados(int $usuarioId, string $mes): array
    {
        $lista = Banco::todos("SELECT p.id, p.cliente_id, p.total, p.situacao, p.criado_em, p.faturado_em, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente,
                c.cidade, c.uf, u.nome AS dona
            FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN usuarios u ON u.id = p.vendedor_id
            WHERE p.tirado_por_id = ? AND p.criado_em >= ? AND p.criado_em < ? + INTERVAL 1 MONTH AND p.situacao NOT IN ('recusado', 'cancelado')
            ORDER BY p.criado_em DESC", [$usuarioId, "$mes-01", "$mes-01"]);
        return [
            'pedidos' => $lista,
            'total' => array_sum(array_map(fn($p) => (float) $p['total'], $lista)),
            'faturado' => array_sum(array_map(fn($p) => $p['situacao'] === 'faturado' ? (float) $p['total'] : 0.0, $lista)),
        ];
    }
}
