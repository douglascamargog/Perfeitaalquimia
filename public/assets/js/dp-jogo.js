// Desenvolvimento de Produto — o "jogo": a embalagem 3D (pote, frasco ou caixinha) no centro, com os balões das etapas em volta.
//
// Como funciona:
//  - os dados vêm embutidos na página (<script id="dp-dados">): tipo, cor da embalagem e da tampa, etapas e a arte do rótulo;
//  - a embalagem já aparece inteira (com a cor escolhida e a tampa) desde a criação do projeto;
//  - a ARTE DO RÓTULO enviada (imagem) envolve a embalagem do jeito que veio, sem distorcer;
//  - etapas finais acrescentam detalhes: selo de aprovado (prova), lacre (lote piloto) e festa (lançamento);
//  - o navegador lembra o que a pessoa já viu (localStorage): o que mudou desde a última visita aparece com animação;
//  - o 3D usa o Three.js guardado no próprio sistema (assets/vendor/three). Se não carregar, fica a ilustração SVG (reserva).

const dados = lerDados();
const jogo = document.querySelector('[data-jogo]');
const reduzir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (dados && jogo) {
  prepararGaveta();
  posicionarBaloes();
  const novas = novidades();
  anunciar(novas);
  iniciar3D(novas).catch(() => jogo.querySelector('[data-cena]').classList.add('sem-3d'));
}

// Trocar o tipo de embalagem envia o formulário na hora
document.querySelectorAll('[data-enviar-ao-mudar]').forEach((s) => s.addEventListener('change', () => s.form.submit()));

function lerDados() {
  try { return JSON.parse(document.getElementById('dp-dados')?.textContent || 'null'); } catch { return null; }
}

// ---------------------------------------------------------------------------
// Gaveta da etapa: o cartão da etapa atual abre ao clicar no balão
// ---------------------------------------------------------------------------
function prepararGaveta() {
  // Uma gaveta por etapa (várias podem estar abertas ao mesmo tempo); abre ao clicar no balão dela
  if (new URLSearchParams(location.search).has('painel')) return;
  const secoes = [...document.querySelectorAll('.dp-gavetas .dp-atual')];
  secoes.forEach((secao) => {
    const dialogo = document.createElement('dialog');
    dialogo.className = 'dp-gaveta';
    dialogo.setAttribute('aria-label', secao.querySelector('h2')?.textContent || 'Etapa');
    const fechar = document.createElement('button');
    fechar.type = 'button';
    fechar.className = 'dp-gaveta-fechar';
    fechar.setAttribute('aria-label', 'Fechar');
    fechar.textContent = '×';
    fechar.addEventListener('click', () => dialogo.close());
    dialogo.append(fechar, secao);
    document.body.append(dialogo);
    dialogo.addEventListener('click', (ev) => { if (ev.target === dialogo) dialogo.close(); }); // clicar fora fecha
    document.querySelectorAll(`[data-abrir-etapa="${secao.dataset.etapa}"]`).forEach((b) => b.addEventListener('click', () => dialogo.showModal()));
  });
  // Volta do formulário (#etapa-12): reabre a gaveta em que a pessoa estava
  const m = location.hash.match(/^#etapa-(\d+)$/);
  if (m) document.querySelector(`[data-abrir-etapa="${m[1]}"]`)?.click();
}

// ---------------------------------------------------------------------------
// Balões em círculo (elipse) em volta da embalagem, com um "fio" até o centro
// ---------------------------------------------------------------------------
function posicionarBaloes() {
  const palco = jogo.querySelector('.dp-palco');
  const baloes = [...jogo.querySelectorAll('.dp-balao')];
  const fios = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  fios.setAttribute('class', 'dp-fios');
  fios.setAttribute('aria-hidden', 'true');
  palco.prepend(fios);
  const arrumar = () => {
    const largo = palco.clientWidth >= 820;
    palco.classList.toggle('circulo', largo);
    fios.innerHTML = '';
    if (!largo) { baloes.forEach((b) => { b.style.left = ''; b.style.top = ''; }); return; }
    const n = baloes.length;
    const w = palco.clientWidth, h = palco.clientHeight;
    fios.setAttribute('viewBox', `0 0 ${w} ${h}`);
    baloes.forEach((b, i) => {
      // começa no alto, à esquerda, e segue no sentido do relógio
      const ang = (-Math.PI / 2) - Math.PI / n + (i + 0.5) * (2 * Math.PI / n);
      const x = 50 + 39 * Math.cos(ang), y = 50 + 40 * Math.sin(ang);
      b.style.left = x + '%';
      b.style.top = y + '%';
      const linha = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      linha.setAttribute('x1', w / 2); linha.setAttribute('y1', h / 2);
      linha.setAttribute('x2', (x / 100) * w); linha.setAttribute('y2', (y / 100) * h);
      linha.setAttribute('class', b.classList.contains('concluida') ? 'feito' : b.classList.contains('andamento') ? 'atual' : '');
      fios.append(linha);
    });
  };
  arrumar();
  new ResizeObserver(arrumar).observe(palco);
}

// ---------------------------------------------------------------------------
// O que mudou desde a última visita desta pessoa (para animar só o que é novo)
// ---------------------------------------------------------------------------
function novidades() {
  const feitas = dados.etapas.filter((e) => e.situacao === 'concluida').map((e) => e.checagem || 'etapa-' + e.id);
  if (dados.situacao === 'lancado') feitas.push('lancado');
  if (dados.arte) feitas.push('arte:' + dados.arte);
  const chave = 'dp-visto-' + dados.id;
  let antes = null;
  try { antes = JSON.parse(localStorage.getItem(chave) || 'null'); } catch { /* sem armazenamento: sem animação */ }
  try { localStorage.setItem(chave, JSON.stringify(feitas)); } catch { /* ignora */ }
  if (!Array.isArray(antes)) return []; // primeira visita: a cena já aparece montada
  return feitas.filter((c) => !antes.includes(c));
}

function anunciar(novas) {
  if (!novas.length) return;
  const etapas = dados.etapas.filter((e) => novas.includes(e.checagem || 'etapa-' + e.id));
  etapas.forEach((e) => jogo.querySelector(`.dp-balao[data-checagem="${CSS.escape(e.checagem)}"]`)?.classList.add('estourou'));
  const aviso = document.createElement('div');
  aviso.className = 'dp-conquista';
  aviso.setAttribute('role', 'status');
  if (novas.includes('lancado')) {
    aviso.textContent = '🎉 Produto lançado!';
    confete();
  } else if (novas.some((n) => n.startsWith('arte:')) && !etapas.length) {
    aviso.textContent = 'A arte do rótulo chegou e já está na embalagem!';
  } else {
    const e = etapas[etapas.length - 1];
    aviso.textContent = e ? `${e.funcao} concluiu "${e.nome}"${e.peca ? ` — chegou ${e.peca}!` : '!'}` : 'O produto avançou!';
  }
  jogo.querySelector('.dp-palco').append(aviso);
  setTimeout(() => aviso.classList.add('saindo'), 5200);
}

function confete() {
  if (reduzir) return;
  const caixa = document.createElement('div');
  caixa.className = 'dp-confete';
  caixa.setAttribute('aria-hidden', 'true');
  const cores = ['#caff4d', '#1f4d3a', '#ffffff', '#8fd14f', '#f2c94c'];
  for (let i = 0; i < 70; i++) {
    const c = document.createElement('i');
    c.style.left = Math.random() * 100 + '%';
    c.style.background = cores[i % cores.length];
    c.style.animationDelay = Math.random() * 0.8 + 's';
    c.style.animationDuration = 2.2 + Math.random() * 1.6 + 's';
    c.style.transform = `rotate(${Math.random() * 360}deg)`;
    caixa.append(c);
  }
  jogo.querySelector('.dp-palco').append(caixa);
  setTimeout(() => caixa.remove(), 5000);
}

// ---------------------------------------------------------------------------
// Cena 3D
// ---------------------------------------------------------------------------
async function iniciar3D(novas) {
  const cena = jogo.querySelector('[data-cena]');
  const teste = document.createElement('canvas');
  if (!(teste.getContext('webgl2') || teste.getContext('webgl'))) throw new Error('sem WebGL');
  const THREE = await import(new URL('../vendor/three/three.module.min.js', import.meta.url).href);

  const feitas = new Set(dados.etapas.filter((e) => e.situacao === 'concluida').map((e) => e.checagem));
  if (dados.situacao === 'lancado') feitas.add('lancamento');
  const nova = (c) => novas.includes(c);

  // Renderizador e câmera
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.domElement.setAttribute('aria-label', 'Embalagem em 3D — arraste para girar');
  renderer.domElement.setAttribute('role', 'img');
  cena.append(renderer.domElement);
  cena.classList.add('com-3d');

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  // Enquadramento de cada embalagem (o pote é mais alto)
  const [alvo, dist, alt] = { pote: [1.95, 9.2, 2.9], frasco: [1.55, 8.6, 2.5], caixinha: [1.35, 8.2, 2.3] }[dados.tipo] || [1.4, 8.2, 2.3];
  camera.position.set(0, alt, dist);
  camera.lookAt(0, alvo, 0);

  scene.add(new THREE.HemisphereLight(0xffffff, 0x1f4d3a, 1.4));
  const sol = new THREE.DirectionalLight(0xffffff, 2.2);
  sol.position.set(3, 6, 5);
  scene.add(sol);
  const contraLuz = new THREE.DirectionalLight(0xcaff4d, 1.3);
  contraLuz.position.set(-4, 3, -4);
  scene.add(contraLuz);

  // Sombra suave no chão
  const sombra = new THREE.Mesh(new THREE.PlaneGeometry(4, 4), new THREE.MeshBasicMaterial({ map: texturaSombra(THREE), transparent: true, depthWrite: false }));
  sombra.rotation.x = -Math.PI / 2;
  sombra.position.y = 0.001;
  scene.add(sombra);

  const embalagem = new THREE.Group();
  scene.add(embalagem);
  const cores = coresDaEmbalagem();
  const pecas = montar(THREE, dados.tipo, embalagem, cores);
  const animacoes = [];

  // Rótulo: a arte enviada (imagem) envolve a embalagem; arte nova chega "enrolando" em volta
  const arte = await carregarArte(dados.arte);
  if (arte) {
    const rotulo = rotuloDaArte(THREE, pecas.medidas, arte);
    embalagem.add(rotulo.objeto);
    if (novas.includes('arte:' + dados.arte) && !reduzir) animacoes.push(rotulo.envolver());
  }

  // Detalhes das etapas finais
  const mostrar = (obj, checagem, comoAparece) => {
    if (!obj) return;
    obj.visible = true;
    if (nova(checagem) && !reduzir) animacoes.push(entrada(obj, comoAparece));
  };
  if (feitas.has('aprovacao')) mostrar(pecas.selo, 'aprovacao', 'carimbo');
  if (feitas.has('piloto')) mostrar(pecas.lacre, 'piloto', 'crescer');
  const pronto = feitas.has('piloto') || feitas.has('lancamento');
  if (nova('lancamento') || nova('lancado')) confete();

  // Girar com o mouse/dedo; gira sozinha devagar (exceto com "reduzir movimento")
  let alvoY = 0.35, anguloY = alvoY, inclinacao = 0, arrastando = false, ultimoX = 0, ultimoY = 0, parado = 0;
  const tela = renderer.domElement;
  tela.style.touchAction = 'pan-y';
  tela.addEventListener('pointerdown', (e) => { arrastando = true; ultimoX = e.clientX; ultimoY = e.clientY; tela.setPointerCapture(e.pointerId); });
  tela.addEventListener('pointermove', (e) => {
    if (!arrastando) return;
    alvoY += (e.clientX - ultimoX) * 0.012;
    inclinacao = Math.max(-0.35, Math.min(0.35, inclinacao + (e.clientY - ultimoY) * 0.004));
    ultimoX = e.clientX; ultimoY = e.clientY; parado = performance.now();
  });
  tela.addEventListener('pointerup', () => { arrastando = false; parado = performance.now(); });

  const ajustar = () => {
    const w = cena.clientWidth, h = cena.clientHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  };
  ajustar();
  new ResizeObserver(ajustar).observe(cena);

  const inicio = performance.now();
  const giroFesta = (nova('lancamento') || nova('lancado')) && !reduzir;
  renderer.setAnimationLoop((agora) => {
    const t = (agora - inicio) / 1000;
    if (!arrastando && !reduzir && agora - parado > 1800) alvoY += giroFesta && t < 2.5 ? 0.12 : 0.005;
    anguloY += (alvoY - anguloY) * 0.12;
    embalagem.rotation.y = anguloY;
    embalagem.rotation.x += (inclinacao - embalagem.rotation.x) * 0.1;
    if (!arrastando) inclinacao *= 0.96;
    if (!reduzir) {
      embalagem.position.y = Math.sin(t * 1.4) * 0.04; // "respira"
      if (pronto) contraLuz.intensity = 1.3 + Math.sin(t * 3) * 0.5;
    }
    for (let i = animacoes.length - 1; i >= 0; i--) if (animacoes[i](agora)) animacoes.splice(i, 1);
    renderer.render(scene, camera);
  });
}

// --- Animações simples (devolvem true quando terminam) ---
function entrada(obj, como) {
  const pos0 = obj.position.clone(), esc0 = obj.scale.clone(), inicio = performance.now() + 350, dur = 900;
  obj.scale.multiplyScalar(como === 'carimbo' ? 2.5 : 0.001);
  return (agora) => {
    const p = Math.min(1, Math.max(0, (agora - inicio) / dur));
    const e = 1 + 2.2 * Math.pow(p - 1, 3) + 1.2 * Math.pow(p - 1, 2); // "easeOutBack"
    if (como === 'carimbo') obj.scale.copy(esc0).multiplyScalar(2.5 - 1.5 * e);
    else obj.scale.copy(esc0).multiplyScalar(Math.max(0.001, e));
    return p >= 1 && (obj.scale.copy(esc0), obj.position.copy(pos0), true);
  };
}

// ---------------------------------------------------------------------------
// Cores: embalagem (branco, preto ou âmbar) e tampa (qualquer cor)
// ---------------------------------------------------------------------------
function coresDaEmbalagem() {
  const padrao = { pote: 'branco', frasco: 'ambar', caixinha: 'branco' }[dados.tipo] || 'branco';
  const nome = dados.cor || padrao;
  const corpo = { branco: 0xf3f5f4, preto: 0x1d1f1e, ambar: 0x8a4a12 }[nome] ?? 0xf3f5f4;
  const tampa = /^#[0-9a-f]{6}$/i.test(dados.tampa || '') ? parseInt(dados.tampa.slice(1), 16) : 0x14473a;
  return { nome, corpo, tampa };
}

const brilhante = (THREE, cor, extra = {}) => new THREE.MeshPhysicalMaterial({ color: cor, roughness: 0.25, clearcoat: 1, clearcoatRoughness: 0.1, ...extra });

// ---------------------------------------------------------------------------
// Modelos: cada tipo devolve corpo, tampa, lacre, selo e as "medidas" onde o rótulo encaixa
// ---------------------------------------------------------------------------
function montar(THREE, tipo, grupo, cores) {
  const p = tipo === 'frasco' ? frasco(THREE, cores) : tipo === 'caixinha' ? caixinha(THREE, cores) : pote(THREE, cores);
  for (const [nome, obj] of Object.entries(p)) {
    if (!obj || nome === 'medidas') continue;
    obj.visible = !['lacre', 'selo'].includes(nome); // selo e lacre só nas etapas finais
    grupo.add(obj);
  }
  return p;
}

function seloMesh(THREE, raio, x, y, z, rotY) {
  const m = new THREE.Mesh(new THREE.CircleGeometry(raio, 48), new THREE.MeshStandardMaterial({ map: texturaSelo(THREE), transparent: true, roughness: 0.5 }));
  m.position.set(x, y, z);
  m.rotation.y = rotY;
  return m;
}

// Pote de cápsulas: alto, com fundo arredondado e tampa de rosca
function pote(THREE, cores) {
  const v = (pts) => pts.map(([x, y]) => new THREE.Vector2(x, y));
  const corpo = new THREE.Mesh(new THREE.LatheGeometry(v([[0, 0], [0.82, 0], [0.93, 0.03], [0.985, 0.1], [1, 0.22], [1, 3.02], [0.985, 3.08], [0.955, 3.1], [0.955, 3.2], [0, 3.2]]), 96),
    brilhante(THREE, cores.corpo, { roughness: cores.nome === 'preto' ? 0.3 : 0.22 }));
  const tampa = new THREE.Group();
  tampa.add(new THREE.Mesh(new THREE.LatheGeometry(v([[0.96, 0], [1.012, 0], [1.02, 0.04], [1.02, 0.58], [1.005, 0.64], [0.96, 0.68], [0.9, 0.69], [0, 0.69]]), 96),
    brilhante(THREE, cores.tampa)));
  const friso = new THREE.Mesh(new THREE.TorusGeometry(1.016, 0.012, 8, 96), new THREE.MeshStandardMaterial({ color: new THREE.Color(cores.tampa).multiplyScalar(0.6), roughness: 0.5 }));
  friso.rotation.x = Math.PI / 2;
  friso.position.y = 0.05;
  tampa.add(friso);
  tampa.position.y = 3.08;
  const lacre = new THREE.Mesh(new THREE.CylinderGeometry(1.03, 1.03, 0.1, 96, 1, true),
    new THREE.MeshStandardMaterial({ color: 0xcaff4d, transparent: true, opacity: 0.8, roughness: 0.2, side: THREE.DoubleSide }));
  lacre.position.y = 3.13;
  const ang = 0.62;
  const selo = seloMesh(THREE, 0.27, Math.sin(ang) * 1.04, 2.6, Math.cos(ang) * 1.04, ang);
  return { corpo, tampa, lacre, selo, medidas: { raio: 1.008, alturaMax: 2.72, centro: 1.64 } };
}

// Frasco com conta-gotas (âmbar deixa ver o líquido)
function frasco(THREE, cores) {
  const perfil = [[0, 0], [0.72, 0], [0.8, 0.08], [0.8, 1.95], [0.74, 2.2], [0.36, 2.45], [0.32, 2.5], [0.32, 2.8], [0, 2.8]].map(([x, y]) => new THREE.Vector2(x, y));
  const ambar = cores.nome === 'ambar';
  const corpo = new THREE.Group();
  corpo.add(new THREE.Mesh(new THREE.LatheGeometry(perfil, 64), brilhante(THREE, cores.corpo, ambar ? { roughness: 0.12, transparent: true, opacity: 0.88 } : {})));
  if (ambar) {
    const liquido = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.7, 1.55, 40), new THREE.MeshStandardMaterial({ color: 0xe0962e, roughness: 0.1, transparent: true, opacity: 0.85 }));
    liquido.position.y = 0.86;
    corpo.add(liquido);
  }
  const tampa = new THREE.Group();
  const rosca = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.38, 0.5, 48), brilhante(THREE, cores.tampa));
  const bulbo = new THREE.Mesh(new THREE.SphereGeometry(0.27, 32, 24), new THREE.MeshStandardMaterial({ color: cores.tampa, roughness: 0.6 }));
  bulbo.scale.y = 1.5;
  bulbo.position.y = 0.55;
  tampa.add(rosca, bulbo);
  tampa.position.y = 2.95;
  const lacre = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.4, 0.1, 48, 1, true),
    new THREE.MeshStandardMaterial({ color: 0xcaff4d, transparent: true, opacity: 0.85, side: THREE.DoubleSide }));
  lacre.position.y = 2.72;
  const ang = 0.7;
  const selo = seloMesh(THREE, 0.24, Math.sin(ang) * 0.84, 1.8, Math.cos(ang) * 0.84, ang);
  return { corpo, tampa, lacre, selo, medidas: { raio: 0.812, alturaMax: 1.45, centro: 1.05 } };
}

// Caixinha (cartucho): a arte cobre a frente ou dá a volta nas 4 faces
function caixinha(THREE, cores) {
  const L = 1.6, A = 2.6, P = 0.85;
  const corpo = new THREE.Mesh(new THREE.BoxGeometry(L, A, P), new THREE.MeshStandardMaterial({ color: cores.corpo, roughness: 0.8 }));
  corpo.position.y = A / 2;
  const lacre = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.16, 0.02, 32), new THREE.MeshStandardMaterial({ color: 0xcaff4d }));
  lacre.rotation.x = Math.PI / 2;
  lacre.position.set(0, A - 0.05, P / 2 + 0.03);
  const selo = seloMesh(THREE, 0.26, 0.5, A - 0.5, P / 2 + 0.03, 0);
  return { corpo, tampa: null, lacre, selo, medidas: { caixa: [L, A, P] } };
}

// ---------------------------------------------------------------------------
// Rótulo a partir da arte enviada. Cilindro: a imagem envolve sem distorcer (se for mais alta que o espaço,
// cobre só parte da volta, centralizada na frente). Caixinha: arte "em pé" cobre a frente; arte larga dá a volta nas faces.
// Devolve { objeto, envolver() } — envolver() é a animação da arte chegando e enrolando na embalagem.
// ---------------------------------------------------------------------------
function rotuloDaArte(THREE, medidas, arte) {
  const tex = new THREE.Texture(arte);
  tex.colorSpace = THREE.SRGBColorSpace;
  tex.anisotropy = 8;
  tex.needsUpdate = true;
  const aspecto = arte.width / arte.height;
  if (medidas.caixa) return rotuloCaixa(THREE, medidas.caixa, tex, aspecto);
  const { raio, alturaMax, centro } = medidas;
  let altura = (2 * Math.PI * raio) / aspecto, abertura = 2 * Math.PI;
  if (altura > alturaMax) { altura = alturaMax; abertura = Math.min(2 * Math.PI, (altura * aspecto) / raio); }
  const material = new THREE.MeshPhysicalMaterial({ map: tex, roughness: 0.35, clearcoat: 0.6, clearcoatRoughness: 0.2 });
  const geometria = (f) => new THREE.CylinderGeometry(raio, raio, altura, 128, 1, true, -abertura / 2, Math.max(0.001, abertura * f));
  const objeto = new THREE.Mesh(geometria(1), material);
  objeto.position.y = centro;
  return {
    objeto,
    envolver() {
      const inicio = performance.now() + 300;
      return (agora) => {
        const p = Math.min(1, Math.max(0, (agora - inicio) / 1400));
        const f = 1 - Math.pow(1 - p, 3);
        objeto.geometry.dispose();
        objeto.geometry = geometria(f);
        tex.repeat.x = Math.max(0.001, f);
        return p >= 1 && ((tex.repeat.x = 1), true);
      };
    },
  };
}

function rotuloCaixa(THREE, [L, A, P], tex, aspecto) {
  const grupo = new THREE.Group();
  const faces = aspecto < 1.2 ? [['frente', L]] : [['frente', L], ['direita', P], ['tras', L], ['esquerda', P]];
  const total = faces.reduce((s, [, w]) => s + w, 0);
  let u = 0;
  for (const [lado, w] of faces) {
    const t = tex.clone();
    t.needsUpdate = true;
    t.repeat.set(w / total, 1);
    t.offset.set(u / total, 0);
    u += w;
    const m = new THREE.Mesh(new THREE.PlaneGeometry(w, A), new THREE.MeshStandardMaterial({ map: t, roughness: 0.6 }));
    const d = 0.006;
    if (lado === 'frente') m.position.set(0, A / 2, P / 2 + d);
    if (lado === 'direita') { m.position.set(L / 2 + d, A / 2, 0); m.rotation.y = Math.PI / 2; }
    if (lado === 'tras') { m.position.set(0, A / 2, -P / 2 - d); m.rotation.y = Math.PI; }
    if (lado === 'esquerda') { m.position.set(-L / 2 - d, A / 2, 0); m.rotation.y = -Math.PI / 2; }
    grupo.add(m);
  }
  return { objeto: grupo, envolver: () => entrada(grupo, 'crescer') };
}

// ---------------------------------------------------------------------------
// Texturas desenhadas em canvas (selo e sombra)
// ---------------------------------------------------------------------------
function textura(THREE, canvas) {
  const t = new THREE.CanvasTexture(canvas);
  t.colorSpace = THREE.SRGBColorSpace;
  t.anisotropy = 4;
  return t;
}

function novoCanvas(w, h) {
  const c = document.createElement('canvas');
  c.width = w; c.height = h;
  return [c, c.getContext('2d')];
}

async function carregarArte(url) {
  if (!url) return null;
  return new Promise((ok) => {
    const img = new Image();
    img.onload = () => ok(img);
    img.onerror = () => ok(null);
    img.src = url;
  });
}

function texturaSelo(THREE) {
  const [c, x] = novoCanvas(256, 256);
  x.fillStyle = '#caff4d';
  x.beginPath(); x.arc(128, 128, 124, 0, Math.PI * 2); x.fill();
  x.strokeStyle = '#1f4d3a'; x.lineWidth = 8;
  x.beginPath(); x.arc(128, 128, 104, 0, Math.PI * 2); x.stroke();
  x.fillStyle = '#1f4d3a';
  x.textAlign = 'center';
  x.font = '800 38px Montserrat, Arial, sans-serif';
  x.fillText('APROVADO', 128, 112);
  x.lineWidth = 14; x.lineCap = 'round'; x.lineJoin = 'round';
  x.beginPath(); x.moveTo(92, 150); x.lineTo(120, 176); x.lineTo(168, 128); x.stroke();
  return textura(THREE, c);
}

function texturaSombra(THREE) {
  const [c, x] = novoCanvas(256, 256);
  const g = x.createRadialGradient(128, 128, 10, 128, 128, 120);
  g.addColorStop(0, 'rgba(0,0,0,.45)'); g.addColorStop(1, 'rgba(0,0,0,0)');
  x.fillStyle = g; x.fillRect(0, 0, 256, 256);
  return textura(THREE, c);
}
