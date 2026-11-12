<?php
namespace App\Integrations\BuscaContatos;

/**
 * Busca de contatos SIMULADA (demonstração).
 * Devolve sugestões fictícias, mas coerentes com a empresa, e sempre as mesmas para o mesmo CNPJ/nome.
 * Não usa internet e não tem custo. Os links de "fonte" apontam só para uma pesquisa no Google (nunca para perfis/sites reais).
 */
class BuscaContatosFake implements BuscaContatosInterface
{
    public function nome(): string
    {
        return 'fake';
    }

    public function buscar(array $empresa): array
    {
        usleep(900_000); // parece que está pesquisando (a tela mostra o One procurando)
        $nome = trim((string) ($empresa['nome_fantasia'] ?: $empresa['razao_social']));
        $semente = crc32(($empresa['cnpj'] ?? '') . $nome);
        // DDD da capital de cada UF (só para o número fictício parecer da região certa)
        $ddd = ['AC' => '68', 'AL' => '82', 'AM' => '92', 'AP' => '96', 'BA' => '71', 'CE' => '85', 'DF' => '61', 'ES' => '27', 'GO' => '62',
            'MA' => '98', 'MG' => '31', 'MS' => '67', 'MT' => '65', 'PA' => '91', 'PB' => '83', 'PE' => '81', 'PI' => '86', 'PR' => '41',
            'RJ' => '21', 'RN' => '84', 'RO' => '69', 'RR' => '95', 'RS' => '51', 'SC' => '48', 'SE' => '79', 'SP' => '11', 'TO' => '63'][$empresa['uf'] ?? ''] ?? '51';
        $usuario = substr(preg_replace('/[^a-z0-9]/', '', \App\Services\Normalizar::semAcento(mb_strtolower($nome))), 0, 22) ?: 'loja';
        $busca = 'https://www.google.com/search?q=' . rawurlencode($nome . ' ' . ($empresa['cidade'] ?? ''));

        $contatos = [
            ['tipo' => 'whatsapp', 'valor' => sprintf('%s9%04d%04d', $ddd, 8000 + $semente % 1999, $semente % 10000), 'fonte' => $busca,
                'fonte_nome' => 'Instagram (bio da loja) — simulado', 'confianca' => 'alta', 'observacao' => 'Número que aparece no botão de WhatsApp do perfil'],
            ['tipo' => 'instagram', 'valor' => "@$usuario", 'fonte' => $busca,
                'fonte_nome' => 'Instagram — simulado', 'confianca' => 'alta', 'observacao' => 'Perfil com publicações recentes'],
            ['tipo' => 'telefone', 'valor' => sprintf('%s3%03d%04d', $ddd, 100 + ($semente >> 3) % 899, ($semente >> 5) % 10000), 'fonte' => $busca,
                'fonte_nome' => 'Google Maps (ficha da empresa) — simulado', 'confianca' => 'media', 'observacao' => 'Telefone fixo da ficha do Google'],
        ];
        if ($semente % 3 !== 0) {
            $contatos[] = ['tipo' => 'email', 'valor' => "contato@$usuario.com.br", 'fonte' => $busca,
                'fonte_nome' => 'Site da empresa — simulado', 'confianca' => 'media', 'observacao' => 'Página "Fale conosco"'];
            $contatos[] = ['tipo' => 'site', 'valor' => "https://www.$usuario.com.br", 'fonte' => $busca,
                'fonte_nome' => 'Resultado de busca — simulado', 'confianca' => 'media', 'observacao' => ''];
        }
        return [
            'contatos' => $contatos,
            'resumo'   => 'DEMONSTRAÇÃO: contatos fictícios. Com a busca real ligada, a IA pesquisa no Google, Instagram, site e listas comerciais.',
            'uso'      => ['buscas_web' => 0, 'tokens_entrada' => 0, 'tokens_saida' => 0, 'custo_estimado' => 0.0],
        ];
    }
}
