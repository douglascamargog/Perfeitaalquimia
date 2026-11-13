<?php
/**
 * DEMONSTRAÇÃO do Marketing (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 * - 3 campanhas (mês atual, ofertas de novembro e próximo mês)
 * - ~22 posts no mês atual em todas as etapas, com as fotos dos produtos da Academia
 * - 13 meses de e-commerce simulado (TrayFake)
 */
use App\Core\Banco;
use App\Services\EcommerceService;

$etapa = fn(string $tipo) => (int) Banco::valor('SELECT id FROM mkt_post_etapas WHERE tipo = ? ORDER BY ordem LIMIT 1', [$tipo]);
$autor = (int) (Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'marketing' ORDER BY u.id LIMIT 1") ?: 1);
$aprovador = (int) (Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'admin' ORDER BY u.id LIMIT 1") ?: 1);
$produtos = Banco::todos('SELECT id, slug, nome, imagem_id FROM produtos WHERE imagem_id IS NOT NULL ORDER BY id LIMIT 40');
if (!$produtos) {
    return 'Marketing (demonstração): sem produtos com foto — posts não criados.';
}

$mes = date('Y-m');
$hoje = date('Y-m-d');
$fimMes = date('Y-m-t');
$proximoMes = date('Y-m', strtotime("$mes-01 +1 month"));
$bf = date('Y-m-d', strtotime('fourth thursday of november ' . date('Y') . ' +1 day'));

// ---------- E-commerce simulado (primeiro: as campanhas usam os produtos que mais vendem no site) ----------
$r = EcommerceService::sincronizar();
$topo = fn(string $mesRef, int $pos) => Banco::valor('SELECT p.id FROM ecommerce_produtos_mes e JOIN produtos p ON p.slug = e.produto_ref
    WHERE e.mes = ? ORDER BY e.faturamento DESC LIMIT 1 OFFSET ' . $pos, [$mesRef]);

// ---------- Campanhas ----------
$campanhas = [
    ['Imunidade em alta', 'Aumentar a venda da linha de imunidade no site', "$mes-01", "$mes-20", '#38bdf8', 'Vender 60 un do produto destaque no site', 1500, $topo($mes, 0), 'ativa'],
    ['Ofertas de Novembro', 'Maior campanha do ano: site e lojistas', date('Y-m-d', strtotime("$bf -10 days")), date('Y-m-d', strtotime("$bf +3 days")), '#14251b', 'R$ 120 mil no e-commerce na semana', 6000, $topo($mes, 1), 'planejada'],
    ['Verão com energia', 'Reposição e energia para o calor', "$proximoMes-05", date('Y-m-t', strtotime("$proximoMes-01")), '#f59e0b', 'Crescer 15% no ticket médio', 2500, null, 'planejada'],
];
$idsCampanha = [];
foreach ($campanhas as [$nome, $objetivo, $inicio, $fim, $cor, $meta, $orcamento, $produto, $situacao]) {
    $idsCampanha[] = Banco::inserir('mkt_campanhas', ['nome' => $nome, 'objetivo' => $objetivo, 'inicio' => $inicio, 'fim' => $fim, 'cor' => $cor,
        'meta_descricao' => $meta, 'orcamento' => $orcamento, 'produto_id' => $produto, 'situacao' => $situacao, 'criado_por' => $autor]);
}

// ---------- Posts do mês ----------
$formatos = ['reels', 'feed', 'stories', 'carrossel', 'feed', 'reels', 'stories'];
$ganchos = ['Você sabia?', '3 benefícios de', 'Rotina saudável com', 'Mitos e verdades:', 'Bastidores da fábrica:', 'Depoimento de lojista:', 'Como tomar'];
$total = 0;
$temAjuste = false;
for ($dia = 1, $i = 0; $dia <= (int) date('t'); $dia += ($i % 3 === 2 ? 2 : 1), $i++) {
    $data = sprintf('%s-%02d', $mes, $dia);
    $pr = $produtos[$i % count($produtos)];
    $formato = $formatos[$i % count($formatos)];
    $nomeCurto = trim(preg_replace('/^\d+\+?\s*/', '', $pr['nome']));
    // Etapa conforme a data: passado = publicado; próximos dias = agendado/aprovado/aprovação; depois = produção/ideia
    $diasAte = (int) round((strtotime($data) - strtotime($hoje)) / 86400);
    $tipo = match (true) {
        $diasAte < 0              => 'publicado',
        $diasAte <= 2             => 'agendado',
        $diasAte <= 5             => ($i % 2 ? 'aprovado' : 'aprovacao'),
        $diasAte <= 8             => ($i % 4 === 0 ? 'ajuste' : 'aprovacao'),
        $diasAte <= 14            => 'producao',
        default                   => 'rascunho',
    };
    if ($tipo === 'aprovacao' && $diasAte >= 6 && !$temAjuste) {
        $tipo = 'ajuste'; // pelo menos um post com ajuste pedido, para mostrar o fluxo
    }
    $temAjuste = $temAjuste || $tipo === 'ajuste';
    $aprovado = in_array($tipo, ['publicado', 'agendado', 'aprovado'], true);
    $id = Banco::inserir('mkt_posts', [
        'campanha_id' => $data <= "$mes-20" ? $idsCampanha[0] : ($data >= date('Y-m-d', strtotime("$bf -10 days")) ? $idsCampanha[1] : null),
        'formato' => $formato, 'titulo' => $ganchos[$i % count($ganchos)] . ' ' . $nomeCurto,
        'legenda' => $tipo === 'rascunho' ? null : "{$ganchos[$i % count($ganchos)]} {$nomeCurto} 🌿\n\nFórmula desenvolvida pela Perfeita Alquimia para o seu dia a dia. Converse com o seu lojista de confiança ou compre no nosso site (link na bio).\n\nEste produto não é um medicamento.",
        'hashtags' => '#perfeitaalquimia #suplementos #saudenatural #bemestar',
        'data_publicacao' => $data, 'hora' => ['09:00', '12:00', '18:30', '20:00'][$i % 4], 'etapa_id' => $etapa($tipo),
        'responsavel_id' => $autor, 'midia_id' => $pr['imagem_id'], 'criado_por' => $autor,
        'link_publicado' => $tipo === 'publicado' ? 'https://www.instagram.com/p/demo' . $i . '/' : null,
        'aprovado_por' => $aprovado ? $aprovador : null, 'aprovado_em' => $aprovado ? date('Y-m-d H:i:s', strtotime("$data -2 days")) : null,
        'motivo_ajuste' => $tipo === 'ajuste' ? 'Trocar a foto por uma com fundo claro e encurtar a primeira frase da legenda.' : null,
    ]);
    Banco::inserir('mkt_post_comentarios', ['post_id' => $id, 'usuario_id' => $autor, 'tipo' => 'etapa', 'texto' => 'Post criado.']);
    if ($tipo === 'ajuste') {
        Banco::inserir('mkt_post_comentarios', ['post_id' => $id, 'usuario_id' => $aprovador, 'tipo' => 'ajuste', 'texto' => 'Pediu ajuste: Trocar a foto por uma com fundo claro e encurtar a primeira frase da legenda.']);
    }
    $total++;
}

return "Marketing (demonstração): 3 campanhas, $total posts no mês e {$r['dias']} dias de e-commerce simulado.";
