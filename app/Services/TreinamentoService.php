<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;

/**
 * Treinamento por setor (V13.0) — no lugar da Academia antiga.
 *
 * Trilhas (trein_trilhas):
 *  - "integracao": para todos (a empresa, como usar o One, segurança e LGPD, quem faz o quê);
 *  - uma por FUNÇÃO (televendas, producao...): módulos do trabalho do setor (tipo "setor") e um módulo
 *    "Como trabalha o setor" (tipo "vizinho"), que aparece para os setores que têm contato com ele
 *    (coluna vizinhos da trilha de quem estuda).
 * Cada módulo tem uma prova curta (trein_perguntas); concluído = nota mínima (Configurações › Treinamento, padrão 70%).
 * A resposta certa nunca vai para o navegador antes de a pessoa enviar.
 */
class TreinamentoService
{
    // ------------------------------------------------------------------ leitura

    /** Todas as trilhas ativas: [chave => linha] */
    public static function trilhas(): array
    {
        $lista = [];
        foreach (Banco::todos('SELECT * FROM trein_trilhas WHERE ativo = 1 ORDER BY ordem, id') as $t) {
            $lista[$t['chave']] = $t;
        }
        return $lista;
    }

    /** Módulos ativos de uma trilha (opcionalmente só de um tipo) */
    public static function modulos(int $trilhaId, ?string $tipo = null): array
    {
        return Banco::todos('SELECT m.*, (SELECT COUNT(*) FROM trein_perguntas p WHERE p.modulo_id = m.id) perguntas
            FROM trein_modulos m WHERE m.trilha_id = ? AND m.ativo = 1' . ($tipo ? ' AND m.tipo = ?' : '') . ' ORDER BY m.ordem, m.id',
            $tipo ? [$trilhaId, $tipo] : [$trilhaId]);
    }

    public static function modulo(int $id): ?array
    {
        $m = Banco::um('SELECT m.*, t.titulo trilha_titulo, t.chave trilha_chave, t.icone trilha_icone FROM trein_modulos m JOIN trein_trilhas t ON t.id = m.trilha_id WHERE m.id = ?', [$id]);
        return $m ?: null;
    }

    /** Perguntas do módulo SEM a resposta certa (para a tela da prova) */
    public static function perguntas(int $moduloId, bool $comResposta = false): array
    {
        $lista = Banco::todos('SELECT id, pergunta, opcoes_json, correta, explicacao FROM trein_perguntas WHERE modulo_id = ? ORDER BY ordem, id', [$moduloId]);
        foreach ($lista as &$p) {
            $p['opcoes'] = (array) json_decode($p['opcoes_json'], true);
            unset($p['opcoes_json']);
            if (!$comResposta) {
                unset($p['correta'], $p['explicacao']);
            }
        }
        return $lista;
    }

    /** Funções da pessoa (slugs) — mais as trilhas dos perfis Financeiro, Gestão e Administração */
    public static function funcoesDe(array $usuario): array
    {
        $slugs = array_column($usuario['funcoes'] ?? \App\Models\Usuario::funcoesDe((int) $usuario['id']), 'slug');
        $perfil = (string) Banco::valor('SELECT p.slug FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.id = ?', [(int) $usuario['id']]);
        // perfis com trilha própria: Financeiro (aprovação), Gestão (gestor e gestor de setor) e Administração (admin)
        $porPerfil = ['financeiro' => 'financeiro', 'gestor' => 'gestor', 'gestor_setor' => 'gestor', 'admin' => 'admin'][$perfil] ?? null;
        if ($porPerfil && !in_array($porPerfil, $slugs, true)) {
            $slugs[] = $porPerfil;
        }
        return $slugs;
    }

    /**
     * O treinamento de uma pessoa, em blocos:
     *  [['chave','titulo','icone','descricao','tipo' => integracao|setor|vizinhos,'modulos' => [...]]]
     */
    public static function daPessoa(array $usuario): array
    {
        $trilhas = self::trilhas();
        $funcoes = self::funcoesDe($usuario);
        $blocos = [];
        if (isset($trilhas['integracao'])) {
            $t = $trilhas['integracao'];
            $blocos[] = ['chave' => 'integracao', 'titulo' => $t['titulo'], 'icone' => $t['icone'], 'descricao' => $t['descricao'], 'tipo' => 'integracao', 'modulos' => self::modulos((int) $t['id'])];
        }
        $vizinhos = [];
        foreach ($funcoes as $slug) {
            if (!isset($trilhas[$slug])) {
                continue;
            }
            $t = $trilhas[$slug];
            $blocos[] = ['chave' => $slug, 'titulo' => $t['titulo'], 'icone' => $t['icone'], 'descricao' => $t['descricao'], 'tipo' => 'setor', 'modulos' => self::modulos((int) $t['id'], 'setor')];
            foreach (array_filter(array_map('trim', explode(',', (string) $t['vizinhos']))) as $v) {
                $vizinhos[$v] = true;
            }
        }
        // Trilhas para vários setores (ex.: Conhecimento de produto): funcao_slug vazio e a coluna "vizinhos"
        // diz quais funções a fazem
        foreach ($trilhas as $chave => $t) {
            if ($chave === 'integracao' || $t['funcao_slug'] !== null) {
                continue;
            }
            $para = array_filter(array_map('trim', explode(',', (string) $t['vizinhos'])));
            if (array_intersect($para, $funcoes)) {
                $blocos[] = ['chave' => $chave, 'titulo' => $t['titulo'], 'icone' => $t['icone'], 'descricao' => $t['descricao'], 'tipo' => 'setor', 'modulos' => self::modulos((int) $t['id'], 'setor')];
            }
        }
        $modsVizinhos = [];
        foreach (array_keys($vizinhos) as $v) {
            if (isset($trilhas[$v]) && !in_array($v, $funcoes, true)) {
                foreach (self::modulos((int) $trilhas[$v]['id'], 'vizinho') as $m) {
                    $m['setor_icone'] = $trilhas[$v]['icone'];
                    $modsVizinhos[] = $m;
                }
            }
        }
        if ($modsVizinhos) {
            $blocos[] = ['chave' => 'vizinhos', 'titulo' => 'Áreas com que você tem contato', 'icone' => '🤝',
                'descricao' => 'Como trabalham os setores que recebem o seu trabalho ou mandam trabalho para você.', 'tipo' => 'vizinhos', 'modulos' => $modsVizinhos];
        }
        return $blocos;
    }

    /** Progresso de uma pessoa: [modulo_id => linha] */
    public static function progresso(int $usuarioId): array
    {
        $lista = [];
        foreach (Banco::todos('SELECT * FROM trein_progresso WHERE usuario_id = ?', [$usuarioId]) as $p) {
            $lista[(int) $p['modulo_id']] = $p;
        }
        return $lista;
    }

    /** [concluídos, total, próximo módulo] de uma lista de blocos */
    public static function resumo(array $blocos, array $progresso): array
    {
        $total = 0;
        $feitos = 0;
        $proximo = null;
        foreach ($blocos as $b) {
            foreach ($b['modulos'] as $m) {
                $total++;
                if (!empty($progresso[(int) $m['id']]['aprovado_em'])) {
                    $feitos++;
                } elseif (!$proximo) {
                    $proximo = $m;
                }
            }
        }
        return [$feitos, $total, $proximo];
    }

    public static function notaMinima(): int
    {
        return max(0, min(100, (int) Config::get('treinamento.nota_minima', 70)));
    }

    /** A pessoa pode abrir o módulo? (é do treinamento dela, ou ela pode ver todas as trilhas) */
    public static function podeAbrir(array $usuario, int $moduloId): bool
    {
        if (Auth::pode('treinamento.equipe') || Auth::pode('academia.editar')) {
            return true;
        }
        foreach (self::daPessoa($usuario) as $b) {
            foreach ($b['modulos'] as $m) {
                if ((int) $m['id'] === $moduloId) {
                    return true;
                }
            }
        }
        // Os outros setores ficam abertos para consulta (Todas as trilhas), só leitura
        return (bool) Banco::valor('SELECT 1 FROM trein_modulos WHERE id = ? AND ativo = 1', [$moduloId]);
    }

    // ------------------------------------------------------------------ estudar

    public static function marcarLido(int $usuarioId, int $moduloId): void
    {
        Banco::executar('INSERT INTO trein_progresso (usuario_id, modulo_id, lido_em) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE lido_em = COALESCE(lido_em, NOW())', [$usuarioId, $moduloId]);
    }

    /**
     * Corrige a prova. $respostas = [pergunta_id => índice da opção].
     * Devolve ['nota', 'aprovado', 'correcao' => [pergunta_id => ['certa' => bool, 'correta' => índice, 'explicacao']], 'trilha_concluida' => título|null]
     */
    public static function corrigir(int $usuarioId, int $moduloId, array $respostas): array
    {
        $perguntas = self::perguntas($moduloId, true);
        if (!$perguntas) {
            self::marcarLido($usuarioId, $moduloId);
            Banco::executar('UPDATE trein_progresso SET nota = 100, aprovado_em = COALESCE(aprovado_em, NOW()) WHERE usuario_id = ? AND modulo_id = ?', [$usuarioId, $moduloId]);
            return ['nota' => 100, 'aprovado' => true, 'correcao' => [], 'trilha_concluida' => self::trilhaConcluida($usuarioId, $moduloId)];
        }
        $certas = 0;
        $correcao = [];
        foreach ($perguntas as $p) {
            $marcada = isset($respostas[$p['id']]) && $respostas[$p['id']] !== '' ? (int) $respostas[$p['id']] : -1;
            $ok = $marcada === (int) $p['correta'];
            $certas += $ok ? 1 : 0;
            $correcao[(int) $p['id']] = ['certa' => $ok, 'marcada' => $marcada, 'correta' => (int) $p['correta'], 'explicacao' => $p['explicacao']];
        }
        $nota = (int) round($certas / count($perguntas) * 100);
        $aprovado = $nota >= self::notaMinima();
        Banco::executar('INSERT INTO trein_progresso (usuario_id, modulo_id, lido_em, nota, tentativas, aprovado_em) VALUES (?, ?, NOW(), ?, 1, ?)
            ON DUPLICATE KEY UPDATE lido_em = COALESCE(lido_em, NOW()), nota = GREATEST(COALESCE(nota, 0), VALUES(nota)), tentativas = tentativas + 1,
              aprovado_em = COALESCE(aprovado_em, VALUES(aprovado_em))',
            [$usuarioId, $moduloId, $nota, $aprovado ? date('Y-m-d H:i:s') : null]);
        return ['nota' => $nota, 'aprovado' => $aprovado, 'correcao' => $correcao, 'trilha_concluida' => $aprovado ? self::trilhaConcluida($usuarioId, $moduloId) : null];
    }

    /** Se este módulo fechou a trilha do SETOR da pessoa (todos os módulos "setor" aprovados), devolve o título da trilha */
    private static function trilhaConcluida(int $usuarioId, int $moduloId): ?string
    {
        $m = self::modulo($moduloId);
        if (!$m || $m['tipo'] !== 'setor') {
            return null;
        }
        $faltam = (int) Banco::valor('SELECT COUNT(*) FROM trein_modulos m LEFT JOIN trein_progresso p ON p.modulo_id = m.id AND p.usuario_id = ?
            WHERE m.trilha_id = ? AND m.ativo = 1 AND m.tipo = ? AND p.aprovado_em IS NULL', [$usuarioId, $m['trilha_id'], 'setor']);
        return $faltam === 0 ? $m['trilha_titulo'] : null;
    }

    /** Trilhas de setor (ou integração) que a pessoa concluiu: [['titulo','icone','em']] — vira selo no perfil */
    public static function trilhasConcluidas(int $usuarioId): array
    {
        return Banco::todos('SELECT t.titulo, t.icone, MAX(p.aprovado_em) em
              FROM trein_trilhas t JOIN trein_modulos m ON m.trilha_id = t.id AND m.ativo = 1 AND m.tipo = \'setor\'
              LEFT JOIN trein_progresso p ON p.modulo_id = m.id AND p.usuario_id = ?
             WHERE t.ativo = 1
             GROUP BY t.id, t.titulo, t.icone
            HAVING COUNT(m.id) > 0 AND COUNT(m.id) = COUNT(p.aprovado_em)
             ORDER BY em', [$usuarioId]);
    }

    // ------------------------------------------------------------------ equipe

    /** Para a tela Equipe: cada pessoa ativa com módulos concluídos, média e telas aprendidas */
    public static function equipe(string $funcao = ''): array
    {
        $params = [];
        $filtro = '';
        if ($funcao !== '') {
            $filtro = ' AND EXISTS (SELECT 1 FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id AND f.slug = ?)';
            $params[] = $funcao;
        }
        $pessoas = Banco::todos("SELECT u.id, u.nome, u.foto, u.criado_em, u.ultimo_login_em, u.tutorial_ativo,
                (SELECT GROUP_CONCAT(f.nome ORDER BY f.ordem SEPARATOR ', ') FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id) funcoes,
                (SELECT COUNT(*) FROM trein_progresso p WHERE p.usuario_id = u.id AND p.aprovado_em IS NOT NULL) concluidos,
                (SELECT ROUND(AVG(p.nota)) FROM trein_progresso p WHERE p.usuario_id = u.id AND p.nota IS NOT NULL) media,
                (SELECT MAX(COALESCE(p.aprovado_em, p.lido_em)) FROM trein_progresso p WHERE p.usuario_id = u.id) ultimo_estudo
              FROM usuarios u WHERE u.ativo = 1$filtro ORDER BY u.nome", $params);
        $telas = TutorialService::contagemPorPessoa();
        foreach ($pessoas as &$p) {
            $p['telas'] = $telas[(int) $p['id']] ?? 0;
            $u = ['id' => (int) $p['id'], 'funcoes' => \App\Models\Usuario::funcoesDe((int) $p['id'])];
            [, $p['total']] = self::resumo(self::daPessoa($u), []);
        }
        return $pessoas;
    }

    // ------------------------------------------------------------------ edição (Admin e Marketing)

    public static function salvarModulo(int $id, array $d): void
    {
        $dados = [
            'titulo'    => mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 160) ?: 'Módulo',
            'resumo'    => mb_substr(trim((string) ($d['resumo'] ?? '')), 0, 255) ?: null,
            'conteudo'  => (string) ($d['conteudo'] ?? ''),
            'tempo_min' => max(1, min(120, (int) ($d['tempo_min'] ?? 5))),
            'ativo'     => empty($d['ativo']) ? 0 : 1,
            'editado_em' => date('Y-m-d H:i:s'),
        ];
        Banco::atualizar('trein_modulos', $id, $dados);
        // Perguntas: o formulário manda todas de novo (pergunta, opções uma por linha, número da certa, explicação)
        if (isset($d['perguntas']) && is_array($d['perguntas'])) {
            $existentes = array_column(Banco::todos('SELECT id FROM trein_perguntas WHERE modulo_id = ?', [$id]), 'id');
            $ordem = 0;
            $mantidas = [];
            foreach ($d['perguntas'] as $p) {
                $texto = mb_substr(trim((string) ($p['pergunta'] ?? '')), 0, 255);
                $opcoes = array_values(array_filter(array_map(fn($o) => mb_substr(trim($o), 0, 200), preg_split('/\R/', (string) ($p['opcoes'] ?? ''))), 'strlen'));
                if ($texto === '' || count($opcoes) < 2) {
                    continue;
                }
                $linha = ['modulo_id' => $id, 'pergunta' => $texto, 'opcoes_json' => json_encode($opcoes, JSON_UNESCAPED_UNICODE),
                    'correta' => max(0, min(count($opcoes) - 1, (int) ($p['correta'] ?? 1) - 1)),
                    'explicacao' => mb_substr(trim((string) ($p['explicacao'] ?? '')), 0, 255) ?: null, 'ordem' => ++$ordem];
                $pid = (int) ($p['id'] ?? 0);
                if ($pid && in_array($pid, array_map('intval', $existentes), true)) {
                    Banco::atualizar('trein_perguntas', $pid, $linha);
                    $mantidas[] = $pid;
                } else {
                    $mantidas[] = Banco::inserir('trein_perguntas', $linha);
                }
            }
            foreach ($existentes as $pid) {
                if (!in_array((int) $pid, $mantidas, true)) {
                    Banco::executar('DELETE FROM trein_perguntas WHERE id = ?', [$pid]); // pergunta tirada pelo editor
                }
            }
        }
        Auditoria::registrar('configuracao', $id, 'editar_modulo_treinamento', null, ['titulo' => $dados['titulo']]);
    }

    public static function novoModulo(int $trilhaId, string $tipo): int
    {
        $ordem = (int) Banco::valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM trein_modulos WHERE trilha_id = ?', [$trilhaId]);
        $id = Banco::inserir('trein_modulos', ['trilha_id' => $trilhaId, 'chave' => 'novo-' . bin2hex(random_bytes(5)), 'tipo' => $tipo === 'vizinho' ? 'vizinho' : 'setor',
            'titulo' => 'Novo módulo', 'conteudo' => "## Assunto\n\nEscreva aqui.", 'ordem' => $ordem, 'editado_em' => date('Y-m-d H:i:s')]);
        Auditoria::registrar('configuracao', $id, 'criar_modulo_treinamento');
        return $id;
    }

    // ------------------------------------------------------------------ texto do módulo

    /**
     * Texto simples → HTML seguro. Tudo é escapado; só viram HTML:
     *  ## Título · ### Subtítulo · "- item" (lista) · "1. item" (lista numerada) · "> destaque" · "! atenção / confira"
     *  "?? situação || o que fazer" → caso prático (abre ao clicar)
     *  **negrito** · [texto](/tela-do-sistema)
     *  ![legenda](assets/img/treinamento/tela.jpg) ou ![legenda](media/...) numa linha sozinha → print com legenda
     *  {{config:chave}} → o valor atual da configuração (ex.: {{config:pedidos.desconto_max_pct}})
     *  {{pedido_minimo}} · {{condicoes_pagamento}} · {{produtos:Cápsulas}} → tabelas montadas com os dados atuais
     */
    public static function html(string $texto): string
    {
        $linha = function (string $t): string {
            $t = e($t);
            $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
            $t = preg_replace_callback('/\{\{config:([a-z0-9_.]+)\}\}/', function ($m) {
                $v = Config::get($m[1], '—');
                return '<b class="tr-regra" title="Valor atual em Configurações">' . e(is_array($v) ? implode(', ', $v) : (string) $v) . '</b>';
            }, $t);
            $t = preg_replace_callback('/!\[([^\]]*)\]\(((?:media|assets\/img\/treinamento)\/[A-Za-z0-9_\/.\-]+)\)/', fn($m) => '<img src="' . e(url('/' . $m[2])) . '" alt="' . $m[1] . '" loading="lazy">', $t);
            return preg_replace_callback('/\[([^\]]+)\]\((\/[A-Za-z0-9_\/\-?=&;.]*)\)/', fn($m) => str_starts_with($m[2], '//') ? $m[1] : '<a href="' . e(url(html_entity_decode($m[2]))) . '">' . $m[1] . '</a>', $t);
        };
        $html = [];
        $lista = null;
        $itens = [];
        $continuar = 0; // lista numerada interrompida só por um print continua a contagem (3, 4, 5...)
        $fechar = function () use (&$html, &$lista, &$itens, &$continuar) {
            if ($lista) {
                $inicio = $lista === 'ol' && $continuar ? ' start="' . ($continuar + 1) . '"' : '';
                $html[] = "<$lista$inicio>" . implode('', array_map(fn($i) => "<li>$i</li>", $itens)) . "</$lista>";
                $continuar = $lista === 'ol' ? $continuar + count($itens) : 0;
            }
            $lista = null;
            $itens = [];
        };
        foreach (preg_split('/\R/', $texto) as $l) {
            $l = rtrim($l);
            if (preg_match('/^\s*[-•]\s+(.*)$/u', $l, $m)) {
                if ($lista !== 'ul') { $fechar(); $lista = 'ul'; }
                $itens[] = $linha($m[1]);
                continue;
            }
            if (preg_match('/^\s*\d+[.)]\s+(.*)$/u', $l, $m)) {
                if ($lista !== 'ol') { $fechar(); $lista = 'ol'; }
                $itens[] = $linha($m[1]);
                continue;
            }
            $fechar();
            if (trim($l) === '') {
                continue;
            }
            $ehPrint = (bool) preg_match('/^!\[[^\]]*\]\((?:media|assets\/img\/treinamento)\//', $l);
            if (!$ehPrint) {
                $continuar = 0; // qualquer outro bloco recomeça a numeração
            }
            if (preg_match('/^!\[([^\]]*)\]\(((?:media|assets\/img\/treinamento)\/[A-Za-z0-9_\/.\-]+)\)$/', $l, $m)) {
                $html[] = '<figure class="tr-print"><img src="' . e(url('/' . $m[2])) . '" alt="' . e($m[1]) . '" loading="lazy">' . ($m[1] !== '' ? '<figcaption>' . $linha($m[1]) . '</figcaption>' : '') . '</figure>';
            } elseif (preg_match('/^\{\{(pedido_minimo|condicoes_pagamento|produtos:[^}]+)\}\}$/u', $l, $m)) {
                $html[] = self::bloco($m[1]);
            } elseif (str_starts_with($l, '?? ')) {
                [$caso, $resposta] = array_pad(explode('||', substr($l, 3), 2), 2, '');
                $html[] = '<details class="tr-caso"><summary><span>🧩 Caso prático</span>' . $linha(trim($caso)) . '</summary><p>' . $linha(trim($resposta)) . '</p></details>';
            } elseif (str_starts_with($l, '### ')) {
                $html[] = '<h4>' . $linha(substr($l, 4)) . '</h4>';
            } elseif (str_starts_with($l, '## ')) {
                $html[] = '<h3>' . $linha(substr($l, 3)) . '</h3>';
            } elseif (str_starts_with($l, '> ')) {
                $html[] = '<p class="tr-destaque">' . $linha(substr($l, 2)) . '</p>';
            } elseif (str_starts_with($l, '! ')) {
                $html[] = '<p class="tr-atencao">' . $linha(substr($l, 2)) . '</p>';
            } else {
                $html[] = '<p>' . $linha($l) . '</p>';
            }
        }
        $fechar();
        return implode("\n", $html);
    }

    /** Blocos com os dados atuais do sistema (as regras mudam em Configurações e o treinamento acompanha) */
    private static function bloco(string $nome): string
    {
        if ($nome === 'pedido_minimo') {
            $linhas = Banco::todos('SELECT r.nome, r.pedido_minimo, (SELECT GROUP_CONCAT(ru.uf ORDER BY ru.uf SEPARATOR \', \') FROM regiao_ufs ru WHERE ru.regiao_id = r.id) ufs FROM regioes r ORDER BY r.pedido_minimo');
            return '<table class="tabela tr-tabela"><thead><tr><th>Região</th><th>Estados</th><th>Pedido mínimo</th></tr></thead><tbody>'
                . implode('', array_map(fn($r) => '<tr><td>' . e($r['nome']) . '</td><td class="suave">' . e((string) $r['ufs']) . '</td><td><b class="tr-regra">' . e(dinheiro((float) $r['pedido_minimo'])) . '</b></td></tr>', $linhas))
                . '</tbody></table>';
        }
        if ($nome === 'condicoes_pagamento') {
            $linhas = Banco::todos('SELECT c.nome, c.desconto_pct, c.valor_minimo, f.nome forma, f.bloqueia_com_atraso FROM condicoes_pagamento c JOIN formas_pagamento f ON f.id = c.forma_pagamento_id WHERE c.ativa = 1 AND f.ativa = 1 ORDER BY f.ordem, c.id');
            $regras = fn($c) => implode(' · ', array_filter([
                (float) $c['desconto_pct'] > 0 ? number_format((float) $c['desconto_pct'], 0, ',', '.') . '% de desconto' : '',
                (float) $c['valor_minimo'] > 0 ? 'pedido a partir de ' . dinheiro((float) $c['valor_minimo']) : '',
                $c['bloqueia_com_atraso'] ? 'bloqueado para cliente com título em atraso' : '',
            ])) ?: '—';
            return '<table class="tabela tr-tabela"><thead><tr><th>Condição</th><th>Forma</th><th>Regras</th></tr></thead><tbody>'
                . implode('', array_map(fn($c) => '<tr><td><b>' . e($c['nome']) . '</b></td><td>' . e($c['forma']) . '</td><td class="suave">' . e($regras($c)) . '</td></tr>', $linhas))
                . '</tbody></table>';
        }
        // produtos:Categoria → vitrine com os produtos da linha (do cadastro único)
        $categoria = trim(substr($nome, 9));
        $lista = array_values(array_filter(AcademiaService::produtos(), fn($p) => ($p['categoria'] ?? '') === $categoria));
        if (!$lista) {
            return '';
        }
        $html = '<div class="tr-produtos">';
        foreach (array_slice($lista, 0, 40) as $p) {
            $html .= '<a class="tr-produto" href="' . e(url('/academia/produtos/' . $p['slug'])) . '">'
                . (!empty($p['imagem']) ? '<img src="' . e($p['imagem']) . '" alt="" loading="lazy">' : '<span class="tr-produto-sem"></span>')
                . '<span><strong>' . e($p['nome']) . '</strong><small>' . e(trim(($p['embalagem'] ?? '') . ' · ' . ($p['chamada'] ?? ''), ' ·')) . '</small></span></a>';
        }
        return $html . '</div>' . (count($lista) > 40 ? '<p class="suave">E mais ' . (count($lista) - 40) . ' na <a href="' . e(url('/academia/produtos')) . '">Biblioteca de produtos</a>.</p>' : '');
    }

    /** Busca nos módulos (para o "Pergunte ao One" sem IA): [['titulo','resumo','link']] */
    public static function buscar(string $texto, int $limite = 3): array
    {
        $palavras = array_filter(preg_split('/\s+/u', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto))), fn($p) => mb_strlen($p) >= 4);
        if (!$palavras) {
            return [];
        }
        $pontos = [];
        foreach (Banco::todos('SELECT id, titulo, resumo, conteudo FROM trein_modulos WHERE ativo = 1') as $m) {
            $alvo = mb_strtolower($m['titulo'] . ' ' . $m['resumo'] . ' ' . $m['conteudo']);
            $tit = mb_strtolower($m['titulo']);
            $n = 0;
            foreach ($palavras as $p) {
                $n += substr_count($alvo, $p) + (str_contains($tit, $p) ? 5 : 0);
            }
            if ($n > 0) {
                $pontos[] = [$n, $m];
            }
        }
        usort($pontos, fn($a, $b) => $b[0] <=> $a[0]);
        return array_map(fn($x) => ['titulo' => $x[1]['titulo'], 'resumo' => $x[1]['resumo'] ?: mb_strimwidth(strip_tags(self::html($x[1]['conteudo'])), 0, 300, '…'), 'link' => '/treinamento/modulo/' . $x[1]['id']], array_slice($pontos, 0, $limite));
    }
}
