<?php
/**
 * Link do orçamento já respondido, vencido, cancelado ou inexistente.
 * Variáveis: $fim (aprovado | recusado | expirado | cancelado | nao_encontrado), $o (ou null)
 */
use App\Core\Config;

$whats = preg_replace('/\D/', '', (string) Config::get('empresa.whatsapp', ''));
$textos = [
    'aprovado' => ['Orçamento aprovado! 🎉', 'Recebemos o seu pedido' . (!empty($o['pedido_id']) ? ' <strong>#' . (int) $o['pedido_id'] . '</strong>' : '') . '. A nossa equipe vai conferir e você recebe a nota fiscal assim que for faturado.'],
    'recusado' => ['Tudo bem, recebemos a sua resposta', 'Obrigado por avisar. O seu vendedor já ficou sabendo e pode preparar outra proposta.'],
    'expirado' => ['Este orçamento venceu', 'Os preços podem ter mudado. Peça um orçamento novo para o seu vendedor.'],
    'cancelado' => ['Este orçamento foi cancelado', 'Provavelmente o seu vendedor mandou um orçamento mais novo. Se tiver dúvida, fale com ele.'],
    'nao_encontrado' => ['Orçamento não encontrado', 'Confira se o link está completo, ou peça um novo para o seu vendedor.'],
];
[$t, $texto] = $textos[$fim] ?? $textos['nao_encontrado'];
?>
<section class="card orc-fim orc-fim-<?= e($fim) ?>" data-fim="<?= e($fim) ?>">
  <?php if ($o): ?><span class="orc-selo">ORÇAMENTO #<?= (int) $o['id'] ?></span><?php endif; ?>
  <h1><?= e($t) ?></h1>
  <p><?= $texto /* textos fixos acima, sem dado digitado */ ?></p>
  <?php if ($o && $o['vendedor']): ?><p class="suave">Atendimento: <?= e($o['vendedor']) ?></p><?php endif; ?>
  <p class="orc-fim-acoes">
    <a class="botao contorno" href="<?= url('/catalogo') ?>">Ver o catálogo</a>
    <?php if ($whats): ?><a class="botao" href="https://wa.me/55<?= e($whats) ?>" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a><?php endif; ?>
  </p>
</section>
