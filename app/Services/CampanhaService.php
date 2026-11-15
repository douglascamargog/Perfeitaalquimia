<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;

/**
 * Campanhas comerciais (Comercial › Campanhas).
 *
 * Uma campanha vale num período (mês, semana ou datas livres) para um PÚBLICO (todos, tabela de preço,
 * região, UF ou setor) e pode dar:
 *  - preço promocional em produtos escolhidos (% de desconto, vale para todas as tabelas);
 *  - faixas por valor do pedido: "a partir de R$ X" → desconto %, brinde ou frete grátis.
 *
 * Regras (as mesmas no catálogo, no pedido da equipe e no app):
 *  - a faixa usa o total do pedido JÁ com os preços promocionais (antes dos descontos da vendedora);
 *  - com duas campanhas ao mesmo tempo o cliente ganha o MELHOR de cada tipo: o maior desconto,
 *    todos os brindes conquistados e o frete grátis se alguma der (descontos não se somam);
 *  - produto em duas campanhas: vale o maior desconto;
 *  - campanha que não soma com a vendedora: se a vendedora der desconto, o desconto da faixa não entra.
 * Tudo é calculado no servidor; a tela só mostra.
 */
class CampanhaService
{
    public const PUBLICOS = [
        'todos'  => 'Todos os clientes',
        'tabela' => 'Tabela de preço',
        'regiao' => 'Região',
        'uf'     => 'Estado (UF)',
        'setor'  => 'Setor de venda',
    ];
    public const TIPOS_FAIXA = ['desconto' => 'Desconto', 'brinde' => 'Brinde', 'frete' => 'Frete grátis'];

    /** Combinações prontas de cor para o catálogo: [nome, primária, destaque, tema] */
    public const VISUAIS = [
        'empresa'  => ['Perfeita Alquimia', '', '', 'claro'],
        'noite'    => ['Verde noite', '#0b2a1f', '#2cdf82', 'escuro'],
        'menta'    => ['Menta', '#025150', '#5cd4b6', 'claro'],
        'outono'   => ['Outono', '#5a3825', '#e8a33d', 'claro'],
        'rosa'     => ['Rosa (outubro)', '#8a1f4f', '#ff8fbf', 'claro'],
        'azul'     => ['Azul (novembro)', '#0b3c5d', '#5bc0eb', 'claro'],
        'natal'    => ['Natal', '#0f3d24', '#e23b3b', 'escuro'],
    ];

    private static ?array $hoje = null;

    // ------------------------------------------------------------------
    // Leitura
    // ------------------------------------------------------------------

    /** Campanhas ligadas e dentro do período hoje (com faixas e produtos) */
    public static function ativasHoje(): array
    {
        if (self::$hoje !== null) {
            return self::$hoje;
        }
        $lista = Banco::todos('SELECT c.*, m.arquivo AS banner FROM campanhas c LEFT JOIN midias m ON m.id = c.banner_midia_id
            WHERE c.ativa = 1 AND c.inicio <= CURDATE() AND c.fim >= CURDATE() ORDER BY c.ordem, c.fim, c.id');
        return self::$hoje = array_map([self::class, 'completar'], $lista);
    }

    /** Esquece o que foi lido (depois de salvar, e nos testes) */
    public static function esquecer(): void
    {
        self::$hoje = null;
    }

    public static function completar(array $c): array
    {
        $c['faixas'] = Banco::todos('SELECT f.*, m.arquivo AS brinde_imagem FROM campanha_faixas f LEFT JOIN midias m ON m.id = f.brinde_midia_id
            WHERE f.campanha_id = ? ORDER BY f.a_partir_de, f.id', [$c['id']]);
        $c['produtos'] = Banco::todos('SELECT cp.*, p.nome, p.codigo, p.slug, p.embalagem, m.arquivo AS imagem FROM campanha_produtos cp
            JOIN produtos p ON p.id = cp.produto_id LEFT JOIN midias m ON m.id = p.imagem_id
            WHERE cp.campanha_id = ? ORDER BY cp.destaque DESC, cp.desconto_pct DESC, p.nome', [$c['id']]);
        return $c;
    }

    /** Campanhas de hoje que valem para este cliente (visitante: só as abertas a todos) */
    public static function paraCliente(?array $cliente): array
    {
        return array_values(array_filter(self::ativasHoje(), fn($c) => self::atende($c, $cliente)));
    }

    /** A campanha vale para este cliente? */
    public static function atende(array $c, ?array $cliente): bool
    {
        if ($c['publico'] === 'todos') {
            return true;
        }
        if (!$cliente) {
            return false;
        }
        $valores = array_filter(array_map('trim', explode(',', (string) $c['publico_valores'])), 'strlen');
        return match ($c['publico']) {
            'tabela' => in_array((string) PedidoService::tabelaDoCliente($cliente)['id'], $valores, true),
            'regiao' => in_array((string) ($cliente['regiao_id'] ?? self::regiaoDaUf($cliente['uf'] ?? null)), $valores, true),
            'uf'     => in_array(strtoupper((string) ($cliente['uf'] ?? '')), array_map('strtoupper', $valores), true),
            'setor'  => in_array((string) ($cliente['funcao_id'] ?? ''), $valores, true),
            default  => false,
        };
    }

    private static function regiaoDaUf(?string $uf): ?int
    {
        $id = $uf ? Banco::valor('SELECT regiao_id FROM regiao_ufs WHERE uf = ?', [$uf]) : null;
        return $id ? (int) $id : null;
    }

    /** Preço promocional de cada produto: [produto_id => ['pct', 'campanha_id', 'campanha', 'destaque']] (o maior desconto) */
    public static function promocoes(array $campanhas): array
    {
        $promo = [];
        foreach ($campanhas as $c) {
            foreach ($c['produtos'] as $p) {
                $pid = (int) $p['produto_id'];
                $pct = (float) $p['desconto_pct'];
                if ($pct > 0 && (!isset($promo[$pid]) || $pct > $promo[$pid]['pct'])) {
                    $promo[$pid] = ['pct' => $pct, 'campanha_id' => (int) $c['id'], 'campanha' => $c['titulo'] ?: $c['nome'], 'destaque' => (bool) $p['destaque']];
                }
            }
        }
        return $promo;
    }

    /** Preço com o desconto promocional */
    public static function precoPromo(float $preco, float $pct): float
    {
        return round($preco * (1 - $pct / 100), 2);
    }

    /**
     * O que as campanhas dão para um pedido deste valor, e o que falta para a próxima faixa.
     * Devolve ['desconto_pct', 'desconto_de' (campanha), 'brindes' => [[texto, imagem, campanha]], 'frete' => bool,
     *          'proximas' => [[falta, a_partir_de, tipo, texto, campanha]] (da mais perto para a mais longe), 'nomes' => [...]]
     */
    public static function beneficios(array $campanhas, float $valor): array
    {
        $r = ['desconto_pct' => 0.0, 'desconto_de' => null, 'brindes' => [], 'frete' => false, 'proximas' => [], 'nomes' => []];
        $futuras = [];
        foreach ($campanhas as $c) {
            $nome = $c['titulo'] ?: $c['nome'];
            $deu = false;
            $brinde = null;
            foreach ($c['faixas'] as $f) {
                $ok = $valor + 0.0001 >= (float) $f['a_partir_de'];
                if ($f['tipo'] === 'desconto') {
                    if ($ok && (float) $f['desconto_pct'] > $r['desconto_pct']) {
                        $r['desconto_pct'] = (float) $f['desconto_pct'];
                        $r['desconto_de'] = $nome;
                    }
                } elseif ($f['tipo'] === 'brinde' && $ok) {
                    $brinde = $f; // a faixa de brinde mais alta conquistada nesta campanha
                } elseif ($f['tipo'] === 'frete' && $ok) {
                    $r['frete'] = true;
                }
                $deu = $deu || $ok;
                if (!$ok) {
                    $futuras[] = $f + ['campanha' => $nome];
                }
            }
            if ($brinde) {
                $r['brindes'][] = ['texto' => $brinde['brinde'], 'imagem' => $brinde['brinde_imagem'] ?? null, 'campanha' => $nome];
            }
            if ($deu) {
                $r['nomes'][] = $nome;
            }
        }
        // Próximas faixas que ainda melhoram o pedido
        foreach ($futuras as $f) {
            $falta = round((float) $f['a_partir_de'] - $valor, 2);
            if ($f['tipo'] === 'desconto' && (float) $f['desconto_pct'] <= $r['desconto_pct']) {
                continue;
            }
            if ($f['tipo'] === 'frete' && $r['frete']) {
                continue;
            }
            $r['proximas'][] = ['falta' => $falta, 'a_partir_de' => (float) $f['a_partir_de'], 'tipo' => $f['tipo'], 'texto' => self::textoFaixa($f), 'campanha' => $f['campanha']];
        }
        usort($r['proximas'], fn($a, $b) => $a['falta'] <=> $b['falta']);
        // de cada tipo, só a mais perto (para a mensagem não virar uma lista)
        $vistos = [];
        $r['proximas'] = array_values(array_filter($r['proximas'], function ($p) use (&$vistos) {
            $k = $p['tipo'] === 'brinde' ? 'brinde:' . $p['texto'] : $p['tipo'];
            if (isset($vistos[$k])) {
                return false;
            }
            return $vistos[$k] = true;
        }));
        $r['nomes'] = array_values(array_unique($r['nomes']));
        return $r;
    }

    /** "10% de desconto", "Bicicleta de brinde", "frete grátis" */
    public static function textoFaixa(array $f): string
    {
        return match ($f['tipo']) {
            'desconto' => self::pct((float) $f['desconto_pct']) . ' de desconto',
            'brinde'   => trim((string) $f['brinde']) . ' de brinde',
            'frete'    => 'frete grátis',
            default    => '',
        };
    }

    /** "Faltam R$ 200,00 para você ganhar 10% de desconto" (ou null) */
    public static function mensagem(array $b, bool $voce = true): ?string
    {
        $p = $b['proximas'][0] ?? null;
        return $p ? 'Faltam ' . dinheiro($p['falta']) . ' para ' . ($voce ? 'você ganhar ' : 'ganhar ') . $p['texto'] : null;
    }

    /** Resumo do que foi conquistado: "10% de desconto · Bicicleta · frete grátis" */
    public static function conquistas(array $b): array
    {
        $l = [];
        if ($b['desconto_pct'] > 0) {
            $l[] = self::pct($b['desconto_pct']) . ' de desconto';
        }
        foreach ($b['brindes'] as $x) {
            $l[] = '🎁 ' . $x['texto'];
        }
        if ($b['frete']) {
            $l[] = 'frete grátis';
        }
        return $l;
    }

    public static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',') . '%';
    }

    /** Período em texto: "01/03 a 31/03/2026" */
    public static function periodo(array $c): string
    {
        $i = strtotime($c['inicio']);
        $f = strtotime($c['fim']);
        return date('Y', $i) === date('Y', $f) ? date('d/m', $i) . ' a ' . date('d/m/Y', $f) : date('d/m/Y', $i) . ' a ' . date('d/m/Y', $f);
    }

    /** Situação: no_ar | agendada | encerrada | desligada */
    public static function situacao(array $c): string
    {
        $hoje = date('Y-m-d');
        if (!(int) $c['ativa']) {
            return 'desligada';
        }
        return $c['fim'] < $hoje ? 'encerrada' : ($c['inicio'] > $hoje ? 'agendada' : 'no_ar');
    }

    /** Público em texto: "Tabela: Distribuidor" */
    public static function publicoTexto(array $c): string
    {
        $valores = array_filter(array_map('trim', explode(',', (string) $c['publico_valores'])), 'strlen');
        if ($c['publico'] === 'todos' || !$valores) {
            return self::PUBLICOS['todos'];
        }
        $marcas = implode(',', array_fill(0, count($valores), '?'));
        $coluna = fn(string $sql) => array_column(Banco::todos($sql, array_values($valores)), 'nome');
        $nomes = match ($c['publico']) {
            'tabela' => $coluna("SELECT nome FROM tabelas_preco WHERE id IN ($marcas)"),
            'regiao' => $coluna("SELECT nome FROM regioes WHERE id IN ($marcas)"),
            'setor'  => $coluna("SELECT nome FROM funcoes WHERE id IN ($marcas)"),
            default  => $valores,
        };
        return self::PUBLICOS[$c['publico']] . ': ' . implode(', ', $nomes);
    }

    // ------------------------------------------------------------------
    // Visual do catálogo
    // ------------------------------------------------------------------

    /**
     * Cores e textos do catálogo hoje: os da 1ª campanha do cliente que mudou o visual, senão os padrão
     * (Configurações do catálogo: catalogo.visual_*). Devolve ['primaria', 'destaque', 'tema', 'titulo', 'chamada', 'banner', 'selo']
     */
    public static function visual(array $campanhas): array
    {
        [$p, $s] = Aparencia::padrao();
        $padrao = [
            'primaria' => Aparencia::valida((string) \App\Core\Config::get('catalogo.visual_primaria', '')) ?? $p,
            'destaque' => Aparencia::valida((string) \App\Core\Config::get('catalogo.visual_destaque', '')) ?? $s,
            'tema' => \App\Core\Config::get('catalogo.visual_tema', 'claro') === 'escuro' ? 'escuro' : 'claro',
            'titulo' => (string) \App\Core\Config::get('catalogo.visual_titulo', 'Nossos produtos'),
            'chamada' => (string) \App\Core\Config::get('catalogo.chamada', ''),
            'banner' => ((string) \App\Core\Config::get('catalogo.visual_banner', '')) ?: null, 'selo' => null, 'campanha' => null,
        ];
        foreach ($campanhas as $c) {
            return [
                'primaria' => Aparencia::valida((string) $c['cor_primaria']) ?? $padrao['primaria'],
                'destaque' => Aparencia::valida((string) $c['cor_destaque']) ?? $padrao['destaque'],
                'tema' => $c['tema'] === 'escuro' ? 'escuro' : 'claro',
                'titulo' => $c['titulo'] ?: $c['nome'], 'chamada' => (string) $c['chamada'],
                'banner' => ($c['banner'] ?? null) ?: $padrao['banner'], 'selo' => $c['selo'] ?: null, 'campanha' => $c,
            ];
        }
        return $padrao;
    }

    /** Bloco <style> com as cores do catálogo (variáveis --cat-*) */
    public static function estilo(array $v): string
    {
        $p = $v['primaria'];
        $d = $v['destaque'];
        $escuro = $v['tema'] === 'escuro';
        $vars = Aparencia::variaveis($p, $d) + [
            '--cat-fundo'     => $escuro ? Aparencia::mistura($p, '#000000', 0.72) : Aparencia::mistura($p, '#f6f7f6', 0.955),
            '--cat-painel'    => $escuro ? Aparencia::mistura($p, '#000000', 0.55) : '#ffffff',
            '--cat-painel-2'  => $escuro ? Aparencia::mistura($p, '#000000', 0.42) : Aparencia::mistura($p, '#ffffff', 0.93),
            '--cat-texto'     => $escuro ? '#f4f8f5' : '#14211f',
            '--cat-suave'     => $escuro ? '#aebcb4' : '#5f6d6a',
            '--cat-linha'     => $escuro ? 'rgba(255,255,255,.09)' : 'rgba(2,40,40,.09)',
            '--cat-topo'      => $escuro ? Aparencia::mistura($p, '#000000', 0.6) : $p,
            '--cat-hero'      => $escuro ? Aparencia::mistura($p, '#000000', 0.35) : $p,
            '--cat-destaque'  => $d,
            '--cat-sobre-destaque' => Aparencia::textoSobre($d),
            '--cat-destaque-texto' => $escuro ? $d : (Aparencia::contraste($d, '#ffffff') >= 3 ? $d : Aparencia::mistura($d, '#000000', 0.45)),
        ];
        $linhas = [];
        foreach ($vars as $nome => $valor) {
            $linhas[] = "$nome:$valor";
        }
        return '<style id="cores-catalogo">:root{' . implode(';', $linhas) . '}</style>';
    }

    // ------------------------------------------------------------------
    // Gravar
    // ------------------------------------------------------------------

    /** Lista para a tela de gestão (no ar e agendadas primeiro) */
    public static function todas(): array
    {
        $lista = Banco::todos("SELECT c.*, u.nome AS criado_por_nome,
              (SELECT COUNT(*) FROM campanha_faixas f WHERE f.campanha_id = c.id) AS faixas,
              (SELECT COUNT(*) FROM campanha_produtos p WHERE p.campanha_id = c.id) AS produtos,
              (SELECT COUNT(*) FROM pedidos pe WHERE pe.campanhas LIKE CONCAT('%', COALESCE(NULLIF(c.titulo, ''), c.nome), '%') AND pe.criado_em >= c.inicio AND pe.criado_em < c.fim + INTERVAL 1 DAY) AS pedidos
            FROM campanhas c LEFT JOIN usuarios u ON u.id = c.criado_por ORDER BY c.fim < CURDATE(), c.inicio DESC, c.id DESC");
        return $lista;
    }

    public static function uma(int $id): ?array
    {
        $c = Banco::um('SELECT c.*, m.arquivo AS banner FROM campanhas c LEFT JOIN midias m ON m.id = c.banner_midia_id WHERE c.id = ?', [$id]);
        return $c ? self::completar($c) : null;
    }

    /**
     * Cria ou altera. $d vem do formulário. Devolve [id, erros].
     * Faixas: faixa_valor[], faixa_tipo[], faixa_pct[], faixa_brinde[]  ·  Produtos: promo_produto[], promo_pct[], promo_destaque[]
     */
    public static function salvar(?int $id, array $d, array $arquivos, int $usuarioId): array
    {
        $num = fn($v) => round(ProducaoService::numero((string) $v), 2);
        $erros = [];
        $nome = mb_substr(trim((string) ($d['nome'] ?? '')), 0, 120);
        $inicio = (string) ($d['inicio'] ?? '');
        $fim = (string) ($d['fim'] ?? '');
        if ($nome === '') {
            $erros[] = 'Dê um nome para a campanha.';
        }
        $dataOk = fn($x) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $x) && strtotime($x);
        if (!$dataOk($inicio) || !$dataOk($fim)) {
            $erros[] = 'Informe o início e o fim da campanha.';
        } elseif ($fim < $inicio) {
            $erros[] = 'O fim da campanha está antes do início.';
        }
        $publico = isset(self::PUBLICOS[$d['publico'] ?? '']) ? $d['publico'] : 'todos';
        $valores = array_values(array_filter(array_map(fn($v) => mb_substr(trim((string) $v), 0, 20), (array) ($d['publico_valores'] ?? [])), 'strlen'));
        if ($publico !== 'todos' && !$valores) {
            $erros[] = 'Escolha para quem vale a campanha (' . mb_strtolower(self::PUBLICOS[$publico]) . ').';
        }
        $faixas = [];
        foreach ((array) ($d['faixa_valor'] ?? []) as $k => $v) {
            $tipo = $d['faixa_tipo'][$k] ?? '';
            if (trim((string) $v) === '' || !isset(self::TIPOS_FAIXA[$tipo])) {
                continue;
            }
            $f = ['a_partir_de' => max(0, $num($v)), 'tipo' => $tipo, 'desconto_pct' => 0, 'brinde' => null, 'chave' => $k];
            if ($tipo === 'desconto') {
                $f['desconto_pct'] = min(100, max(0, $num($d['faixa_pct'][$k] ?? 0)));
                if ($f['desconto_pct'] <= 0) {
                    $erros[] = 'Faixa de ' . dinheiro($f['a_partir_de']) . ': informe o % de desconto.';
                }
            } elseif ($tipo === 'brinde') {
                $f['brinde'] = mb_substr(trim((string) ($d['faixa_brinde'][$k] ?? '')), 0, 120);
                if ($f['brinde'] === '') {
                    $erros[] = 'Faixa de ' . dinheiro($f['a_partir_de']) . ': escreva qual é o brinde.';
                }
            }
            $f['brinde_midia_id'] = (int) ($d['faixa_brinde_midia'][$k] ?? 0) ?: null;
            $faixas[] = $f;
        }
        $produtos = [];
        foreach ((array) ($d['promo_produto'] ?? []) as $k => $pid) {
            $pid = (int) $pid;
            $pct = min(90, max(0, $num($d['promo_pct'][$k] ?? 0)));
            if ($pid && $pct > 0) {
                $produtos[$pid] = ['produto_id' => $pid, 'desconto_pct' => $pct, 'destaque' => !empty($d['promo_destaque'][$k]) ? 1 : 0];
            }
        }
        if (!$faixas && !$produtos) {
            $erros[] = 'Coloque pelo menos uma faixa (desconto, brinde ou frete) ou um produto em promoção.';
        }
        if ($erros) {
            return [$id, $erros];
        }
        // Imagens (banner e brindes) na biblioteca de mídia
        try {
            $banner = MarketingService::guardarImagem($arquivos['banner'] ?? null, 'Banner da campanha ' . $nome);
            foreach ($faixas as &$f) {
                $arq = self::arquivoDaLista($arquivos['faixa_imagem'] ?? null, $f['chave']);
                if ($arq && ($novo = MarketingService::guardarImagem($arq, 'Brinde: ' . $f['brinde']))) {
                    $f['brinde_midia_id'] = $novo;
                }
            }
            unset($f);
        } catch (\RuntimeException $e) {
            return [$id, [$e->getMessage()]];
        }
        $cor = fn($k) => Aparencia::valida((string) ($d[$k] ?? ''));
        $linha = [
            'nome' => $nome, 'titulo' => mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 120) ?: null,
            'chamada' => mb_substr(trim((string) ($d['chamada'] ?? '')), 0, 255) ?: null, 'selo' => mb_substr(trim((string) ($d['selo'] ?? '')), 0, 30) ?: null,
            'inicio' => $inicio, 'fim' => $fim, 'ativa' => !empty($d['ativa']) ? 1 : 0,
            'publico' => $publico, 'publico_valores' => $publico === 'todos' ? null : mb_substr(implode(',', $valores), 0, 500),
            'soma_desconto_vendedor' => !empty($d['soma_desconto_vendedor']) ? 1 : 0,
            'cor_primaria' => $cor('cor_primaria'), 'cor_destaque' => $cor('cor_destaque'), 'tema' => ($d['tema'] ?? '') === 'escuro' ? 'escuro' : 'claro',
        ];
        if ($banner) {
            $linha['banner_midia_id'] = $banner;
        } elseif (!empty($d['tirar_banner'])) {
            $linha['banner_midia_id'] = null;
        }
        $antes = $id ? self::uma($id) : null;
        $id = Banco::transacao(function () use ($id, $linha, $faixas, $produtos, $usuarioId) {
            if ($id) {
                Banco::atualizar('campanhas', $id, $linha + ['atualizado_em' => date('Y-m-d H:i:s')]);
            } else {
                $id = Banco::inserir('campanhas', $linha + ['criado_por' => $usuarioId]);
            }
            Banco::executar('DELETE FROM campanha_faixas WHERE campanha_id = ?', [$id]); // faixas e produtos são da campanha: regrava a lista
            foreach ($faixas as $f) {
                unset($f['chave']);
                Banco::inserir('campanha_faixas', $f + ['campanha_id' => $id]);
            }
            Banco::executar('DELETE FROM campanha_produtos WHERE campanha_id = ?', [$id]);
            foreach ($produtos as $p) {
                Banco::inserir('campanha_produtos', $p + ['campanha_id' => $id]);
            }
            return $id;
        });
        Auditoria::registrar('campanha', (int) $id, $antes ? 'alterar' : 'criar', $antes ? array_intersect_key($antes, $linha) : null, $linha + ['faixas' => count($faixas), 'produtos' => count($produtos)]);
        self::esquecer();
        return [(int) $id, []];
    }

    /** Um arquivo de um campo múltiplo do $_FILES (name="faixa_imagem[k]") */
    private static function arquivoDaLista(?array $campo, $k): ?array
    {
        if (!$campo || !isset($campo['name'][$k])) {
            return null;
        }
        return ['name' => $campo['name'][$k], 'type' => $campo['type'][$k], 'tmp_name' => $campo['tmp_name'][$k], 'error' => $campo['error'][$k], 'size' => $campo['size'][$k]];
    }

    /** Liga / desliga */
    public static function alternar(int $id): void
    {
        $c = self::uma($id);
        if ($c) {
            Banco::atualizar('campanhas', $id, ['ativa' => (int) $c['ativa'] ? 0 : 1, 'atualizado_em' => date('Y-m-d H:i:s')]);
            Auditoria::registrar('campanha', $id, (int) $c['ativa'] ? 'desligar' : 'ligar', null, null);
            self::esquecer();
        }
    }

    /** Duplica para o próximo período (mesma duração, começando no dia seguinte ao fim) */
    public static function duplicar(int $id, int $usuarioId): ?int
    {
        $c = self::uma($id);
        if (!$c) {
            return null;
        }
        $dias = (int) round((strtotime($c['fim']) - strtotime($c['inicio'])) / 86400);
        $inicio = date('Y-m-d', strtotime($c['fim'] . ' +1 day'));
        // campanha de mês cheio → o mês seguinte inteiro
        $mesCheio = date('d', strtotime($c['inicio'])) === '01' && date('Y-m-t', strtotime($c['inicio'])) === $c['fim'];
        $fim = $mesCheio ? date('Y-m-t', strtotime($inicio)) : date('Y-m-d', strtotime("$inicio +$dias day"));
        $novo = Banco::transacao(function () use ($c, $inicio, $fim, $usuarioId) {
            $linha = array_intersect_key($c, array_flip(['titulo', 'chamada', 'selo', 'publico', 'publico_valores', 'soma_desconto_vendedor', 'cor_primaria', 'cor_destaque', 'tema', 'banner_midia_id', 'ordem']));
            $novo = Banco::inserir('campanhas', $linha + ['nome' => mb_substr($c['nome'] . ' (cópia)', 0, 120), 'inicio' => $inicio, 'fim' => $fim, 'ativa' => 0, 'criado_por' => $usuarioId]);
            foreach ($c['faixas'] as $f) {
                Banco::inserir('campanha_faixas', ['campanha_id' => $novo] + array_intersect_key($f, array_flip(['a_partir_de', 'tipo', 'desconto_pct', 'brinde', 'brinde_midia_id'])));
            }
            foreach ($c['produtos'] as $p) {
                Banco::inserir('campanha_produtos', ['campanha_id' => $novo, 'produto_id' => $p['produto_id'], 'desconto_pct' => $p['desconto_pct'], 'destaque' => $p['destaque']]);
            }
            return $novo;
        });
        Auditoria::registrar('campanha', (int) $novo, 'duplicar', null, ['de' => $id]);
        return (int) $novo;
    }
}
