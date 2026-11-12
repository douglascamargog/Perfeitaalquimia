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
        return $this->filtrarPorData($this->ler('produtos'), $alteradoDesde);
    }

    public function listarPrecos(?string $alteradoDesde = null): array
    {
        return $this->ler('precos');
    }

    public function listarEstoque(): array
    {
        return $this->ler('estoque');
    }

    public function listarClientes(?string $alteradoDesde = null): array
    {
        return $this->filtrarPorData($this->ler('clientes'), $alteradoDesde);
    }

    public function listarTitulos(?string $alteradoDesde = null): array
    {
        return $this->ler('titulos');
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

    public function consultarPedido(string $omieCodigo): array
    {
        foreach ($this->pedidos() as $p) {
            if ($p['omie_codigo'] === $omieCodigo) {
                return ['omie_codigo' => $p['omie_codigo'], 'etapa' => $p['etapa'], 'faturado' => !empty($p['numero_nf']), 'numero_nf' => $p['numero_nf']];
            }
        }
        throw new \RuntimeException("Pedido $omieCodigo não encontrado no Omie (simulado).");
    }

    /** Só no Fake: simula o faturamento de um pedido (usado na demonstração do fluxo completo) */
    public function simularFaturamento(string $omieCodigo): void
    {
        $pedidos = $this->pedidos();
        foreach ($pedidos as &$p) {
            if ($p['omie_codigo'] === $omieCodigo) {
                $p['etapa'] = '60';
                $p['numero_nf'] = (string) random_int(10000, 99999);
                $p['faturado_em'] = date('Y-m-d H:i:s');
            }
        }
        $this->gravarPedidos($pedidos);
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
