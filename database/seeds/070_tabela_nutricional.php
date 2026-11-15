<?php
/**
 * Tabela nutricional (V11.7) — ESSENCIAL (uma vez só): traz para a ficha técnica as tabelas que já estavam nos
 * produtos (dados da Academia): porção, porções por embalagem, nutrientes e nota. O %VD é recalculado pelos
 * valores diários de referência atuais da Anvisa (IN 75/2020); quem precisar ajusta à mão na ficha técnica.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'nutricional:importar-academia'")) {
    foreach (Banco::todos("SELECT id, ficha_json FROM produtos WHERE ficha_json IS NOT NULL AND id NOT IN (SELECT produto_id FROM produto_nutricional)") as $p) {
        $f = json_decode((string) $p['ficha_json'], true) ?: [];
        if (empty($f['nutrition']) || !is_array($f['nutrition'])) {
            continue;
        }
        $linhas = [];
        foreach ($f['nutrition'] as $n) {
            [$nome, $qtd] = [trim((string) ($n[0] ?? '')), trim((string) ($n[1] ?? ''))];
            if ($nome === '') {
                continue;
            }
            preg_match('/^([\d.,]+)\s*(.*)$/u', $qtd, $m);
            $linhas[] = ['nutriente' => $nome, 'quantidade' => $m[1] ?? $qtd, 'unidade' => trim($m[2] ?? ''), 'vd' => ''];
        }
        $porcao = preg_replace('/\s*[·•-]\s*(.+)$/u', ' ($1)', trim((string) ($f['serving'] ?? '')));
        Banco::inserir('produto_nutricional', [
            'produto_id' => $p['id'], 'porcoes_embalagem' => preg_replace('/\D+/', '', (string) ($f['servings'] ?? '')) ?: null,
            'porcao' => $porcao ?: null, 'linhas' => json_encode($linhas, JSON_UNESCAPED_UNICODE),
            'nota' => trim((string) ($f['tableNote'] ?? '')) ?: null, 'atualizado_em' => date('Y-m-d H:i:s'),
        ]);
    }
    Banco::inserir('migracoes', ['arquivo' => 'nutricional:importar-academia']);
}
return null;
