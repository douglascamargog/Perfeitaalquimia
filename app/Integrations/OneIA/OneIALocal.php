<?php
namespace App\Integrations\OneIA;

use App\Services\OneFerramentas as F;

/**
 * "Pergunte ao One" SEM IA (padrão, e quando a IA está desligada ou sem chave).
 * Entende alguns assuntos por palavras-chave e responde com as consultas do próprio sistema.
 * Nada sai do servidor e não tem custo.
 */
class OneIALocal implements OneIAInterface
{
    public function nome(): string
    {
        return 'local';
    }

    public function responder(string $pergunta, array $historico): array
    {
        $p = mb_strtolower($pergunta);
        $consultas = [];
        if (preg_match('/\b(meta|vendi|vendido|falta|quanto (eu )?vendi|meus n[uú]meros|atrasad|recompra)\b/u', $p)) {
            $n = F::meusNumeros();
            $consultas[] = 'meus_numeros';
            $l = [];
            if (isset($n['meta_do_mes'])) {
                $l[] = '- Meta do mês: **' . $n['meta_do_mes'] . '** · vendido: **' . $n['vendido_no_mes'] . '**' . ($n['falta'] && !$n['meta_batida'] ? ' · faltam ' . $n['falta'] : '') . ($n['meta_batida'] ? ' 🎯 meta batida!' : '');
            }
            if (isset($n['precisa_por_dia_util']) && $n['precisa_por_dia_util'] && empty($n['meta_batida'])) {
                $l[] = '- Para bater a meta: cerca de ' . $n['precisa_por_dia_util'] . ' por dia útil';
            }
            if (isset($n['retornos_atrasados'])) {
                $l[] = '- Agenda: ' . $n['retornos_atrasados'] . ' atrasado(s) e ' . $n['retornos_hoje'] . ' para hoje ([abrir a Agenda](/agenda))';
            }
            if (isset($n['clientes_na_recompra'])) {
                $l[] = '- Recompra: ' . $n['clientes_na_recompra'] . ' cliente(s) passaram do prazo ([ver](/recompra))';
            }
            $texto = $l ? "Seus números agora:\n" . implode("\n", $l) : 'Não encontrei números para mostrar para o seu perfil.';
        } elseif (preg_match('/campanha|promo[cç]/u', $p)) {
            $c = F::campanhas()['campanhas'];
            $consultas[] = 'campanhas_ativas';
            $texto = $c ? "Campanhas no ar:\n" . implode("\n", array_map(fn($x) => '- **' . $x['nome'] . '** (' . $x['periodo'] . ', ' . $x['para_quem'] . ')' . ($x['faixas'] ? ': ' . implode('; ', $x['faixas']) : ''), $c))
                . "\n\nDetalhes em [Campanhas](/campanhas)." : 'Nenhuma campanha no ar hoje.';
        } else {
            $r = F::ajudaSistema($pergunta)['resultados'];
            $consultas[] = 'ajuda_sistema';
            if ($r) {
                $a = $r[0];
                $texto = '**' . $a['titulo'] . "**\n" . mb_strimwidth($a['resumo'], 0, 420, '…') . "\n\n[Abrir](" . $a['link'] . ')';
                if (count($r) > 1) {
                    $texto .= "\n\nVeja também:\n" . implode("\n", array_map(fn($x) => '- [' . $x['titulo'] . '](' . $x['link'] . ')', array_slice($r, 1, 2)));
                }
            } else {
                $texto = 'Não achei isso no treinamento. Tente com outras palavras, ou veja o seu [Treinamento](/treinamento). Para entender a tela em que você está, clique em "O que dá pra fazer aqui?".';
            }
        }
        return ['texto' => $texto, 'consultas' => $consultas, 'tokens_entrada' => 0, 'tokens_saida' => 0, 'custo' => 0.0, 'situacao' => 'ok'];
    }
}
