// Teste de carga: N pessoas logadas usando o One ao mesmo tempo.
// uso: node carga.js <segundos> <pausaMin> <pausaMax> <arquivo-resultado>
const BASE = 'http://127.0.0.1:8000';
const [SEG, PMIN, PMAX, SAIDA] = [Number(process.argv[2] || 120), Number(process.argv[3] || 5), Number(process.argv[4] || 15), process.argv[5] || 'r.json'];
const { execSync } = require('child_process');
const sql = (q) => execSync(`mysql perfeita -N -e "${q}"`).toString().trim();
const usuarios = sql("select concat(u.email,'|',p.slug) from usuarios u join perfis p on p.id=u.perfil_id where u.ativo=1").split('\n').map((l) => l.split('|'));
const telas = {
  vendedor: ['/', '/carteira', '/carteira/lista', '/carteira/lista?pagina=2', '/agenda', '/agenda?ver=lista', '/recompra', '/reativacao', '/pedidos', '/meu-mes', '/orcamentos', 'CLIENTE', 'CLIENTE', 'NOVOPEDIDO', 'BUSCA', '/campanhas', '/academia'],
  gestor: ['/', '/carteira', '/carteira/lista', '/resultados', '/painel', '/equipe', '/acompanhamento', '/carteiras', '/pedidos', '/campanhas', 'CLIENTE', 'BUSCA', '/agenda'],
  gestor_setor: ['/', '/carteira', '/carteira/lista', '/equipe', '/acompanhamento', '/pedidos', 'CLIENTE', '/agenda'],
  admin: ['/', '/carteira', '/resultados', '/painel', '/pedidos', '/aprovacao', '/administracao', '/usuarios', '/configuracoes', '/auditoria', 'CLIENTE'],
  diretoria: ['/', '/resultados', '/painel', '/pedidos', '/aprovacao', '/carteira', '/producao'],
  financeiro: ['/', '/aprovacao', '/cobranca', '/financeiro/compras', '/pedidos', '/painel', 'CLIENTE'],
  marketing: ['/', '/marketing/inicio', '/marketing', '/marketing/ecommerce', '/marketing/pedir', '/produtos', '/academia'],
  producao: ['/', '/producao', '/producao/materias', '/producao/ordens', '/producao/fichas', '/producao/acabados', '/producao/rotulos'],
  qualidade: ['/', '/compras', '/producao/materias', '/financeiro/fornecedores', '/produtos'],
  tecnica: ['/', '/produtos', '/producao/fichas', '/desenvolvimento', '/producao/ordens'],
  deposito: ['/', '/deposito', '/deposito/recebimento', '/deposito/lotes'],
};
const clientes = sql("select id from clientes where tipo='cliente' order by rand() limit 200").split('\n');
const termos = ['farm', 'natural', 'porto', 'sup', 'empor', 'ma', 'vida', 'saude'];
const medidas = {}; let erros = 0; const errosPorTela = {};
const anota = (rota, ms, ok) => { (medidas[rota] ??= []).push(ms); if (!ok) { erros++; errosPorTela[rota] = (errosPorTela[rota] || 0) + 1; } };
const espera = (ms) => new Promise((r) => setTimeout(r, ms));
const sorteio = (l) => l[Math.floor(Math.random() * l.length)];

async function pessoa([email, perfil], fim) {
  let cookie = '';
  const req = async (url, opt = {}) => {
    const r = await fetch(BASE + url, { redirect: 'manual', ...opt, headers: { cookie, ...(opt.headers || {}) } });
    const sc = r.headers.get('set-cookie'); if (sc) cookie = sc.split(';')[0];
    return r;
  };
  const t0 = Date.now();
  let r = await req('/entrar'); const html = await r.text();
  const csrf = (html.match(/name="_csrf" value="([^"]+)"/) || [])[1];
  r = await req('/entrar', { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _csrf: csrf, email, senha: 'Demo@2026' }) });
  anota('LOGIN', Date.now() - t0, r.status === 302);
  const lista = telas[perfil] || telas.vendedor;
  // fundo: sino a cada 60 s (como o navegador faz)
  const sino = (async () => { while (Date.now() < fim) { await espera(60000 * Math.random() + 1000); if (Date.now() >= fim) break; const t = Date.now(); const x = await req('/notificacoes/resumo'); await x.text(); anota('(sino)', Date.now() - t, x.status === 200); } })();
  while (Date.now() < fim) {
    let tela = sorteio(lista);
    if (tela === 'CLIENTE') tela = '/carteira/clientes/' + sorteio(clientes);
    if (tela === 'NOVOPEDIDO') tela = '/pedidos/novo?cliente=' + sorteio(clientes);
    if (tela === 'BUSCA') tela = '/busca?q=' + sorteio(termos);
    const t = Date.now();
    try { const x = await req(tela); await x.text(); anota(tela.replace(/\/\d+$/, '/{id}').replace(/cliente=\d+/, 'cliente={id}').replace(/q=\w+/, 'q=…'), Date.now() - t, x.status < 400 || x.status === 403); }
    catch { anota(tela, Date.now() - t, false); }
    await espera((PMIN + Math.random() * (PMAX - PMIN)) * 1000);
  }
  await sino;
}
(async () => {
  const fim = Date.now() + SEG * 1000;
  const t = Date.now();
  await Promise.all(usuarios.map((u, i) => espera(i * 150).then(() => pessoa(u, fim))));
  const pct = (l, p) => { const s = [...l].sort((a, b) => a - b); return s[Math.min(s.length - 1, Math.floor(s.length * p))]; };
  const linhas = Object.entries(medidas).map(([rota, l]) => ({ rota, n: l.length, mediana: pct(l, 0.5), p95: pct(l, 0.95), max: Math.max(...l) })).sort((a, b) => b.p95 - a.p95);
  const todas = Object.values(medidas).flat();
  const res = { pessoas: usuarios.length, segundos: Math.round((Date.now() - t) / 1000), acessos: todas.length, por_segundo: +(todas.length / ((Date.now() - t) / 1000)).toFixed(1), erros, errosPorTela, mediana: pct(todas, 0.5), p95: pct(todas, 0.95), linhas };
  require('fs').writeFileSync(SAIDA, JSON.stringify(res, null, 1));
  console.log(JSON.stringify({ ...res, linhas: res.linhas.slice(0, 12) }, null, 0));
})();
