// =====================================================================
// Planejador de visitas: cidades do formulário, mapa do roteiro (MapLibre), marcar lojas e busca de lojas.
// =====================================================================

// Formulário: "+ Mais uma cidade"
const campos = document.querySelector('[data-cidades]');
if (campos) {
  campos.querySelector('[data-mais-cidade]')?.addEventListener('click', () => {
    const linhas = campos.querySelectorAll('.rt-cidade-linha');
    const nova = linhas[linhas.length - 1].cloneNode(true);
    nova.querySelector('.rt-num').textContent = linhas.length + 1;
    const input = nova.querySelector('input');
    input.value = '';
    input.placeholder = 'Próxima cidade';
    input.setAttribute('aria-label', 'Cidade ' + (linhas.length + 1));
    linhas[linhas.length - 1].after(nova);
    input.focus();
  });
}

// Mapa: caminho (linha), paradas numeradas e lojas no caminho
const el = document.getElementById('rt-mapa');
if (el) {
  const dados = JSON.parse(el.dataset.mapaRoteiro || '{}');
  if (typeof maplibregl === 'undefined') {
    el.innerHTML = '<div class="mapa-erro"><strong>O mapa não carregou.</strong><span>Verifique a conexão com a internet.</span></div>';
  } else {
    const pontos = [...(dados.rota || []), ...(dados.paradas || []).filter((p) => p.lat !== null), ...(dados.caminho || [])];
    const mapa = new maplibregl.Map({
      container: el, style: 'https://tiles.openfreemap.org/styles/bright',
      center: pontos.length ? [pontos[0].lng, pontos[0].lat] : [-51.2, -30.0], zoom: 8, attributionControl: false,
    });
    mapa.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');
    mapa.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
    if (pontos.length > 1) {
      const b = new maplibregl.LngLatBounds();
      pontos.forEach((p) => b.extend([p.lng, p.lat]));
      mapa.fitBounds(b, { padding: 40, maxZoom: 13, duration: 0 });
    }
    const marcador = (classe, texto, p, popup) => {
      const m = document.createElement('div');
      m.className = 'rt-marcador ' + classe;
      if (texto) m.textContent = texto;
      new maplibregl.Marker({ element: m }).setLngLat([p.lng, p.lat]).setPopup(new maplibregl.Popup({ offset: 12 }).setText(popup)).addTo(mapa);
    };
    mapa.on('load', () => {
      if ((dados.rota || []).length > 1) {
        mapa.addSource('rota', { type: 'geojson', data: { type: 'Feature', geometry: { type: 'LineString', coordinates: dados.rota.map((c) => [c.lng, c.lat]) } } });
        mapa.addLayer({ id: 'rota', type: 'line', source: 'rota', paint: { 'line-color': '#0b5d4b', 'line-width': 4, 'line-opacity': .55, 'line-dasharray': [2, 1.5] } });
      }
    });
    (dados.caminho || []).filter((l) => !l.no_roteiro).forEach((l) => marcador(l.sugerida ? 'sugerida' : 'outra', '', l, l.nome + (l.sugerida ? ' · sugerida' : '')));
    (dados.rota || []).forEach((c) => marcador('cidade', '', c, c.nome));
    (dados.paradas || []).filter((p) => p.lat !== null).forEach((p, i) => marcador(p.situacao, String(i + 1), p, p.nome + (p.dia ? ' · ' + p.dia : '')));
  }
}

// Lojas no caminho: marcar/desmarcar todas
document.querySelector('[data-marcar-todas]')?.addEventListener('change', (ev) => {
  document.querySelectorAll('[data-form-caminho] input[name="clientes[]"]').forEach((c) => { c.checked = ev.target.checked; });
});

// Busca de qualquer loja para incluir no roteiro
const busca = document.querySelector('[data-busca-lojas]');
if (busca) {
  const lista = document.querySelector('[data-resultado-busca] ul');
  let espera;
  busca.addEventListener('input', () => {
    clearTimeout(espera);
    espera = setTimeout(async () => {
      const q = busca.value.trim();
      if (q.length < 2) { lista.innerHTML = ''; return; }
      const r = await fetch(busca.dataset.buscaLojas + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
      const { lojas } = await r.json();
      lista.innerHTML = '';
      lojas.forEach((l) => {
        const li = document.createElement('li');
        const txt = document.createElement('span');
        txt.textContent = `${l.nome} · ${l.cidade || ''}/${l.uf || ''} · ${l.vendedor ? 'carteira de ' + l.vendedor : 'sem vendedor(a)'}`;
        const bt = document.createElement('button');
        bt.type = 'submit'; bt.name = 'clientes[]'; bt.value = l.id; bt.className = 'botao pequeno'; bt.textContent = 'Incluir';
        li.append(txt, bt);
        lista.append(li);
      });
      if (!lojas.length) lista.innerHTML = '<li class="suave">Nenhuma loja encontrada.</li>';
    }, 250);
  });
}
