<?php
/**
 * DEMONSTRAÇÃO do Marketing por funções (só na instalação nova):
 *  - "Marketing (demo)" vira Gestor de Marketing (aprova o Marketing);
 *  - pessoas fictícias: "Social Media (demo)" e "E-commerce (demo)";
 *  - 10 tarefas em situações variadas, com pedidos da Televendas e da Produção.
 */
use App\Core\Banco;

if (Banco::valor('SELECT 1 FROM mkt_tarefas LIMIT 1')) {
    return null;
}
$perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'marketing'");
$funcao = fn(string $slug) => (int) Banco::valor('SELECT id FROM funcoes WHERE slug = ?', [$slug]);
$usuario = function (string $email, string $nome, string $slug) use ($perfil, $funcao): int {
    $id = (int) Banco::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
    if (!$id && $perfil) {
        $id = Banco::inserir('usuarios', ['nome' => $nome, 'email' => $email, 'senha_hash' => password_hash('Demo@2026', PASSWORD_DEFAULT), 'perfil_id' => $perfil]);
    }
    if ($id && $funcao($slug)) {
        Banco::executar('INSERT IGNORE INTO usuario_funcoes (usuario_id, funcao_id) VALUES (?, ?)', [$id, $funcao($slug)]);
    }
    return $id;
};
$gestor = $usuario('marketing@demo.local', 'Marketing (demo)', 'gestor_marketing');
Banco::executar("INSERT IGNORE INTO usuario_permissoes (usuario_id, permissao, concedida) VALUES (?, 'marketing.aprovar', 1)", [$gestor]);
$social = $usuario('social@demo.local', 'Social Media (demo)', 'social_media');
$ecom = $usuario('ecommerce@demo.local', 'E-commerce (demo)', 'ecommerce');
$tele = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'televendas1@demo.local'");
$prod = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'producao@demo.local'") ?: $tele;
$tipo = fn(string $inicio) => (int) Banco::valor('SELECT id FROM mkt_tipos WHERE nome LIKE ? ORDER BY id LIMIT 1', [$inicio . '%']);
$dia = fn(int $n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));

// [título, tipo, função, situação, prazo (dias), responsável, solicitante, setor, urgente]
$tarefas = [
    ['Arte da promoção de Magnésio para o WhatsApp', 'Arte', 'social_media', 'fazendo', 1, $social, $tele, 'Televendas', 1],
    ['Carrossel "5 benefícios da Berberina"', 'Arte', 'social_media', 'a_fazer', 4, null, $gestor, null, 0],
    ['Banner da feira de produtos naturais', 'Material', 'social_media', 'aprovacao', 2, $social, $tele, 'Televendas', 0],
    ['Folder com a linha de cápsulas', 'Material', 'social_media', 'a_fazer', -1, null, $tele, 'Televendas', 0],
    ['Rótulo do novo Colágeno', 'Rótulo', 'gestor_marketing', 'fazendo', 6, $gestor, $prod, 'Produção', 0],
    ['Responder perguntas do Mercado Livre (semana)', 'Pergunta', 'ecommerce', 'fazendo', 0, $ecom, null, null, 0],
    ['Oferta relâmpago Shopee — Ômega 3', 'Campanha', 'ecommerce', 'a_fazer', 3, null, $gestor, null, 0],
    ['Envio FULL Mercado Livre — reposição de novembro', 'Envio FULL', 'ecommerce', 'aprovacao', 2, $ecom, null, null, 1],
    ['Cadastrar NAC e Reumax na Shopee', 'Cadastrar', 'ecommerce', 'feito', -2, $ecom, $gestor, null, 0],
    ['Post do Dia do Farmacêutico', 'Arte', 'social_media', 'feito', -3, $social, null, null, 0],
];
foreach ($tarefas as [$titulo, $t, $func, $sit, $prazo, $resp, $solic, $setor, $urg]) {
    $criado = date('Y-m-d H:i:s', strtotime('-' . random_int(2, 9) . ' days'));
    $id = Banco::inserir('mkt_tarefas', array_filter([
        'titulo' => $titulo, 'tipo_id' => $tipo($t), 'funcao_slug' => $func, 'situacao' => $sit, 'prazo' => $dia($prazo),
        'responsavel_id' => $resp, 'solicitante_id' => $solic ?: $gestor, 'setor' => $setor, 'urgente' => $urg, 'criado_em' => $criado,
        'iniciado_em' => in_array($sit, ['fazendo', 'aprovacao', 'feito'], true) ? $criado : null,
        'concluido_em' => $sit === 'feito' ? date('Y-m-d H:i:s', strtotime('-1 day')) : null,
        'descricao' => $setor ? "Pedido da $setor. Usar as cores da marca e o logo novo." : null,
    ], fn($v) => $v !== null));
    Banco::inserir('mkt_tarefa_historico', ['tarefa_id' => $id, 'usuario_id' => $solic ?: $gestor, 'texto' => $setor ? "Pedido feito ($setor)." : 'Tarefa criada.', 'criado_em' => $criado]);
}
return 'Marketing (demonstração): Gestor de Marketing, Social Media e E-commerce com 10 tarefas.';
