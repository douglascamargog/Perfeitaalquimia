<?php
/** Perfil das pessoas e mural de aniversário (DEMONSTRAÇÃO): perfis preenchidos e parabéns para quem faz aniversário hoje. */
use App\Core\Banco;

$perfis = [
    'televendas1@demo.local' => ['sobre_mim' => 'Trabalho com televendas há 6 anos. Adoro conhecer as lojas pelo telefone e ajudar cada cliente a montar o pedido certo.',
        'hobbies' => 'Corrida no parque, cozinhar no fim de semana', 'comida' => 'Lasanha da vó', 'musica' => 'MPB e samba', 'filme_serie' => 'The Office',
        'time_futebol' => 'Internacional', 'lugar_favorito' => 'Gramado no inverno', 'familia' => 'Casada, mãe do Pedro (4 anos)', 'pets' => 'A Mel, uma beagle comilona'],
    'externo1@demo.local' => ['sobre_mim' => 'Rodo o RS visitando lojas. Se tem uma loja de produtos naturais na serra, eu já tomei um café lá.',
        'hobbies' => 'Trilhas de bike e fotografia', 'comida' => 'Churrasco', 'musica' => 'Rock gaúcho', 'time_futebol' => 'Grêmio', 'lugar_favorito' => 'Cambará do Sul'],
    'gestor@demo.local' => ['sobre_mim' => 'Cuido da equipe comercial. Minha porta (e meu WhatsApp) estão sempre abertos.',
        'hobbies' => 'Futebol com os amigos às quartas', 'comida' => 'Sushi', 'filme_serie' => 'Senhor dos Anéis', 'time_futebol' => 'Internacional',
        'familia' => 'Pai da Laura e do Miguel', 'pets' => 'O Thor, vira-lata folgado'],
    'marketing@demo.local' => ['sobre_mim' => 'Cuido das redes, do site e das campanhas. Me mande foto dos produtos na loja dos clientes!',
        'hobbies' => 'Design, plantas e cafés especiais', 'musica' => 'Indie', 'filme_serie' => 'Ted Lasso', 'lugar_favorito' => 'Uma livraria com café'],
];
$n = 0;
foreach ($perfis as $email => $dados) {
    $id = (int) Banco::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
    if ($id && !Banco::valor('SELECT 1 FROM usuario_perfil WHERE usuario_id = ?', [$id])) {
        Banco::inserir('usuario_perfil', ['usuario_id' => $id] + $dados);
        $n++;
    }
}
// Parabéns para quem faz aniversário hoje (a demonstração da equipe já tem alguém fazendo aniversário hoje)
$msgs = ['Feliz aniversário! Muita saúde e muitas vendas! 🎉', 'Parabéns!! Que o seu dia seja incrível 🎂', 'Felicidades! Obrigado por tudo que você faz pela equipe.'];
$mensagens = 0;
foreach (Banco::todos("SELECT id FROM usuarios WHERE ativo = 1 AND DATE_FORMAT(data_nascimento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')") as $aniv) {
    $autores = Banco::todos('SELECT id FROM usuarios WHERE ativo = 1 AND id <> ? ORDER BY id LIMIT 3', [$aniv['id']]);
    foreach ($autores as $i => $a) {
        Banco::inserir('aniversario_mensagens', ['aniversariante_id' => $aniv['id'], 'ano' => (int) date('Y'), 'autor_id' => $a['id'], 'texto' => $msgs[$i % 3],
            'criado_em' => date('Y-m-d') . ' 0' . (8 + $i) . ':1' . $i . ':00']);
        $mensagens++;
    }
}
return "Equipe (demonstração): $n perfis públicos e $mensagens mensagem(ns) de parabéns.";
