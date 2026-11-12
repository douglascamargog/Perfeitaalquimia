// =====================================================================
// Mapa da carteira (MapLibre + OpenFreeMap), como na Carteira V4, agora para o Brasil inteiro.
//
// O servidor devolve os dados já agrupados, então funciona com dezenas de milhares de lojas:
//   zoom baixo  → um círculo por ESTADO (com a contagem)
//   zoom médio  → um círculo por CIDADE
//   zoom alto   → um ponto por LOJA (só o que está visível na tela)
// Lojas sem coordenada própria ficam em posição aproximada dentro da cidade (como na V4).
// =====================================================================
// Abrir a ficha usa a função de carteira.js (carregado antes deste arquivo)
const abrirCliente = (id) => window.Carteira?.abrirCliente(id);

const BASE = document.querySelector('meta[name="base"]')?.content || '';
const ZOOM_CIDADE = 5.6;   // a partir daqui mostra cidades
const ZOOM_PONTOS = 10.7;  // a partir daqui mostra cada loja
const CORES = { 'Produtos naturais': '#caff4e', Suplementos: '#35d99c', 'Farmácia': '#6cb8ff' };

const elemento = document.querySelector('[data-mapa]');
if (elemento) iniciar(elemento);

function iniciar(el) {
  const carregando = document.querySelector('[data-mapa-carregando]');
  const contagem = document.querySelector('[data-mapa-contagem]');
  const filtros = el.dataset.filtros || '';

  if (typeof maplibregl === 'undefined') {
    el.innerHTML = '<div class="mapa-erro"><strong>O mapa não carregou.</strong><span>Verifique a conexão com a internet.</span></div>';
    carregando?.remove();
    return;
  }

  const mapa = new maplibregl.Map({
    container: el,
    style: 'https://tiles.openfreemap.org/styles/bright',
    center: [-51.2, -29.9],
    zoom: 4,
    minZoom: 3,
    maxZoom: 17,
    attributionControl: false,
    renderWorldCopies: false,
  });
  mapa.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');
  mapa.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');

  let marcadores = [];
  let ultimaChave = '';
  let pedidoAtual = 0;

  /** Pede ao servidor os dados do nível de zoom atual e redesenha os marcadores */
  async function atualizar(forcar = false) {
    const zoom = mapa.getZoom();
    const nivel = zoom < ZOOM_CIDADE ? 'uf' : zoom < ZOOM_PONTOS ? 'cidade' : 'ponto';
    const b = mapa.getBounds();
    const caixa = nivel === 'uf' ? '' : [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map((n) => n.toFixed(2)).join(',');
    const chave = nivel + '|' + caixa;
    if (!forcar && chave === ultimaChave) return;
    ultimaChave = chave;

    const numero = ++pedidoAtual;
    const parametros = new URLSearchParams(filtros);
    parametros.set('nivel', nivel);
    if (caixa) parametros.set('caixa', caixa);
    const resposta = await fetch(`${BASE}/carteira/mapa.json?${parametros}`);
    if (!resposta.ok || numero !== pedidoAtual) return; // chegou atrasado: ignora
    const { itens } = await resposta.json();

    marcadores.forEach((m) => m.remove());
    marcadores = itens.filter((i) => i.lat !== null && i.lng !== null).map((item) => criarMarcador(item, nivel));
    carregando?.remove();

    const total = itens.reduce((soma, i) => soma + Number(i.total || 1), 0);
    if (contagem) {
      contagem.textContent = nivel === 'uf' ? `${total.toLocaleString('pt-BR')} estabelecimentos em ${itens.length} estados`
        : nivel === 'cidade' ? `${total.toLocaleString('pt-BR')} estabelecimentos em ${itens.length} cidades visíveis`
        : `${itens.length.toLocaleString('pt-BR')} estabelecimentos visíveis`;
    }
    return itens;
  }

  function criarMarcador(item, nivel) {
    const botao = document.createElement('button');
    botao.type = 'button';
    if (nivel === 'ponto') {
      const [lng, lat] = posicaoAproximada(item);
      botao.className = 'pino-loja' + (item.tipo === 'cliente' ? ' cliente' : '');
      botao.style.setProperty('--cor', CORES[item.segmento] || '#c9cfc9');
      botao.setAttribute('aria-label', item.nome);
      botao.title = `${item.nome} · ${item.cidade}`;
      botao.addEventListener('click', (e) => {
        e.stopPropagation();
        new maplibregl.Popup({ offset: 12, maxWidth: '320px' }).setLngLat([lng, lat]).setDOMContent(popup(item)).addTo(mapa);
      });
      return new maplibregl.Marker({ element: botao }).setLngLat([lng, lat]).addTo(mapa);
    }
    // Agrupamento (estado ou cidade): bolha com a contagem; clicar aproxima
    botao.className = 'pino-grupo' + (nivel === 'uf' ? ' estado' : '');
    botao.textContent = Number(item.total).toLocaleString('pt-BR');
    const nome = nivel === 'uf' ? item.uf : `${item.cidade} / ${item.uf}`;
    botao.title = `${nome}: ${item.total} estabelecimentos (${item.clientes} clientes). Clique para aproximar.`;
    botao.setAttribute('aria-label', botao.title);
    botao.addEventListener('click', (e) => {
      e.stopPropagation();
      const destino = { center: [Number(item.lng), Number(item.lat)], zoom: nivel === 'uf' ? 6.5 : 12 };
      // Com o fundo do mapa carregado, aproxima com animação; sem ele (internet ruim), salta direto
      if (mapa.isStyleLoaded()) mapa.flyTo({ ...destino, duration: 600 });
      else mapa.jumpTo(destino);
    });
    return new maplibregl.Marker({ element: botao }).setLngLat([Number(item.lng), Number(item.lat)]).addTo(mapa);
  }

  /** Lojas sem coordenada própria: ponto fixo (sempre o mesmo) espalhado em volta do centro da cidade */
  function posicaoAproximada(item) {
    const lat = Number(item.lat), lng = Number(item.lng);
    if (Number(item.exato)) return [lng, lat];
    let h = 0;
    for (const ch of String(item.id) + item.nome) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    const angulo = (h % 360) * Math.PI / 180;
    const raio = ((h >>> 12) % 800) / 800;
    return [lng + Math.cos(angulo) * (0.003 + raio * 0.007), lat + Math.sin(angulo) * (0.003 + raio * 0.006)];
  }

  function popup(item) {
    const div = document.createElement('div');
    div.className = 'mapa-popup';
    const titulo = document.createElement('strong');
    titulo.textContent = item.nome;
    const info = document.createElement('span');
    info.textContent = `${item.cidade} / ${item.uf} · ${item.segmento || ''}` + (item.endereco ? ` — ${item.endereco}` : '');
    const selos = document.createElement('small');
    selos.textContent = `${item.etapa || ''} · prioridade ${item.prioridade || ''}` + (Number(item.exato) ? '' : ' · posição aproximada');
    const botao = document.createElement('button');
    botao.type = 'button';
    botao.className = 'botao lima pequeno';
    botao.textContent = 'Abrir ficha';
    botao.addEventListener('click', () => abrirCliente(item.id));
    div.append(titulo, info, selos, botao);
    return div;
  }

  // Ao abrir: carrega os estados e enquadra onde há clientes.
  // Não espera o estilo do mapa (evento 'load'): os marcadores são HTML e aparecem mesmo se o fundo demorar.
  requestAnimationFrame(async () => {
    const itens = await atualizar(true);
    if (itens && itens.length) {
      const limites = new maplibregl.LngLatBounds();
      itens.forEach((i) => i.lat !== null && limites.extend([Number(i.lng), Number(i.lat)]));
      if (itens.length === 1) mapa.jumpTo({ center: limites.getCenter(), zoom: 6.5 });
      else mapa.fitBounds(limites, { padding: 60, maxZoom: 6, duration: 0 });
    }
  });
  mapa.on('moveend', () => atualizar());
  mapa.on('error', (e) => console.warn('Mapa:', e.error || e));
}
