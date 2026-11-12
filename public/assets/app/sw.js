// =====================================================================
// Service worker do One Vendas (o servidor coloca antes deste arquivo: const VERSAO e const BASE).
//  - Tela do app: rede primeiro; sem internet, usa a cópia guardada.
//  - Arquivos (CSS, JS, imagens, fotos de produtos): cópia guardada primeiro (rápido e offline).
//  - API (/app/api/...): sempre pela rede (os dados ficam no IndexedDB, não aqui).
//  - Background Sync: quando a internet volta, avisa o app aberto para enviar a fila.
// =====================================================================
const CACHE = 'one-vendas-' + VERSAO;

// Ao instalar: guarda a tela do app e todos os arquivos que ela usa (para abrir sem internet já na próxima vez)
self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    try {
      const tela = await fetch(BASE + '/app', { credentials: 'same-origin' });
      if (tela.ok && !tela.redirected) {
        const html = await tela.clone().text();
        await cache.put(BASE + '/app', tela);
        const arquivos = [...html.matchAll(/(?:href|src)="([^"]+)"/g)].map((m) => m[1]).filter((u) => u.startsWith(BASE + '/assets/'));
        const modulos = ['banco.js', 'fila.js'].map((m) => BASE + '/assets/app/' + m);
        await cache.addAll([...new Set([...arquivos, ...modulos, BASE + '/assets/img/icones.svg'])]);
      }
    } catch { /* sem internet na instalação: guarda na próxima visita */ }
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((chaves) => Promise.all(chaves.filter((k) => k.startsWith('one-vendas-') && k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || url.pathname.startsWith(BASE + '/app/api/')) return; // API: só rede

  // Tela do app: rede primeiro, cópia guardada sem internet
  if (url.pathname === BASE + '/app' || url.pathname === BASE + '/app/') {
    e.respondWith(fetch(req).then((r) => {
      if (r.ok && !r.redirected) caches.open(CACHE).then((c) => c.put(BASE + '/app', r.clone()));
      return r;
    }).catch(() => caches.match(BASE + '/app')));
    return;
  }

  // Arquivos e fotos: cópia guardada primeiro
  if (url.pathname.startsWith(BASE + '/assets/') || url.pathname.startsWith(BASE + '/media/')) {
    e.respondWith(caches.match(req).then((guardado) => guardado || fetch(req).then((r) => {
      if (r.ok) caches.open(CACHE).then((c) => c.put(req, r.clone()));
      return r;
    })));
  }
});

// Internet voltou (Android/Chrome): pede para o app aberto enviar a fila
self.addEventListener('sync', (e) => {
  if (e.tag !== 'enviar-fila') return;
  e.waitUntil(self.clients.matchAll({ includeUncontrolled: true }).then((abertos) => abertos.forEach((c) => c.postMessage('enviar-fila'))));
});
