<?php
namespace App\Services;

/**
 * Leitura dos dados da Carteira Comercial V4 (o sistema antigo, referencias/dashboard.html).
 *
 * - lojasDoHtml(): as 499 lojas que estão embutidas no HTML (<script id="leadData">)
 * - lojasDoBackup(): o arquivo "Backup JSON" exportado pela V4 (lojas criadas + alterações da equipe)
 *
 * Os dois devolvem a mesma estrutura: lista de lojas no formato da V4 (name, city, segment, stage...).
 * A conversão para o formato do novo sistema é feita por paraCliente().
 */
class CarteiraV4
{
    public static function lojasDoHtml(string $arquivo): array
    {
        $html = (string) file_get_contents($arquivo);
        if (!preg_match('#<script type="application/json" id="leadData">(.*?)</script>#s', $html, $m)) {
            throw new \RuntimeException('Não encontrei a lista de lojas (leadData) no arquivo da Carteira V4.');
        }
        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Backup JSON da V4: {"app":"perfeita-alquimia-carteira","state":{"updates":{id:{...}},"customLeads":[...]}}
     * As alterações (updates) só fazem sentido junto com as lojas originais, então juntamos as duas coisas.
     */
    public static function lojasDoBackup(string $json, array $lojasOriginais = []): array
    {
        $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (($dados['app'] ?? '') !== 'perfeita-alquimia-carteira' || !isset($dados['state'])) {
            throw new \RuntimeException('Este arquivo não é um Backup JSON da Carteira V4.');
        }
        $lojas = [];
        foreach ([...$lojasOriginais, ...($dados['state']['customLeads'] ?? [])] as $loja) {
            $alteracao = $dados['state']['updates'][(string) $loja['id']] ?? null;
            $lojas[] = $alteracao ? array_merge($loja, $alteracao) : $loja;
        }
        return $lojas;
    }

    /**
     * Converte uma loja da V4 para os campos do novo cadastro de clientes.
     * Observação: o campo "owner" (responsável) da V4 é texto livre com nome de pessoa e NÃO é importado
     * como regra — o responsável é definido pela função (veja a importação).
     */
    public static function paraCliente(array $l): array
    {
        $observacoes = trim(implode("\n", array_filter([
            $l['notes'] ?? '',
            !empty($l['owner']) ? 'Responsável na carteira antiga: ' . $l['owner'] : '',
        ])));
        return [
            'razao_social'      => Normalizar::texto($l['name'] ?? '', 200),
            'cnpj'              => Normalizar::cnpj($l['cnpj'] ?? ''),
            'endereco'          => Normalizar::texto($l['address'] ?? ''),
            'cidade'            => Normalizar::texto($l['city'] ?? '', 120),
            'uf'                => strtoupper(Normalizar::texto($l['uf'] ?? 'RS', 2) ?? 'RS'),
            'segmento'          => Normalizar::segmento($l['segment'] ?? ''),
            'segmento_original' => Normalizar::texto($l['segment'] ?? '', 120),
            'telefone'          => Normalizar::telefone($l['phone'] ?? ''),
            'whatsapp'          => Normalizar::telefone($l['whatsapp'] ?? ''),
            'email'             => Normalizar::texto($l['email'] ?? '', 160),
            'instagram'         => Normalizar::texto($l['instagram'] ?? '', 120),
            'facebook'          => Normalizar::texto($l['facebook'] ?? ''),
            'site'              => Normalizar::texto($l['website'] ?? ''),
            'mix_produtos'      => Normalizar::texto($l['mix'] ?? '', 2000),
            'fonte_url'         => Normalizar::texto($l['source'] ?? '', 500),
            'validacao'         => Normalizar::texto($l['validation'] ?? ''),
            'origem_detalhe'    => Normalizar::texto($l['origin'] ?? '', 160),
            'etapa'             => Normalizar::texto($l['stage'] ?? '', 80),
            'prioridade'        => Normalizar::texto($l['priority'] ?? '', 10),
            'contatado'         => !empty($l['contacted']) ? 1 : 0,
            'ultimo_contato_em' => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($l['lastContact'] ?? '')) ? substr($l['lastContact'], 0, 10) : null,
            'observacoes'       => $observacoes !== '' ? $observacoes : null,
            'historico'         => $l['history'] ?? [],
        ];
    }
}
