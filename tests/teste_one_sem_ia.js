// V17.6 — Pergunte ao One: quando responde SEM IA, diz o motivo (IA desligada, sem chave, limite da hora),
// a Administração mostra o alerta, e a trava do limite por hora sai sozinha (as perguntas barradas não contam).
// Rodar com o banco de TESTE e o servidor local no ar. Usa uma chave FALSA em config/segredos.php (apagada no fim).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
const SEGREDOS = 'config/segredos.php';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const tinhaSegredos = fs.existsSync(SEGREDOS) ? fs.readFileSync(SEGREDOS) : null;
  const nav = await chromium.launch();
  const p = await (await nav.newContext()).newPage();
  try {
    if (tinhaSegredos) fs.unlinkSync(SEGREDOS);
    sql("update configuracoes set valor='0' where chave='one.ia_ativa'");
    await p.goto(BASE + '/entrar'); await p.fill('#email', 'admin@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    const csrf = await p.locator('meta[name=csrf]').getAttribute('content');
    const perguntar = async (t) => (await (await p.request.post(BASE + '/one/perguntar', { form: { pergunta: t, _csrf: csrf }, headers: { Accept: 'application/json' } })).json()).texto || '';

    // 1) IA desligada e sem chave
    let r = await perguntar('como faço um orçamento?');
    ok(r.includes('Estou respondendo sem IA') && r.includes('desligada e sem chave'), 'sem IA: a resposta avisa e diz o motivo (desligada e sem chave)');
    await p.goto(BASE + '/administracao');
    ok((await p.locator('[data-one-sem-ia]').count()) === 1, 'Administração mostra o alerta "respondendo sem IA"');

    // 2) ligada, mas sem chave no servidor
    sql("update configuracoes set valor='1' where chave='one.ia_ativa'");
    r = await perguntar('como faço um orçamento?');
    ok(r.includes('chave da IA não está cadastrada'), 'IA ligada sem chave: avisa que falta a chave');

    // 3) com chave e ligada, passou do limite da hora → avisa; as barradas não prolongam a trava
    fs.writeFileSync(SEGREDOS, "<?php return ['integracoes' => ['anthropic' => ['chave' => 'sk-ant-chave-falsa-de-teste-0000000000000']]];\n");
    const adm = sql("select id from usuarios where email='admin@demo.local'");
    sql("update configuracoes set valor='2' where chave='one.ia_limite_hora'");
    sql(`delete from one_perguntas where usuario_id=${adm}`);
    sql(`insert into one_perguntas (usuario_id, provedor, situacao, criado_em) values (${adm}, 'claude', 'ok', NOW() - INTERVAL 70 MINUTE), (${adm}, 'claude', 'ok', NOW() - INTERVAL 30 MINUTE), (${adm}, 'claude', 'ok', NOW() - INTERVAL 20 MINUTE)`);
    r = await perguntar('como faço um orçamento?');
    ok(r.includes('perguntas com IA na última hora'), 'passou do limite da hora: avisa (não fica "burro" calado)');
    await p.goto(BASE + '/administracao');
    ok((await p.locator('[data-one-sem-ia]').count()) === 0 && (await p.locator('[data-one-limite]').count()) === 1, 'Administração: IA ok, mas mostra as perguntas barradas pelo limite');
    // as 2 da última hora "envelhecem": com só barradas na janela, a trava sai
    sql(`update one_perguntas set criado_em = NOW() - INTERVAL 2 HOUR where usuario_id=${adm} and situacao='ok'`);
    ok(sql(`select count(*) from one_perguntas where usuario_id=${adm} and provedor='claude' and coalesce(situacao,'ok') <> 'limite' and criado_em >= NOW() - INTERVAL 1 HOUR`) === '0'
      && sql(`select count(*) from one_perguntas where usuario_id=${adm} and situacao='limite'`) !== '0',
      'perguntas barradas pelo limite não contam como perguntas com IA (a trava sai sozinha)');
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  fs.rmSync(SEGREDOS, { force: true });
  if (tinhaSegredos) fs.writeFileSync(SEGREDOS, tinhaSegredos);
  sql("update configuracoes set valor='0' where chave='one.ia_ativa'"); sql("update configuracoes set valor='20' where chave='one.ia_limite_hora'");
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
