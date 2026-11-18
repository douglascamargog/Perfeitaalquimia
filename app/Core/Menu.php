<?php
namespace App\Core;

/** Ajuda com o menu lateral (app/menu.php) */
class Menu
{
    /** Ícone de cada grupo (submenu) que não é setor de venda */
    private const ICONES_GRUPO = ['Financeiro' => 'moeda', 'Gestão' => 'grafico', 'Marketing' => 'megafone', 'Produtos' => 'caixa',
        'Produção' => 'fabrica', 'Administração' => 'engrenagem', 'Vendas' => 'pessoas', 'Comercial' => 'pessoas', 'Treinamento' => 'livro', 'Desenvolvimento' => 'faisca', 'Depósito' => 'loja', 'Compras' => 'carrinho', 'Estoque' => 'caixa'];

    /**
     * Monta o menu lateral em grupos que abrem e fecham (submenus).
     *  - Topo: itens sem grupo (Início).
     *  - COMERCIAL: um grupo só para todos os setores de venda (Televendas, Externo, Terceirização, Prospecção).
     *    No alto, a escolha do setor (só para quem trabalha em mais de um); embaixo, as abas comuns ('setor' => true)
     *    com ?setor=slug, as abas só daquele setor ('so_setor') e as outras abas do Comercial (Campanhas).
     *  - Administração NÃO fica no menu: abre pela engrenagem do topo (/administracao).
     *  - Demais grupos (Financeiro, Gestão, Marketing...): todos veem, com cadeado (sem acesso) ou olho (só leitura).
     * Devolve [ ['chave', 'nome', 'icone', 'itens' => [...], 'aberto' => bool, 'contador' => int, 'topo' => bool, 'setores' => [...]], ... ]
     * Cada item ganha: href, bloqueado, leitura, ativo, contador.
     */
    public static function lateral(string $caminhoAtual, array $pendencias): array
    {
        $setores = \App\Services\Setores::daPessoa();
        $atual = \App\Services\Setores::atual();
        $itens = self::itensVisiveis($caminhoAtual, $urlAtual);
        $verTodos = Auth::pode('carteira.ver_todas');
        // Setor das abas do Comercial: o escolhido; sem escolha, quem vê todas as carteiras fica em "Todos" ('') e os outros no 1º setor
        $principal = $atual['slug'] ?? ($verTodos ? '' : (array_key_first($setores) ?? ''));

        $grupos = [];
        $novoGrupo = fn(string $chave, string $nome, string $icone, bool $topo = false) => ['chave' => $chave, 'nome' => $nome, 'icone' => $icone, 'itens' => [], 'aberto' => false, 'contador' => 0, 'topo' => $topo];
        $grupos['topo'] = $novoGrupo('topo', '', '', true);
        $grupos['Comercial'] = $novoGrupo('Comercial', 'Comercial', self::ICONES_GRUPO['Comercial']);
        $comuns = [];
        $extras = [];
        foreach ($itens as $item) {
            if (!empty($item['oculto']) || ($item['grupo'] ?? '') === 'Administração') {
                continue; // fica em abas de outra entrada, ou na página Administração (engrenagem do topo)
            }
            $item['ativo'] = $item['url'] === '/' ? $caminhoAtual === '/' : ($item['url'] === $urlAtual || in_array($urlAtual, $item['tambem'] ?? [], true));
            $item['href'] = $item['url'];
            $item['contador'] = (int) ($pendencias[$item['url']] ?? 0);
            if (($item['grupo'] ?? '') === '') {
                $grupos['topo']['itens'][] = $item;
            } elseif (!empty($item['setor'])) {
                $item['href'] = $item['url'] . ($setores ? '?setor=' . rawurlencode($principal) : '');
                $comuns[] = $item;
            } elseif (!empty($item['so_setor'])) {
                if (isset($setores[$item['so_setor']])) {
                    // aba só de um setor: aparece com o setor escolhido (ou em "Todos"); leva para o setor dela
                    if ($principal === '' || $principal === $item['so_setor']) {
                        $item['href'] = $item['url'] . '?setor=' . rawurlencode($item['so_setor']);
                        $extras[] = $item;
                    }
                } elseif (!$setores || !isset(\App\Services\Setores::todos()[$item['so_setor']])) {
                    $extras[] = $item; // quem não trabalha em setor de venda (ex.: Produção vê os rótulos da Terceirização)
                }
            } elseif (in_array($item['grupo'], ['Vendas', 'Comercial'], true)) {
                $extras[] = $item;
            } else {
                $grupos[$item['grupo']] ??= $novoGrupo($item['grupo'], $item['grupo'], self::ICONES_GRUPO[$item['grupo']] ?? 'mais');
                $grupos[$item['grupo']]['itens'][] = $item;
            }
        }
        $grupos['Comercial']['itens'] = array_merge($comuns, $extras);
        // Escolha do setor (chips no alto do Comercial): só para quem trabalha em mais de um
        if (count($setores) > 1 || ($verTodos && $setores)) {
            $base = !empty(self::itemDe($caminhoAtual)['setor']) ? $urlAtual : '/carteira';
            $chips = [];
            if ($verTodos) {
                $chips[] = ['slug' => '', 'nome' => 'Todos', 'href' => $base . '?setor=', 'ativo' => $principal === ''];
            }
            foreach ($setores as $slug => $st) {
                $chips[] = ['slug' => $slug, 'nome' => self::nomeCurto($st['nome']), 'href' => $base . '?setor=' . rawurlencode($slug), 'ativo' => $principal === $slug, 'icone' => $st['icone']];
            }
            $grupos['Comercial']['setores'] = $chips;
        }
        foreach ($grupos as $chave => &$g) {
            if (!$g['itens']) {
                unset($grupos[$chave]);
                continue;
            }
            // Dentro do grupo, as abas que a pessoa pode abrir vêm primeiro (usort é estável no PHP 8)
            usort($g['itens'], fn($a, $b) => (int) $a['bloqueado'] <=> (int) $b['bloqueado']);
            $g['aberto'] = (bool) array_filter($g['itens'], fn($i) => $i['ativo']);
            $g['contador'] = array_sum(array_column($g['itens'], 'contador'));
            $g['bloqueado'] = !array_filter($g['itens'], fn($i) => !$i['bloqueado']);
        }
        unset($g);
        return array_values($grupos);
    }

    /** Todos os itens do menu com bloqueado/leitura calculados; $urlAtual recebe o item da página atual */
    private static function itensVisiveis(string $caminhoAtual, ?string &$urlAtual): array
    {
        $itens = [];
        $urlAtual = '/';
        foreach (require RAIZ . '/app/menu.php' as $item) {
            if (!empty($item['so_desenvolvedor']) && !Auth::pode('sistema.desenvolvedor')) {
                continue; // telas de desenvolvimento do sistema: nem aparecem para os outros
            }
            $item['bloqueado'] = !\App\Controllers\InicioController::visivel($item);
            $item['leitura'] = !$item['bloqueado'] && !empty($item['editar']) && !Auth::pode($item['editar']);
            // Diretoria (só acompanha): tudo com o olho, menos onde ela aprova ou pede (e as telas de venda de quem também vende)
            if (!$item['bloqueado'] && Auth::somenteAcompanha() && !in_array($item['url'], ['/', '/pessoas', '/aprovacao', '/compras', '/marketing/pedir'], true)
                && !(Auth::pode('diretoria.vende') && (!empty($item['setor']) || !empty($item['so_setor'])))) {
                $item['leitura'] = true;
            }
            $itens[] = $item;
            if (!$item['bloqueado'] && $item['url'] !== '/' && str_starts_with($caminhoAtual . '/', $item['url'] . '/') && strlen($item['url']) > strlen($urlAtual)) {
                $urlAtual = $item['url'];
            }
        }
        return $itens;
    }

    /** "Terceirização e Marca Própria" → "Terceirização"; "Prospecção e Reativação" → "Prospecção" (só no chip do menu) */
    private static function nomeCurto(string $nome): string
    {
        return preg_replace('/\s+e\s+.*$/u', '', $nome) ?: $nome;
    }

    /** Itens da página Administração (engrenagem do topo) que a pessoa pode abrir */
    public static function administracao(): array
    {
        $ordem = ['/usuarios', '/configuracoes', '/comunicados', '/integracao', '/sistema/atualizacao', '/auditoria', '/desenvolvimento/etapas'];
        $lista = array_values(array_filter(self::itensVisiveis('/', $nada), fn($i) => ($i['grupo'] ?? '') === 'Administração' && empty($i['oculto']) && !$i['bloqueado']));
        $pos = fn($i) => ($k = array_search($i['url'], $ordem, true)) === false ? 99 : $k;
        usort($lista, fn($a, $b) => $pos($a) <=> $pos($b));
        return $lista;
    }

    /** Telas que não estão no menu, mas têm nome */
    private const OUTRAS = ['/meu-perfil' => 'Meu perfil', '/novidades' => 'Novidades', '/minha-senha' => 'Meu perfil', '/notificacoes' => 'Notificações'];

    /** O item do menu da página (o mais específico: /producao/fichas → "Fichas técnicas") ou null */
    public static function itemDe(string $caminho): ?array
    {
        $caminho = '/' . trim($caminho, '/');
        $achado = null;
        foreach (require RAIZ . '/app/menu.php' as $item) {
            $ok = $item['url'] === '/' ? $caminho === '/' : str_starts_with($caminho . '/', $item['url'] . '/');
            if ($ok && strlen($item['url']) > strlen((string) ($achado['url'] ?? ''))) {
                $achado = $item;
            }
        }
        return $achado;
    }

    /** Nome da aba para mostrar ("Carteira", "Pedidos"...). Nunca devolve ids nem dados da página. */
    public static function abaDe(string $caminho): string
    {
        $caminho = '/' . trim($caminho, '/');
        foreach (self::OUTRAS as $url => $nome) {
            if (str_starts_with($caminho . '/', $url . '/')) {
                return $nome;
            }
        }
        $item = self::itemDe($caminho);
        // Aba de venda dentro de um setor: "Televendas › Carteira" (só o nome do setor, nunca dado de cliente)
        if (!empty($item['setor']) && ($setor = \App\Services\Setores::atual())) {
            return $setor['nome'] . ' › ' . $item['titulo'];
        }
        return $item['titulo'] ?? 'One';
    }
}
