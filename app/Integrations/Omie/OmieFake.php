<?php
namespace App\Integrations\Omie;

/**
 * Omie SIMULADO para o protótipo.
 *
 * - Lê os dados de arquivos JSON em database/seeds/omie_fake/ (produtos.json, precos.json, ...).
 *   Se um arquivo ainda não existe, devolve lista vazia.
 * - Pedidos enviados são guardados em storage/omie_fake/pedidos.json, como se tivessem entrado no Omie.
 *
 * Tem exatamente os mesmos métodos do OmieClient (a API real).
 */
class OmieFake implements ErpInterface
{
    private string $pastaDados;
    private string $arquivoPedidos;

    public function __construct()
    {
        $this->pastaDados = RAIZ . '/database/seeds/omie_fake';
        $this->arquivoPedidos = RAIZ . '/storage/omie_fake/pedidos.json';
    }

    public function listarProdutos(?string $alteradoDesde = null): array
    {
        $doArquivo = $this->ler('produtos');
        if ($doArquivo) {
            return $this->filtrarPorData($doArquivo, $alteradoDesde);
        }
        // Sem arquivo: os produtos do próprio cadastro (código do "Omie" = slug)
        return array_map(fn($p) => ['omie_codigo' => $p['slug'], 'sku' => strtoupper(substr($p['slug'], 0, 12)), 'nome' => $p['nome'],
            'unidade' => 'UN', 'ativo' => (bool) $p['ativo'], 'alterado_em' => $p['atualizado_em']],
            \App\Core\Banco::todos('SELECT slug, nome, ativo, atualizado_em FROM produtos ORDER BY id'));
    }

    /**
     * Preços simulados, sempre os mesmos para o mesmo produto:
     * Lojista = base · Distribuidor = 18% menos · Terceirização = 30% menos. ~3% dos produtos ficam sem preço (como acontece no ERP).
     */
    public function listarPrecos(?string $alteradoDesde = null): array
    {
        $doArquivo = $this->ler('precos');
        if ($doArquivo) {
            return $doArquivo;
        }
        $precos = [];
        foreach (\App\Core\Banco::todos('SELECT slug FROM produtos WHERE ativo = 1 ORDER BY id') as ['slug' => $slug]) {
            $semente = crc32("preco-$slug");
            if ($semente % 33 === 0) {
                continue;
            }
            $base = 24.9 + ($semente % 110) + (($semente >> 8) % 10) / 10;
            foreach (['lojista' => 1, 'distribuidor' => .82, 'terceirizacao' => .70] as $tabela => $fator) {
                $precos[] = ['omie_codigo_produto' => $slug, 'tabela' => $tabela, 'preco' => round($base * $fator, 2)];
            }
        }
        return $precos;
    }

    public function listarEstoque(): array
    {
        $doArquivo = $this->ler('estoque');
        if ($doArquivo) {
            return $doArquivo;
        }
        return array_map(fn($p) => ['omie_codigo_produto' => $p['slug'], 'quantidade' => crc32("estoque-{$p['slug']}") % 400],
            \App\Core\Banco::todos('SELECT slug FROM produtos WHERE ativo = 1 ORDER BY id'));
    }

    public function listarClientes(?string $alteradoDesde = null): array
    {
        return $this->filtrarPorData($this->ler('clientes'), $alteradoDesde);
    }

    /**
     * Títulos a receber simulados.
     * Se existir database/seeds/omie_fake/titulos.json, usa o arquivo. Senão, inventa títulos de forma
     * DETERMINÍSTICA (sempre os mesmos) para ~25% dos clientes (tipo cliente) com CNPJ: alguns vencidos, alguns pagos.
     */
    public function listarTitulos(?string $alteradoDesde = null): array
    {
        $doArquivo = $this->ler('titulos');
        if ($doArquivo) {
            return $this->filtrarPorData($doArquivo, $alteradoDesde);
        }
        $titulos = [];
        $hoje = new \DateTimeImmutable('today');
        $cnpjs = \App\Core\Banco::todos("SELECT cnpj FROM clientes WHERE tipo = 'cliente' AND cnpj IS NOT NULL AND excluido_em IS NULL ORDER BY id");
        foreach ($cnpjs as ['cnpj' => $cnpj]) {
            $semente = crc32($cnpj);
            if ($semente % 4 !== 0) {
                continue;
            }
            $parcelas = 1 + $semente % 3;
            $atrasoBase = 3 + ($semente >> 4) % 140;                 // 3 a 142 dias
            $valor = 280 + (($semente >> 8) % 45) * 97.35;           // R$ 280 a ~R$ 4.600
            $nota = 10000 + ($semente >> 12) % 89999;
            for ($p = 1; $p <= $parcelas; $p++) {
                $vencimento = $hoje->modify('-' . max(1, $atrasoBase - ($p - 1) * 30) . ' days');
                $pago = ($semente >> (16 + $p)) % 5 === 0;           // ~20% das parcelas já pagas
                $titulos[] = [
                    'omie_codigo' => "FAKE-$cnpj-$p",
                    'cnpj'        => $cnpj,
                    'documento'   => "NF $nota",
                    'parcela'     => "$p/$parcelas",
                    'valor'       => round($valor / $parcelas, 2),
                    'vencimento'  => $vencimento->format('Y-m-d'),
                    'pago_em'     => $pago ? $vencimento->modify('+2 days')->format('Y-m-d') : null,
                    'situacao'    => $pago ? 'pago' : 'atrasado',
                    'alterado_em' => $vencimento->format('Y-m-d H:i:s'),
                ];
            }
        }
        return $this->filtrarPorData($titulos, $alteradoDesde);
    }

    public function listarFaturamento(?string $alteradoDesde = null): array
    {
        // Notas simuladas do arquivo + notas dos pedidos "faturados" nesta demonstração
        $notas = $this->ler('faturamento');
        foreach ($this->pedidos() as $p) {
            if (!empty($p['numero_nf'])) {
                $notas[] = ['omie_codigo' => 'NF-' . $p['omie_codigo'], 'omie_codigo_pedido' => $p['omie_codigo'], 'cnpj' => $p['cnpj'],
                            'numero_nf' => $p['numero_nf'], 'valor' => $p['total'], 'emitido_em' => $p['faturado_em']];
            }
        }
        return $notas;
    }

    public function enviarPedido(array $pedido): array
    {
        $pedidos = $this->pedidos();

        // Mesmo comportamento esperado do Omie real: o mesmo pedido (uuid) não entra duas vezes
        foreach ($pedidos as $existente) {
            if ($existente['uuid'] === $pedido['uuid']) {
                return ['omie_codigo' => $existente['omie_codigo'], 'numero' => $existente['numero'], 'etapa' => $existente['etapa']];
            }
        }

        $numero = count($pedidos) + 1001;
        $total = 0;
        foreach ($pedido['itens'] ?? [] as $item) {
            $total += $item['quantidade'] * $item['preco_unitario'];
        }
        $novo = [
            'uuid'        => $pedido['uuid'],
            'omie_codigo' => 'FAKE-' . $numero,
            'numero'      => (string) $numero,
            'etapa'       => (string) \App\Core\Config::get('pedido.omie_etapa_inicial', '10'),
            'cnpj'        => $pedido['cnpj'] ?? '',
            'total'       => round($total, 2),
            'recebido_em' => date('Y-m-d H:i:s'),
            'numero_nf'   => null,
            'faturado_em' => null,
        ];
        $pedidos[] = $novo;
        $this->gravarPedidos($pedidos);
        return ['omie_codigo' => $novo['omie_codigo'], 'numero' => $novo['numero'], 'etapa' => $novo['etapa']];
    }

    public function aprovarPedido(string $omieCodigo): array
    {
        $pedidos = $this->pedidos();
        foreach ($pedidos as &$p) {
            if ($p['omie_codigo'] === $omieCodigo) {
                $p['etapa'] = '20'; // "aprovado" no Omie simulado
                $this->gravarPedidos($pedidos);
                return ['omie_codigo' => $omieCodigo, 'etapa' => '20'];
            }
        }
        throw new \RuntimeException("Pedido $omieCodigo não encontrado no Omie (simulado).");
    }

    /** SÓ NA DEMONSTRAÇÃO: emite a "nota fiscal" de um pedido aprovado (no Omie real quem fatura é o Financeiro, no Omie) */
    public function simularFaturamento(string $omieCodigo): string
    {
        $pedidos = $this->pedidos();
        foreach ($pedidos as &$p) {
            if ($p['omie_codigo'] === $omieCodigo) {
                $p['etapa'] = '60';
                $p['numero_nf'] ??= (string) (5000 + count(array_filter($pedidos, fn($x) => !empty($x['numero_nf']))) + 1);
                $p['faturado_em'] ??= date('Y-m-d H:i:s');
                $this->gravarPedidos($pedidos);
                return $p['numero_nf'];
            }
        }
        throw new \RuntimeException("Pedido $omieCodigo não encontrado no Omie (simulado).");
    }

    public function consultarPedido(string $omieCodigo): array
    {
        foreach ($this->pedidos() as $p) {
            if ($p['omie_codigo'] === $omieCodigo) {
                return ['omie_codigo' => $p['omie_codigo'], 'etapa' => $p['etapa'], 'faturado' => !empty($p['numero_nf']), 'numero_nf' => $p['numero_nf']];
            }
        }
        throw new \RuntimeException("Pedido $omieCodigo não encontrado no Omie (simulado).");
    }

    public function testarConexao(): array
    {
        return ['ok' => true, 'mensagem' => 'Usando dados simulados (OmieFake). Nenhuma conexão real com o Omie.'];
    }

    // ---------------- auxiliares ----------------

    private function ler(string $nome): array
    {
        $arquivo = "{$this->pastaDados}/$nome.json";
        return is_file($arquivo) ? (json_decode(file_get_contents($arquivo), true) ?: []) : [];
    }

    private function filtrarPorData(array $lista, ?string $desde): array
    {
        if ($desde === null) {
            return $lista;
        }
        return array_values(array_filter($lista, fn($item) => ($item['alterado_em'] ?? '') > $desde));
    }

    private function pedidos(): array
    {
        return is_file($this->arquivoPedidos) ? (json_decode(file_get_contents($this->arquivoPedidos), true) ?: []) : [];
    }

    private function gravarPedidos(array $pedidos): void
    {
        if (!is_dir(dirname($this->arquivoPedidos))) {
            mkdir(dirname($this->arquivoPedidos), 0775, true);
        }
        file_put_contents($this->arquivoPedidos, json_encode($pedidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
