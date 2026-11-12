<?php
namespace App\Integrations\BuscaContatos;

/**
 * Consulta GRATUITA do CNPJ na Receita Federal pela BrasilAPI (https://brasilapi.com.br).
 * Devolve telefone/e-mail/endereço do cadastro do CNPJ como sugestões, e a situação cadastral.
 * Se a consulta falhar (sem internet, fora do ar, CNPJ inexistente), devolve null: a busca segue sem ela.
 */
class ReceitaCnpj
{
    private const URL = 'https://brasilapi.com.br/api/cnpj/v1/';

    public function consultar(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14 || !function_exists('curl_init')) {
            return null;
        }
        $curl = curl_init(self::URL . $cnpj);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_USERAGENT => 'One/1.0']);
        $corpo = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $d = $status === 200 && is_string($corpo) ? json_decode($corpo, true) : null;
        if (!is_array($d)) {
            return null;
        }

        $fonte = 'https://brasilapi.com.br/api/cnpj/v1/' . $cnpj;
        $contatos = [];
        foreach (['ddd_telefone_1', 'ddd_telefone_2'] as $campo) {
            $tel = preg_replace('/\D/', '', (string) ($d[$campo] ?? ''));
            if (strlen($tel) >= 10) {
                $contatos[] = ['tipo' => strlen($tel) === 11 ? 'whatsapp' : 'telefone', 'valor' => $tel, 'fonte' => $fonte,
                    'fonte_nome' => 'Receita Federal (cadastro do CNPJ)', 'confianca' => 'media', 'observacao' => 'Telefone informado no CNPJ (pode ser do contador)'];
            }
        }
        if (!empty($d['email'])) {
            $contatos[] = ['tipo' => 'email', 'valor' => mb_strtolower(trim($d['email'])), 'fonte' => $fonte,
                'fonte_nome' => 'Receita Federal (cadastro do CNPJ)', 'confianca' => 'media', 'observacao' => 'E-mail do CNPJ (pode ser do contador)'];
        }
        $endereco = trim(implode(', ', array_filter([trim(($d['descricao_tipo_de_logradouro'] ?? '') . ' ' . ($d['logradouro'] ?? '')), $d['numero'] ?? '', $d['bairro'] ?? ''])));
        if ($endereco !== '') {
            $contatos[] = ['tipo' => 'endereco', 'valor' => $endereco . ' — ' . ($d['municipio'] ?? '') . '/' . ($d['uf'] ?? ''), 'fonte' => $fonte,
                'fonte_nome' => 'Receita Federal (cadastro do CNPJ)', 'confianca' => 'alta', 'observacao' => ''];
        }
        return [
            'situacao' => (string) ($d['descricao_situacao_cadastral'] ?? ''),   // ATIVA, BAIXADA, INAPTA...
            'contatos' => $contatos,
        ];
    }
}
