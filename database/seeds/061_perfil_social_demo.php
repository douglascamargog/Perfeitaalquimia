<?php
/**
 * Perfil das pessoas (V11.0) — DEMONSTRAÇÃO (só instalação nova): status, frases, cores, capas,
 * interesses, recados e elogios entre os usuários de exemplo.
 */
use App\Core\Banco;

$id = fn(string $email) => (int) Banco::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
$perfis = [
    'televendas1@demo.local' => ['☕', 'focada em ligações de recompra hoje', 'Cliente bem atendido sempre volta 😉', '#c2185b', 'por_do_sol', 'café, corrida, séries de suspense', 'fitoterápicos, objeções de preço'],
    'televendas2@demo.local' => ['🎯', 'faltam 3 pedidos para a meta!', 'Um sim de cada vez.', '#13678a', 'oceano', 'futebol, churrasco, podcasts', 'Excel, rotas de entrega'],
    'externo1@demo.local'    => ['🚗', 'na rua: Canoas e Esteio', 'A estrada ensina mais que qualquer curso.', '#2a9d8f', 'floresta', 'viagens, fotografia, trilhas', 'cidades do interior, negociação'],
    'gestor@demo.local'      => ['🧠', 'planejando a semana da equipe', 'Time que se ajuda bate meta junto.', '#264653', 'noite', 'liderança, xadrez, ciclismo', 'metas, funil de vendas'],
    'marketing@demo.local'   => ['🚀', 'lançando a campanha de verão', 'Criatividade é inteligência se divertindo.', '#7b5ea7', 'lavanda', 'design, música indie, gatos', 'Instagram, Canva, campanhas'],
    'producao@demo.local'    => ['📦', 'produção a todo vapor', 'Qualidade não é acaso.', '#e76f51', 'areia', 'culinária, jardinagem', 'fichas técnicas, matérias-primas'],
];
$horas = date('Y-m-d H:i:s', time() + 20 * 3600);
foreach ($perfis as $email => [$emoji, $status, $frase, $cor, $capa, $interesses, $pergunte]) {
    if (!($uid = $id($email))) {
        continue;
    }
    Banco::executar('INSERT INTO usuario_perfil (usuario_id, status_emoji, status_texto, status_ate, frase, cor_perfil, capa_tema, interesses, pergunte_sobre)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status_emoji = VALUES(status_emoji), status_texto = VALUES(status_texto), status_ate = VALUES(status_ate),
        frase = VALUES(frase), cor_perfil = VALUES(cor_perfil), capa_tema = VALUES(capa_tema), interesses = VALUES(interesses), pergunte_sobre = VALUES(pergunte_sobre)',
        [$uid, $emoji, $status, $horas, $frase, $cor, $capa, $interesses, $pergunte]);
}
$elogios = [
    ['televendas1@demo.local', 'gestor@demo.local', 'arrasou_na_venda', 'Fechou o maior pedido da semana!', 2],
    ['televendas1@demo.local', 'televendas2@demo.local', 'ajudou_muito', 'Me ajudou com a objeção de prazo. Valeu!', 5],
    ['televendas1@demo.local', 'externo1@demo.local', 'otimo_trabalho_em_equipe', null, 9],
    ['televendas1@demo.local', 'marketing@demo.local', 'deixa_o_dia_mais_leve', null, 12],
    ['televendas2@demo.local', 'televendas1@demo.local', 'salvou_o_dia', 'Cobriu minhas ligações quando eu estava no médico.', 3],
    ['marketing@demo.local', 'gestor@demo.local', 'ideia_brilhante', 'A campanha de recompra deu muito certo.', 6],
    ['producao@demo.local', 'externo1@demo.local', 'salvou_o_dia', 'Produziu o pedido urgente da Drogaria Vital em 1 dia.', 4],
];
foreach ($elogios as [$para, $de, $tipo, $texto, $dias]) {
    if ($id($para) && $id($de)) {
        Banco::inserir('perfil_elogios', ['para_id' => $id($para), 'de_id' => $id($de), 'tipo' => $tipo, 'texto' => $texto, 'criado_em' => date('Y-m-d H:i:s', strtotime("-$dias days 10:30"))]);
    }
}
$recados = [
    ['televendas1@demo.local', 'televendas2@demo.local', 'Bora bater essa meta juntas esse mês! 💪', 1],
    ['televendas1@demo.local', 'producao@demo.local', 'Obrigado pelo bolo de aniversário da semana passada 🎂', 6],
    ['gestor@demo.local', 'televendas1@demo.local', 'Reunião de sexta foi ótima, obrigada pelas dicas!', 2],
    ['externo1@demo.local', 'gestor@demo.local', 'Boa viagem para o interior. Manda foto dos clientes novos!', 3],
];
foreach ($recados as [$perfil, $autor, $texto, $dias]) {
    if ($id($perfil) && $id($autor)) {
        Banco::inserir('perfil_recados', ['perfil_id' => $id($perfil), 'autor_id' => $id($autor), 'texto' => $texto, 'criado_em' => date('Y-m-d H:i:s', strtotime("-$dias days 15:10"))]);
    }
}
return 'Perfis com status, frases, cores, recados e elogios de exemplo.';
