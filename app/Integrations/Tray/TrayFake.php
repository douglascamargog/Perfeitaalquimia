<?php
namespace App\Integrations\Tray;

use App\Core\Banco;

/**
 * Loja virtual SIMULADA (demonstração). Números determinísticos (sempre os mesmos para a mesma data),
 * com sazonalidade: fim de semana mais fraco, pico na Black Friday e em dezembro, janeiro mais calmo.
 * Os produtos são os do cadastro da Academia (mesmos nomes do site).
 */
class TrayFake implements EcommerceInterface
{
    /** Peso de cada mês (1 = normal) */
    private const SAZONAL = [1 => .8, 2 => .85, 3 => 1, 4 => 1, 5 => 1.15, 6 => 1.05, 7 => 1.1, 8 => 1.05, 9 => 1, 10 => 1.1, 11 => 1.45, 12 => 1.35];

    public function nome(): string
    {
        return 'fake';
    }

    public function resumoPorDia(string $desde, string $ate): array
    {
        $dias = [];
        $ate = min($ate, date('Y-m-d')); // não inventa o futuro
        for ($d = $desde; $d <= $ate; $d = date('Y-m-d', strtotime("$d +1 day"))) {
            $semente = crc32("tray-$d");
            $semana = (int) date('N', strtotime($d)) >= 6 ? .7 : 1;
            $pico = substr($d, 5) === $this->blackFriday((int) substr($d, 0, 4)) ? 4.2 : 1;
            $pedidos = (int) round((14 + $semente % 9) * self::SAZONAL[(int) substr($d, 5, 2)] * $semana * $pico * $this->crescimento($d));
            if ($d === date('Y-m-d')) {
                $pedidos = (int) round($pedidos * min(1, (int) date('G') / 22)); // hoje: só o que já entrou
            }
            $ticket = 148 + ($semente >> 5) % 45 + ($pico > 1 ? -18 : 0);
            $dias[] = [
                'data' => $d, 'pedidos' => $pedidos, 'faturamento' => round($pedidos * $ticket, 2),
                'visitas' => (int) round($pedidos / (0.016 + (($semente >> 9) % 8) / 1000)), 'novos_clientes' => (int) round($pedidos * (.32 + (($semente >> 12) % 15) / 100)),
            ];
        }
        return $dias;
    }

    public function produtosMaisVendidos(string $mes, int $limite = 10): array
    {
        $produtos = Banco::todos('SELECT id, slug, nome FROM produtos WHERE ativo = 1 ORDER BY id LIMIT 60');
        if (!$produtos) {
            return [];
        }
        $total = array_sum(array_column($this->resumoPorDia("$mes-01", date('Y-m-t', strtotime("$mes-01"))), 'faturamento'));
        $pesos = [];
        foreach ($produtos as $p) {
            $pesos[$p['slug']] = 1 + crc32($p['slug'] . $mes) % 100;
        }
        arsort($pesos);
        $soma = array_sum($pesos);
        $nomes = array_column($produtos, 'nome', 'slug');
        $lista = [];
        foreach (array_slice($pesos, 0, $limite, true) as $slug => $peso) {
            $fat = round($total * $peso / $soma * 2.2, 2); // os 10 primeiros concentram boa parte das vendas
            $preco = 39.9 + crc32($slug) % 90;
            $lista[] = ['produto_ref' => $slug, 'nome' => $nomes[$slug], 'quantidade' => max(1, (int) round($fat / $preco)), 'faturamento' => $fat];
        }
        return $lista;
    }

    public function pedidosRecentes(int $limite = 10): array
    {
        $cidades = [['Porto Alegre', 'RS'], ['Canoas', 'RS'], ['Caxias do Sul', 'RS'], ['São Paulo', 'SP'], ['Curitiba', 'PR'], ['Florianópolis', 'SC'],
            ['Belo Horizonte', 'MG'], ['Rio de Janeiro', 'RJ'], ['Novo Hamburgo', 'RS'], ['Campinas', 'SP']];
        $situacoes = ['Aguardando pagamento', 'Pago', 'Pago', 'Em separação', 'Enviado', 'Enviado', 'Entregue'];
        $pedidos = [];
        $base = (int) (strtotime('today') / 3600);
        for ($i = 0; $i < $limite; $i++) {
            $s = crc32('pedido' . ($base - $i * 3));
            [$cidade, $uf] = $cidades[$s % count($cidades)];
            $pedidos[] = ['numero' => (string) (48000 + intdiv($base, 3) % 9000 - $i), 'data' => date('Y-m-d H:i', strtotime('-' . ($i * 97 + $s % 60) . ' minutes')),
                'valor' => round(89 + $s % 420 + ($s >> 8) % 100 / 100, 2), 'situacao' => $situacoes[min(count($situacoes) - 1, intdiv($i, 2) + $s % 2)], 'cidade' => $cidade, 'uf' => $uf];
        }
        return $pedidos;
    }

    public function testarConexao(): array
    {
        return [true, 'Loja simulada (demonstração).'];
    }

    /** Black Friday = dia seguinte à 4ª quinta-feira de novembro (MM-DD) */
    private function blackFriday(int $ano): string
    {
        return date('m-d', strtotime("fourth thursday of november $ano +1 day"));
    }

    /** A loja cresce ~1,5% ao mês (para o gráfico de 12 meses ter tendência) */
    private function crescimento(string $data): float
    {
        $meses = (int) ((strtotime('today') - strtotime($data)) / (30 * 86400));
        return pow(0.985, $meses);
    }
}
