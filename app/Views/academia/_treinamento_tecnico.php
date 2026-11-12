<?php
/** Treinamento técnico completo de um produto (hoje: Ômega 3). Variável: $p (produto com technicalTraining) */
use App\Core\Visao;

$t = $p['technicalTraining'];
$lote = $p['lotReference'] ?? null;
$blocos = function (?array $lista): string {
    $html = '';
    foreach ($lista ?? [] as [$titulo, $paragrafos]) {
        $html .= '<div class="bloco-leitura"><h4>' . e($titulo) . '</h4>';
        foreach ($paragrafos as $par) {
            $html .= '<p>' . e($par) . '</p>';
        }
        $html .= '</div>';
    }
    return $html;
};
?>
<section class="academia-hero">
  <div>
    <span class="sobretitulo">TREINAMENTO COMPLETO DE PRODUTO · <?= e(mb_strtoupper($t['time'])) ?></span>
    <h2><?= e($t['title']) ?></h2>
    <p><?= e($t['lead']) ?></p>
    <a class="botao contorno claro pequeno" href="<?= url('/academia/produtos/' . e($p['slug'])) ?>">Abrir a ficha de <?= e($p['nome']) ?> →</a>
  </div>
  <?php if ($p['imagem']): ?><img class="hero-produto" src="<?= e($p['imagem']) ?>" alt="Embalagem de <?= e($p['nome']) ?>"><?php endif; ?>
</section>

<nav class="indice-capitulos card" aria-label="Capítulos">
  <strong>Neste treinamento</strong>
  <ol><?php foreach ($t['chapters'] as $i => $c): ?><li><a href="#cap-<?= $i ?>"><?= e($c['title']) ?></a></li><?php endforeach; ?></ol>
</nav>

<?php foreach ($t['chapters'] as $i => $c): ?>
  <section class="card capitulo" id="cap-<?= $i ?>">
    <span class="sobretitulo">CAPÍTULO <?= $i + 1 ?></span>
    <h2><?= e($c['title']) ?></h2>
    <?php if (!empty($c['lead'])): ?><p class="capitulo-chamada"><?= e($c['lead']) ?></p><?php endif; ?>
    <?= $blocos($c['blocks'] ?? []) ?>

    <?php if (!empty($c['lot']) && $lote): ?>
      <div class="tabela-rolagem"><table class="tabela tabela-tecnica">
        <caption>Lote de referência <?= e($lote['id']) ?> · quantidades de ácidos graxos</caption>
        <thead><tr><th>Componente</th><th>1 cápsula</th><th>2 cápsulas</th></tr></thead>
        <tbody><?php foreach ($lote['rows'] as $r): ?><tr><th><?= e($r[0]) ?></th><td><?= e($r[1]) ?></td><td><?= e($r[2]) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
      <p class="pequeno suave">Os valores de 2 cápsulas são calculados a partir dos valores por cápsula. EPA e DHA já fazem parte do ômega 3 total; não some as linhas como se fossem componentes separados.</p>
    <?php endif; ?>

    <?php if (!empty($c['quality']) && $lote): ?>
      <div class="grade-3"><?php foreach ($lote['quality'] as [$nome, $valor, $sentido]): ?><div class="bloco-claro"><small><?= e($nome) ?></small><strong class="valor-grande"><?= e($valor) ?></strong><p><?= e($sentido) ?></p></div><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if (!empty($c['comparison'])): ?>
      <div class="tabela-rolagem"><table class="tabela tabela-tecnica">
        <caption>Critérios para comparar o produto</caption>
        <thead><tr><th>Característica</th><th>Como comparar</th><th>Cuidado na explicação</th></tr></thead>
        <tbody><?php foreach ($c['comparison'] as [$a, $b, $d]): ?><tr><th><?= e($a) ?></th><td><?= e($b) ?></td><td><?= e($d) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>

    <?php if (!empty($c['language'])): ?>
      <div class="linguagem"><?php foreach ($c['language'] as [$evite, $use]): ?>
        <div><p class="evite"><span>Evite prometer</span><?= e($evite) ?></p><p class="use"><span>Explique assim</span><?= e($use) ?></p></div>
      <?php endforeach; ?></div>
    <?php endif; ?>

    <?php if (!empty($c['qa'])): ?>
      <?php foreach ($c['qa'] as [$pergunta, $paragrafos]): ?>
        <details class="faq"><summary><?= e($pergunta) ?></summary><?php foreach ($paragrafos as $par): ?><p><?= e($par) ?></p><?php endforeach; ?></details>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($c['bullets'])): ?><ul class="lista-aula"><?php foreach ($c['bullets'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul><?php endif; ?>

    <?php foreach ($c['messages'] ?? [] as $msg): ?>
      <?= Visao::renderizar('academia/_mensagem', ['titulo' => $msg['title'], 'dica' => $msg['when'] ?? '', 'texto' => $msg['message']]) ?>
    <?php endforeach; ?>

    <?= $blocos($c['afterBlocks'] ?? []) ?>
    <?php if (!empty($c['takeaway'])): ?><p class="nota-destaque"><strong>Leve para o atendimento: </strong><?= e($c['takeaway']) ?></p><?php endif; ?>
  </section>
<?php endforeach; ?>

<section class="card">
  <span class="sobretitulo">PRATIQUE A CONVERSA COMPLETA</span>
  <p>Escolha uma situação, simule o atendimento e depois confira a explicação. Combine composição, dúvida do cliente e próximo passo.</p>
  <?php foreach ($t['practice'] as [$situacao, $resposta]): ?>
    <details class="faq"><summary><?= e($situacao) ?></summary><p><?= e($resposta) ?></p></details>
  <?php endforeach; ?>
</section>
