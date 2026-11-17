// =====================================================================
// Carteira › ajustar o ponto da loja no mapa (MapLibre): marcador arrastável; a posição vai para o formulário.
// =====================================================================
const el = document.getElementById('mapa-localizacao');
if (el) {
  const lat = Number(el.dataset.lat), lng = Number(el.dataset.lng);
  const campoLat = document.querySelector('[data-campo-lat]'), campoLng = document.querySelector('[data-campo-lng]');
  if (typeof maplibregl === 'undefined') {
    el.innerHTML = '<div class="mapa-erro"><strong>O mapa não carregou.</strong><span>Verifique a conexão com a internet.</span></div>';
  } else {
    const mapa = new maplibregl.Map({
      container: el, style: 'https://tiles.openfreemap.org/styles/bright',
      center: [lng, lat], zoom: el.dataset.exato === '1' ? 17 : 14, maxZoom: 19, attributionControl: false,
    });
    mapa.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');
    mapa.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
    const marcador = new maplibregl.Marker({ draggable: true, color: '#0b5d4b' }).setLngLat([lng, lat]).addTo(mapa);
    const guardar = () => { const p = marcador.getLngLat(); campoLat.value = p.lat.toFixed(6); campoLng.value = p.lng.toFixed(6); };
    marcador.on('dragend', guardar);
    // Clicar no mapa também leva o marcador para lá
    mapa.on('click', (ev) => { marcador.setLngLat(ev.lngLat); guardar(); });
  }
}
