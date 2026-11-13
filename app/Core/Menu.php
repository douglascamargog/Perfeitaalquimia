<?php
namespace App\Core;

/** Ajuda com o menu lateral (app/menu.php) */
class Menu
{
    /** Ícone de cada grupo (submenu) que não é setor de venda */
    private const ICONES_GRUPO = ['Financeiro' => 'moeda', 'Gestão' => 'grafico', 'Marketing' => 'megafone', 'Produtos' => 'caixa',
        'Produção' => 'fabrica', 'Administração' => 'engrenagem', 'Vendas' => 'pessoas', 'Comercial' => 'pessoas'];

    /**
     * Monta o menu lateral em grupos que abrem e fecham (submenus).
     *  - Topo: itens sem grupo (Início, Equipe).
     *  - Um grupo por SETOR DE VENDA da pessoa (Setores::daPessoa): abas comuns ('setor' => true) com ?setor=slug
     *    e as abas só daquele setor ('so_setor'). Setor desligado em Funções (ou pessoa sem setor): as abas vão para o grupo "Comercial".
     *  - Demais grupos (Financeiro, Gestão, Marketing...): todos veem, com cadeado (sem acesso) ou olho (só leitura).
     * Devolve [ ['chave', 'nome', 'icone', 'itens' => [...], 'aberto' => bool, 'contador' => int, 'topo' => bool], ... ]
     * Cada item ganha: href, bloqueado, leitura, ativo, contador (só no grupo principal, para não repetir).
     */
    public static function lateral(string $caminhoAtual, array $pendencias): array
    {
        $setores = \App\Services\Setores::daPessoa();
        $atual = \App\Services\Setores::atual();
        $itens = [];
        $urlAtual = '/';
        foreach (require RAIZ . '/app/menu.php' as $item) {
            $item['bloqueado'] = !\App\Controllers\InicioController::visivel($item);
            $item['leitura'] = !$item['bloqueado'] && !empty($item['editar']) && !Auth::pode($item['editar']);
            $itens[] = $item;
            if (!$item['bloqueado'] && $item['url'] !== '/' && str_starts_with($caminhoAtual . '/', $item['url'] . '/') && strlen($item['url']) > strlen($urlAtual)) {
                $urlAtual = $item['url'];
            }
        }
        // Setor "principal" para marcar a aba ativa e mostrar os contadores: o atual, senão o primeiro
        $principal = $atual['slug'] ?? (array_key_first($setores) ?? '');

        $grupos = [];
        $novoGrupo = fn(string $chave, string $nome, string $icone, bool $topo = false) => ['chave' => $chave, 'nome' => $nome, 'icone' => $icone, 'itens' => [], 'aberto' => false, 'contador' => 0, 'topo' => $topo];
        $grupos['topo'] = $novoGrupo('topo', '', '', true);
        foreach ($setores as $slug => $s) {
            $grupos['setor:' . $slug] = $novoGrupo('setor:' . $slug, $s['nome'], $s['icone']) + ['setor' => $slug];
        }
        $comuns = array_filter($itens, fn($i) => !empty($i['setor']));
        foreach ($itens as $item) {
            $item['ativo'] = $item['url'] === '/' ? $caminhoAtual === '/' : $item['url'] === $urlAtual;
            $item['href'] = $item['url'];
            $item['contador'] = (int) ($pendencias[$item['url']] ?? 0);
            if (($item['grupo'] ?? '') === '') {
                $grupos['topo']['itens'][] = $item;
            } elseif (!empty($item['setor'])) {
                continue; // entra em cada setor logo abaixo
            } elseif (!empty($item['so_setor']) && isset($grupos['setor:' . $item['so_setor']])) {
                $grupos['setor:' . $item['so_setor']]['extras'][] = $item;
            } elseif (!empty($item['so_setor']) && $setores && isset(\App\Services\Setores::todos()[$item['so_setor']])) {
                continue; // setor existe, mas não é desta pessoa (cada um vê só o seu setor)
            } else {
                // Quem não trabalha em setor de venda (ex.: Produção vê os rótulos da Terceirização): grupo "Comercial"
                $g = $item['grupo'] === 'Vendas' ? 'Comercial' : $item['grupo'];
                $grupos[$g] ??= $novoGrupo($g, $g, self::ICONES_GRUPO[$g] ?? 'mais');
                $grupos[$g]['itens'][] = $item;
            }
        }
        // Abas comuns de venda dentro de cada setor (com ?setor=), depois as abas só do setor
        foreach ($setores as $slug => $s) {
            $g = &$grupos['setor:' . $slug];
            foreach ($comuns as $item) {
                $item['href'] = $item['url'] . '?setor=' . rawurlencode($slug);
                $item['ativo'] = $item['url'] === $urlAtual && $slug === $principal;
                $item['comContador'] = $slug === $principal; // contador pessoal (agenda, recompra) aparece num setor só
                $item['contador'] = $item['comContador'] ? (int) ($pendencias[$item['url']] ?? 0) : 0;
                $g['itens'][] = $item;
            }
            foreach ($g['extras'] ?? [] as $item) {
                $item['ativo'] = $item['url'] === $urlAtual;
                $g['itens'][] = $item;
            }
            unset($g['extras'], $g);
        }
        // Sem nenhum setor de venda cadastrado: as abas comuns vão para o grupo "Vendas"
        if (!$setores && $comuns) {
            $grupos['Comercial'] ??= $novoGrupo('Comercial', 'Comercial', self::ICONES_GRUPO['Vendas']);
            foreach ($comuns as $item) {
                $item['ativo'] = $item['url'] === $urlAtual;
                $item['href'] = $item['url'];
                $item['contador'] = (int) ($pendencias[$item['url']] ?? 0);
                array_unshift($grupos['Comercial']['itens'], $item);
            }
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
