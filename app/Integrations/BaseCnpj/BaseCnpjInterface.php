<?php
namespace App\Integrations\BaseCnpj;

/**
 * Base pública de CNPJs para a prospecção (empresas ativas dos CNAEs do nosso público).
 *
 * Protótipo: BaseCnpjLocal (tabela prospeccao_empresas com a amostra simulada do RS).
 * Fase posterior: a mesma tabela recebe a base aberta da Receita Federal (importação filtrada por UF + CNAE),
 * ou outra implementação desta interface consulta um serviço externo — o resto do sistema não muda.
 */
interface BaseCnpjInterface
{
    /**
     * Empresas que atendem aos filtros, paginadas.
     * $f: uf, cidade, bairro, cnae (só dígitos), meses (abertas nos últimos X meses), q (nome/CNPJ),
     *     esconder_carteira (bool), cnaes (lista de CNAEs permitidos)
     * Cada item: cnpj, razao_social, nome_fantasia, cnae, cnae_descricao, abertura, endereco, bairro, cidade, uf, telefone, email, porte
     * Retorna ['itens' => [...], 'total' => int]
     */
    public function buscar(array $f, int $pagina, int $porPagina): array;

    /** Cidades da UF com a quantidade de empresas: [['cidade' => ..., 'empresas' => n, 'na_carteira' => n], ...] */
    public function cidades(string $uf, array $cnaes): array;

    /** UFs disponíveis na base */
    public function ufs(): array;
}
