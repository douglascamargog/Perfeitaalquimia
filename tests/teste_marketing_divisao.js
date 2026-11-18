// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh marketing_divisao
// V16.0: fim do "quem pegar primeiro" no Marketing. Cada função (Social Media, E-commerce e Marketplaces, Gestor) tem a sua
// pessoa (Início do Marketing › Quem cuida de quê); o pedido já nasce com dono, só ele é avisado e só ele começa/conclui.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  // Duas pessoas no Social Media e uma no E-commerce (perfil Marketing)
  const perfil = sql("select id from perfis where slug='marketing'");
  const pessoa = (nome, email, funcao) => {
    sql(`insert into usuarios (nome, email, senha_hash, perfil_id, ativo, versao_vista, tutorial_ativo) select '${nome}', '${email}', senha_hash, ${perfil}, 1, versao_vista, 0 from usuarios where email='marketing@demo.local'`);
    const id = sql(`select id from usuarios where email='${email}'`);
    sql(`insert into usuario_funcoes (usuario_id, funcao_id) select ${id}, id from funcoes where slug='${funcao}'`);
    return id;
  };
  sql("delete uf from usuario_funcoes uf join funcoes f on f.id = uf.funcao_id where f.slug in ('social_media','ecommerce')");
  const sm1 = pessoa('Social Um', 'social1@demo.local', 'social_media');
  const sm2 = pessoa('Social Dois', 'social2@demo.local', 'social_media');
  const eco = pessoa('Ecommerce Um', 'ecom1@demo.local', 'ecommerce');
  sql("update mkt_tipos set ativo = 1 where funcao_slug = 'ecommerce' limit 1");
  const tipoSocial = sql("select id from mkt_tipos where funcao_slug='social_media' and ativo=1 order by id limit 1");
  const tipoEco = sql("select id from mkt_tipos where funcao_slug='ecommerce' and ativo=1 order by id limit 1");
  sql('delete from notificacoes');

  const b = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const p = await (await b.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  try {
    // Gestor escolhe quem cuida do Social Media
    const a = await entrar('admin@demo.local');
    await a.goto(BASE + '/marketing/inicio');
    ok(await a.locator('[data-divisao] [data-funcao-mkt="social_media"]').count() === 1 && await a.locator('[data-divisao] [data-funcao-mkt="ecommerce"]').count() === 1,
      'Início do Marketing mostra "Quem cuida de quê" (Social Media e E-commerce)');
    await Promise.all([a.waitForNavigation(), a.selectOption('[data-funcao-mkt="social_media"] select', sm2)]);
    ok(sql("select valor from configuracoes where chave='marketing.responsavel.social_media'") === sm2, 'gestor escolhe quem cuida do Social Media');

    // Pedido de outro setor
    const v = await entrar('televendas1@demo.local');
    await v.goto(BASE + '/marketing/pedir');
    const tok = await v.locator('meta[name=csrf]').getAttribute('content');
    await v.request.post(BASE + '/marketing/tarefas', { form: { _csrf: tok, pedido: '1', titulo: 'Post da promoção', tipo_id: tipoSocial } });
    await v.request.post(BASE + '/marketing/tarefas', { form: { _csrf: tok, pedido: '1', titulo: 'Cadastrar kit na Shopee', tipo_id: tipoEco } });
    const tSocial = sql("select concat(id, '|', responsavel_id) from mkt_tarefas where titulo='Post da promoção'").split('|');
    const tEco = sql("select concat(id, '|', responsavel_id) from mkt_tarefas where titulo='Cadastrar kit na Shopee'").split('|');
    ok(tSocial[1] === sm2 && tEco[1] === eco, 'cada pedido já nasce com dono: Social Media → quem foi escolhido; e-commerce → quem cuida do e-commerce');
    const avisos = (u) => sql(`select count(*) from notificacoes where usuario_id=${u} and tipo='mkt_tarefa'`);
    ok(avisos(sm2) === '1' && avisos(sm1) === '0' && avisos(eco) === '1', 'só a dona é avisada (o outro do Social Media não recebe)');

    // Quadro "minhas" de cada um
    const s1 = await entrar('social1@demo.local');
    await s1.goto(BASE + '/marketing/inicio');
    const q1 = await s1.locator('.mt-quadro').innerText();
    ok(!q1.includes('Post da promoção') && !q1.includes('Cadastrar kit na Shopee'), '"Minhas tarefas" de outra pessoa do Social Media não mostra a tarefa da colega');
    const e1 = await entrar('ecom1@demo.local');
    await e1.goto(BASE + '/marketing/inicio');
    const qe = await e1.locator('.mt-quadro').innerText();
    ok(qe.includes('Cadastrar kit na Shopee') && !qe.includes('Post da promoção'), 'quem cuida do e-commerce vê só as tarefas do e-commerce');

    // Só a dona começa
    await s1.goto(BASE + '/marketing/tarefas/' + tSocial[0]);
    ok(await s1.locator('[data-acao="comecar"]').count() === 0 && await s1.locator('[data-tarefa-de-outra]').count() === 1, 'outra pessoa vê "Esta tarefa é de ..." e não tem o botão Começar');
    const r = await s1.request.post(BASE + `/marketing/tarefas/${tSocial[0]}/mudar`, { form: { _csrf: await s1.locator('meta[name=csrf]').getAttribute('content'), acao: 'comecar' } });
    ok(sql(`select concat(situacao, '|', responsavel_id) from mkt_tarefas where id=${tSocial[0]}`) === `a_fazer|${sm2}`, 'e não consegue "pegar" a tarefa pela outra porta');
    const s2 = await entrar('social2@demo.local');
    await s2.goto(BASE + '/marketing/tarefas/' + tSocial[0]);
    await Promise.all([s2.waitForNavigation(), s2.click('[data-acao="comecar"]')]);
    ok(sql(`select situacao from mkt_tarefas where id=${tSocial[0]}`) === 'fazendo', 'a dona começa a tarefa');

    // Comentário de quem pediu → só a dona
    await v.request.post(BASE + `/marketing/tarefas/${tEco[0]}/comentar`, { form: { _csrf: tok, texto: 'Pode ser até sexta?' } });
    ok(sql(`select count(*) from notificacoes where tipo='mkt_comentario' and usuario_id=${eco}`) === '1' && sql(`select count(*) from notificacoes where tipo='mkt_comentario' and usuario_id in (${sm1},${sm2})`) === '0',
      'comentário de quem pediu avisa só quem cuida da tarefa');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
