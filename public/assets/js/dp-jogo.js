// Desenvolvimento de Produto — o "jogo": a embalagem 3D (pote, frasco ou caixinha) vai se formando
// conforme cada área conclui a sua etapa. Os balões em volta mostram o que falta.
//
// Como funciona:
//  - os dados vêm embutidos na página (<script id="dp-dados">): tipo, etapas (com a "checagem" de cada uma), o que falta, EAN e a arte;
//  - cada etapa CONCLUÍDA liga uma peça da embalagem (ver PECAS abaixo) — pela checagem, então renomear etapas não quebra nada;
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
  const secao = document.getElementById('dp-etapa');
  if (!secao || new URLSearchParams(location.search).has('painel')) return;
  const dialogo = document.createElement('dialog');
  dialogo.className = 'dp-gaveta';
  dialogo.setAttribute('aria-label', 'Etapa atual');
  const fechar = document.createElement('button');
  fechar.type = 'button';
  fechar.className = 'dp-gaveta-fechar';
  fechar.setAttribute('aria-label', 'Fechar');
  fechar.textContent = '×';
  fechar.addEventListener('click', () => dialogo.close());
  dialogo.append(fechar, secao);
  document.body.append(dialogo);
  dialogo.addEventListener('click', (ev) => { if (ev.target === dialogo) dialogo.close(); }); // clicar fora fecha
  document.querySelectorAll('[data-abrir-etapa]').forEach((b) => b.addEventListener('click', () => dialogo.showModal()));
  if (location.hash === '#etapa') dialogo.showModal();
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
  camera.position.set(0, 2.3, 8.2);
  camera.lookAt(0, 1.35, 0);

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
  const pecas = montar(THREE, dados.tipo, embalagem);

  // Texturas dos rótulos (a arte pode vir de uma imagem enviada pelo Marketing)
  const arte = await carregarArte(dados.arte);
  const impresso = feitas.has('grafica');
  aplicarRotulos(THREE, pecas, arte, impresso);

  // Liga cada peça conforme as etapas concluídas
  const animacoes = [];
  const mostrar = (obj, checagem, comoAparece = 'cair') => {
    if (!obj) return;
    obj.visible = true;
    if (nova(checagem) && !reduzir) animacoes.push(entrada(obj, comoAparece));
  };
  // Antes do recebimento os rótulos "flutuam" em volta; depois ficam colados na embalagem
  const colado = feitas.has('recebimento');
  const rotulos = [pecas.rotuloFrente, pecas.rotuloTras, pecas.codigo].filter(Boolean);
  if (!colado) rotulos.forEach((r) => r.scale.multiplyScalar(1.07));
  pecas.fantasma.visible = !feitas.has('compras');
  if (feitas.has('ficha')) mostrar(pecas.conteudo, 'ficha', 'crescer');
  if (feitas.has('composicao')) mostrar(pecas.rotuloTras, 'composicao');
  if (feitas.has('codigo')) mostrar(pecas.codigo, 'codigo');
  if (feitas.has('arte')) mostrar(pecas.rotuloFrente, 'arte');
  if (feitas.has('compras')) mostrar(pecas.corpo, 'compras', 'crescer');
  if (feitas.has('aprovacao')) mostrar(pecas.selo, 'aprovacao', 'carimbo');
  if (feitas.has('recebimento')) mostrar(pecas.tampa, 'recebimento');
  if (feitas.has('piloto')) mostrar(pecas.lacre, 'piloto', 'crescer');
  if (nova('grafica') && !reduzir) animacoes.push(pulso(pecas.rotuloFrente));
  const pronto = feitas.has('piloto') || feitas.has('lancamento');
  if (nova('lancamento') || nova('lancado')) confete();

  // Girar com o mouse/dedo; gira sozinha devagar (exceto com "reduzir movimento")
  // Começa virada para a peça que acabou de chegar (o rótulo de trás e o código ficam do outro lado)
  const deCostas = ['composicao', 'codigo'].some((c) => nova(c)) && !nova('arte');
  let alvoY = deCostas ? Math.PI + 0.4 : 0.5, anguloY = alvoY, inclinacao = 0, arrastando = false, ultimoX = 0, ultimoY = 0, parado = 0;
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
      if (!colado) rotulos.forEach((r, i) => { r.position.y = r.userData.y0 + Math.sin(t * 2 + i) * 0.05; });
      if (pecas.fantasma.visible) pecas.fantasma.material.opacity = 0.32 + Math.sin(t * 2.2) * 0.12;
      if (pronto) contraLuz.intensity = 1.3 + Math.sin(t * 3) * 0.5;
    }
    for (let i = animacoes.length - 1; i >= 0; i--) if (animacoes[i](agora)) animacoes.splice(i, 1);
    renderer.render(scene, camera);
  });
}

// --- Animações simples (devolvem true quando terminam) ---
function entrada(obj, como) {
  const pos0 = obj.position.clone(), esc0 = obj.scale.clone(), inicio = performance.now() + 350, dur = 900;
  const de = como === 'cair' ? 2.4 : 0;
  obj.scale.multiplyScalar(como === 'carimbo' ? 2.5 : 0.001);
  if (de) obj.position.y += de;
  return (agora) => {
    const p = Math.min(1, Math.max(0, (agora - inicio) / dur));
    const e = 1 + 2.2 * Math.pow(p - 1, 3) + 1.2 * Math.pow(p - 1, 2); // "easeOutBack"
    if (como === 'carimbo') obj.scale.copy(esc0).multiplyScalar(2.5 - 1.5 * e);
    else obj.scale.copy(esc0).multiplyScalar(Math.max(0.001, e));
    obj.position.y = pos0.y + de * (1 - Math.min(1, e));
    return p >= 1 && (obj.scale.copy(esc0), obj.position.copy(pos0), true);
  };
}

function pulso(obj) {
  if (!obj) return () => true;
  const esc0 = obj.scale.clone(), inicio = performance.now() + 300;
  return (agora) => {
    const p = Math.min(1, Math.max(0, (agora - inicio) / 700));
    obj.scale.copy(esc0).multiplyScalar(1 + Math.sin(p * Math.PI) * 0.12);
    return p >= 1;
  };
}

// ---------------------------------------------------------------------------
// Modelos: cada tipo devolve as mesmas peças (corpo, fantasma, conteudo, rotuloFrente, rotuloTras, codigo, tampa, lacre, selo)
// ---------------------------------------------------------------------------
function montar(THREE, tipo, grupo) {
  const p = tipo === 'frasco' ? frasco(THREE) : tipo === 'caixinha' ? caixinha(THREE) : pote(THREE);
  Object.values(p).forEach((obj) => {
    if (!obj) return;
    obj.visible = false;
    obj.userData.y0 = obj.position.y;
    grupo.add(obj);
  });
  return p;
}

const fantasmaMat = (THREE) => new THREE.MeshBasicMaterial({ color: 0xcaff4d, wireframe: true, transparent: true, opacity: 0.35 });

function seloMesh(THREE, raio, x, y, z, rotY) {
  const m = new THREE.Mesh(new THREE.CircleGeometry(raio, 48), new THREE.MeshStandardMaterial({ map: texturaSelo(THREE), transparent: true, roughness: 0.5 }));
  m.position.set(x, y, z);
  m.rotation.y = rotY;
  return m;
}

function pote(THREE) {
  const r = 1, h = 2.4;
  const corpo = new THREE.Mesh(new THREE.CylinderGeometry(r, r * 0.97, h, 64), new THREE.MeshStandardMaterial({ color: 0xf6f6f1, roughness: 0.35 }));
  corpo.position.y = h / 2;
  const fantasma = new THREE.Mesh(new THREE.CylinderGeometry(r, r * 0.97, h, 18, 5), fantasmaMat(THREE));
  fantasma.position.y = h / 2;
  const conteudo = new THREE.Mesh(new THREE.CylinderGeometry(r * 0.86, r * 0.84, h * 0.78, 40), new THREE.MeshStandardMaterial({ color: 0xefdcb4, roughness: 0.95 }));
  conteudo.position.y = h * 0.42;
  const rotuloFrente = rotuloCilindro(THREE, r * 1.012, 1.3, -Math.PI / 2, Math.PI);
  rotuloFrente.position.y = 1.15;
  const rotuloTras = rotuloCilindro(THREE, r * 1.012, 1.3, Math.PI / 2, Math.PI);
  rotuloTras.position.y = 1.15;
  const codigo = rotuloCilindro(THREE, r * 1.02, 0.42, Math.PI + 0.12, 0.62);
  codigo.position.y = 0.72;
  const tampa = new THREE.Group();
  const tampaCorpo = new THREE.Mesh(new THREE.CylinderGeometry(r * 1.06, r * 1.06, 0.5, 64), new THREE.MeshStandardMaterial({ color: 0x1f4d3a, roughness: 0.4 }));
  const tampaTopo = new THREE.Mesh(new THREE.CylinderGeometry(r * 0.9, r * 1.06, 0.06, 64), new THREE.MeshStandardMaterial({ color: 0x2a6a4f, roughness: 0.35 }));
  tampaTopo.position.y = 0.28;
  tampa.add(tampaCorpo, tampaTopo);
  tampa.position.y = h + 0.24;
  const lacre = new THREE.Mesh(new THREE.CylinderGeometry(r * 1.075, r * 1.075, 0.14, 64, 1, true),
    new THREE.MeshStandardMaterial({ color: 0xcaff4d, transparent: true, opacity: 0.85, roughness: 0.2, side: THREE.DoubleSide }));
  lacre.position.y = h + 0.02;
  const ang = 0.62;
  const selo = seloMesh(THREE, 0.27, Math.sin(ang) * 1.04, 1.95, Math.cos(ang) * 1.04, ang);
  return { corpo, fantasma, conteudo, rotuloFrente, rotuloTras, codigo, tampa, lacre, selo };
}

function frasco(THREE) {
  const perfil = [[0, 0], [0.72, 0], [0.8, 0.08], [0.8, 1.95], [0.74, 2.2], [0.36, 2.45], [0.32, 2.5], [0.32, 2.8], [0, 2.8]]
    .map(([x, y]) => new THREE.Vector2(x, y));
  const corpo = new THREE.Mesh(new THREE.LatheGeometry(perfil, 64),
    new THREE.MeshStandardMaterial({ color: 0x8a4a12, roughness: 0.12, metalness: 0.1, transparent: true, opacity: 0.88 }));
  const fantasma = new THREE.Mesh(new THREE.LatheGeometry(perfil, 18), fantasmaMat(THREE));
  const conteudo = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.7, 1.55, 40),
    new THREE.MeshStandardMaterial({ color: 0xe0962e, roughness: 0.1, transparent: true, opacity: 0.85 }));
  conteudo.position.y = 0.86;
  const rotuloFrente = rotuloCilindro(THREE, 0.812, 1.15, -Math.PI / 2, Math.PI);
  rotuloFrente.position.y = 1.05;
  const rotuloTras = rotuloCilindro(THREE, 0.812, 1.15, Math.PI / 2, Math.PI);
  rotuloTras.position.y = 1.05;
  const codigo = rotuloCilindro(THREE, 0.82, 0.36, Math.PI + 0.15, 0.75);
  codigo.position.y = 0.68;
  const tampa = new THREE.Group();
  const rosca = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.38, 0.5, 48), new THREE.MeshStandardMaterial({ color: 0x1b1b1b, roughness: 0.5 }));
  const bulbo = new THREE.Mesh(new THREE.SphereGeometry(0.27, 32, 24), new THREE.MeshStandardMaterial({ color: 0x1f4d3a, roughness: 0.6 }));
  bulbo.scale.y = 1.5;
  bulbo.position.y = 0.55;
  tampa.add(rosca, bulbo);
  tampa.position.y = 2.95;
  const lacre = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.4, 0.1, 48, 1, true),
    new THREE.MeshStandardMaterial({ color: 0xcaff4d, transparent: true, opacity: 0.85, side: THREE.DoubleSide }));
  lacre.position.y = 2.72;
  const ang = 0.7;
  const selo = seloMesh(THREE, 0.24, Math.sin(ang) * 0.83, 1.72, Math.cos(ang) * 0.83, ang);
  return { corpo, fantasma, conteudo, rotuloFrente, rotuloTras, codigo, tampa, lacre, selo };
}

function caixinha(THREE) {
  const L = 1.6, A = 2.6, P = 0.85;
  const cartao = new THREE.MeshStandardMaterial({ color: 0xf4efe4, roughness: 0.8 });
  const corpo = new THREE.Mesh(new THREE.BoxGeometry(L, A, P), cartao);
  corpo.position.y = A / 2;
  const fantasma = new THREE.Mesh(new THREE.BoxGeometry(L, A, P, 4, 6, 2), fantasmaMat(THREE));
  fantasma.position.y = A / 2;
  // Conteúdo: cartela de cápsulas (blister)
  const conteudo = new THREE.Group();
  const cartela = new THREE.Mesh(new THREE.BoxGeometry(L * 0.8, A * 0.75, 0.04), new THREE.MeshStandardMaterial({ color: 0xd9dde0, metalness: 0.6, roughness: 0.3 }));
  conteudo.add(cartela);
  const capsula = new THREE.CapsuleGeometry(0.09, 0.2, 6, 12);
  const capMat = new THREE.MeshStandardMaterial({ color: 0xcaff4d, roughness: 0.4 });
  for (let i = 0; i < 2; i++) for (let j = 0; j < 5; j++) {
    const c = new THREE.Mesh(capsula, capMat);
    c.position.set(-0.3 + i * 0.6, -0.75 + j * 0.38, 0.06);
    c.rotation.z = Math.PI / 2;
    conteudo.add(c);
  }
  conteudo.position.y = A / 2;
  const plano = (w, h) => new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshStandardMaterial({ roughness: 0.6, transparent: true }));
  const rotuloFrente = plano(L, A);
  rotuloFrente.position.set(0, A / 2, P / 2 + 0.006);
  const rotuloTras = plano(L, A);
  rotuloTras.position.set(0, A / 2, -P / 2 - 0.006);
  rotuloTras.rotation.y = Math.PI;
  const codigo = plano(0.9, 0.42);
  codigo.position.set(0, 0.45, -P / 2 - 0.012);
  codigo.rotation.y = Math.PI;
  // "Tampa": a aba de cima, fechada
  const tampa = new THREE.Mesh(new THREE.BoxGeometry(L + 0.02, 0.05, P + 0.02), new THREE.MeshStandardMaterial({ color: 0x1f4d3a, roughness: 0.5 }));
  tampa.position.y = A + 0.025;
  const lacre = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.16, 0.02, 32), new THREE.MeshStandardMaterial({ color: 0xcaff4d }));
  lacre.rotation.x = Math.PI / 2;
  lacre.position.set(0, A - 0.05, P / 2 + 0.03);
  const selo = seloMesh(THREE, 0.26, 0.5, A - 0.5, P / 2 + 0.02, 0);
  return { corpo, fantasma, conteudo, rotuloFrente, rotuloTras, codigo, tampa, lacre, selo };
}

function rotuloCilindro(THREE, raio, altura, inicio, abertura) {
  return new THREE.Mesh(new THREE.CylinderGeometry(raio, raio, altura, 64, 1, true, inicio, abertura),
    new THREE.MeshStandardMaterial({ roughness: 0.55, side: THREE.DoubleSide, transparent: true }));
}

// ---------------------------------------------------------------------------
// Texturas desenhadas em canvas
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

function aplicarRotulos(THREE, pecas, arte, impresso) {
  const caixa = dados.tipo === 'caixinha';
  const [cf, xf] = caixa ? novoCanvas(512, 832) : novoCanvas(1024, 440);
  desenharFrente(xf, cf.width, cf.height, arte);
  if (!impresso) { // antes da gráfica: rascunho em tons de cinza
    const [cg, xg] = novoCanvas(cf.width, cf.height);
    xg.filter = 'grayscale(1) contrast(.8) brightness(1.15)';
    xg.drawImage(cf, 0, 0);
    pecas.rotuloFrente.material.map = textura(THREE, cg);
  } else {
    pecas.rotuloFrente.material.map = textura(THREE, cf);
  }
  const [ct, xt] = caixa ? novoCanvas(512, 832) : novoCanvas(1024, 440);
  desenharTras(xt, ct.width, ct.height);
  pecas.rotuloTras.material.map = textura(THREE, ct);
  const [cc, xc] = novoCanvas(512, 240);
  desenharCodigo(xc, 512, 240, dados.ean);
  pecas.codigo.material.map = textura(THREE, cc);
  [pecas.rotuloFrente, pecas.rotuloTras, pecas.codigo].forEach((m) => { m.material.needsUpdate = true; });
}

function desenharFrente(x, w, h, arte) {
  if (arte) { // arte enviada: preenche o rótulo (corta as sobras, sem distorcer)
    const esc = Math.max(w / arte.width, h / arte.height);
    x.drawImage(arte, (w - arte.width * esc) / 2, (h - arte.height * esc) / 2, arte.width * esc, arte.height * esc);
    return;
  }
  const g = x.createLinearGradient(0, 0, 0, h);
  g.addColorStop(0, '#1f4d3a'); g.addColorStop(1, '#123326');
  x.fillStyle = g; x.fillRect(0, 0, w, h);
  x.fillStyle = '#caff4d'; x.fillRect(0, h * 0.78, w, h * 0.05);
  x.textAlign = 'center';
  x.fillStyle = '#caff4d';
  x.font = `700 ${Math.round(h * 0.06)}px Montserrat, Arial, sans-serif`;
  x.fillText('PERFEITA ALQUIMIA', w / 2, h * 0.18);
  x.fillStyle = '#ffffff';
  const tam = Math.round(Math.min(w / 11, h * 0.12));
  x.font = `800 ${tam}px Montserrat, Arial, sans-serif`;
  quebrar(x, dados.nome, w * 0.8).slice(0, 3).forEach((l, i, a) => x.fillText(l, w / 2, h * 0.47 - (a.length - 1) * tam * 0.55 + i * tam * 1.1));
  x.font = `500 ${Math.round(h * 0.05)}px Montserrat, Arial, sans-serif`;
  x.fillStyle = '#d7e8de';
  x.fillText('suplemento alimentar', w / 2, h * 0.72);
}

function desenharTras(x, w, h) {
  x.fillStyle = '#fbfbf7'; x.fillRect(0, 0, w, h);
  const larg = Math.min(w * 0.42, 420), esq = (w - larg) / 2;
  x.strokeStyle = '#222'; x.lineWidth = 3;
  x.strokeRect(esq, h * 0.08, larg, h * 0.62);
  x.fillStyle = '#222';
  x.font = `800 ${Math.round(h * 0.05)}px Arial, sans-serif`;
  x.textAlign = 'left';
  x.fillText('INFORMAÇÃO NUTRICIONAL', esq + 10, h * 0.15);
  x.lineWidth = 1.5;
  for (let i = 0; i < 8; i++) {
    const y = h * (0.2 + i * 0.062);
    x.beginPath(); x.moveTo(esq + 8, y); x.lineTo(esq + larg - 8, y); x.stroke();
    x.fillStyle = '#999';
    x.fillRect(esq + 12, y + 8, larg * (0.25 + ((i * 37) % 30) / 100), h * 0.018);
    x.fillRect(esq + larg * 0.75, y + 8, larg * 0.15, h * 0.018);
  }
  x.fillStyle = '#bbb';
  for (let i = 0; i < 3; i++) x.fillRect(esq, h * (0.76 + i * 0.045), larg * (i === 2 ? 0.6 : 1), h * 0.02);
}

function desenharCodigo(x, w, h, ean) {
  x.fillStyle = '#ffffff'; x.fillRect(0, 0, w, h);
  const digitos = (ean || '7890000000000').replace(/\D/g, '').padEnd(13, '0');
  x.fillStyle = '#111';
  let pos = 40;
  for (let i = 0; i < 95 && pos < w - 40; i++) {
    const d = Number(digitos[i % 13]);
    const largura = 1 + ((d + i) % 4);
    if ((d + i) % 2 === 0 || i % 23 === 0) x.fillRect(pos, 20, largura * 1.6, h * 0.62);
    pos += largura * 1.6 + 1.5;
  }
  x.font = '600 30px monospace';
  x.textAlign = 'center';
  x.fillText(digitos.replace(/^(\d)(\d{6})(\d{6})$/, '$1 $2 $3'), w / 2, h - 22);
}

function quebrar(x, texto, max) {
  const palavras = String(texto).split(/\s+/), linhas = [];
  let atual = '';
  palavras.forEach((p) => {
    const teste = atual ? atual + ' ' + p : p;
    if (x.measureText(teste).width > max && atual) { linhas.push(atual); atual = p; } else { atual = teste; }
  });
  if (atual) linhas.push(atual);
  return linhas;
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
