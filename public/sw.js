/*
 * DMS - service worker (hanya aktif bila DMS dibuka lewat HTTPS atau localhost).
 * - Aset statis (CSS, JS, font, ikon): disimpan di cache agar cepat & tetap tampil saat sinyal lemah.
 * - Halaman: selalu dari jaringan; bila sinyal hilang tampilkan halaman "Tidak ada sinyal".
 * - Data (API) dan POST tidak pernah disimpan di cache: angka selalu yang terbaru dari server.
 */
const VERSI = 'dms-v2.20';
const DASAR = new URL('./', self.location).pathname;
const AWAL = ['offline.html', 'favicon.svg', 'assets/img/ikon/dms-192.png', 'manifest.webmanifest'].map((p) => DASAR + p);

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSI).then((c) => c.addAll(AWAL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((k) => Promise.all(k.filter((n) => n !== VERSI).map((n) => caches.delete(n)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(DASAR + 'offline.html')));
    return;
  }
  const statis = url.pathname.startsWith(DASAR + 'assets/') || /\.(css|js|woff2?|png|svg|ico)$/.test(url.pathname);
  if (!statis || url.pathname.startsWith(DASAR + 'api/')) return;
  // Pakai cache dulu, sambil memperbarui di belakang layar.
  e.respondWith(caches.open(VERSI).then(async (c) => {
    const ada = await c.match(req);
    const segar = fetch(req).then((r) => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => ada);
    return ada || segar;
  }));
});
