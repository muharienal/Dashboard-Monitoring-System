/* DMS - Mode layar TV: berganti antar halaman (semua bidang → tiap bidang) setiap N detik. */
(function () {
  'use strict';
  const $ = (s) => document.querySelector(s);
  const T = window.TV;
  const kini = new Date();
  const periode = { tahun: kini.getFullYear(), bulan: kini.getMonth() + 1 };
  const halaman = [{ jenis: 'semua', nama: 'Semua bidang' }, ...T.bidang.map((b) => ({ jenis: 'bidang', ...b }))];
  let idx = 0, jeda = false, timer = null;

  // Grafik di latar gelap
  Chart.defaults.color = '#c9cec8';
  Chart.defaults.borderColor = 'rgba(255,255,255,.08)';
  Chart.defaults.font.size = 15;
  Chart.defaults.animation.duration = 600;

  const HDR = { headers: { 'X-DMS-Mode': 'tv' } };
  const ambil = (url) => DMS.fetchJson(url, HDR);
  const qs = `tahun=${periode.tahun}&bulan=${periode.bulan}`;

  // ---------- Jam ----------
  const jam = () => {
    const d = new Date();
    $('#tvJam').textContent = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    $('#tvTanggal').textContent = d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  };
  jam(); setInterval(jam, 1000 * 15);
  $('#tvPeriode').textContent = `${DMS.BULAN[periode.bulan - 1]} ${periode.tahun}`;

  // ---------- Titik halaman ----------
  $('#tvTitik').innerHTML = halaman.map((h, i) => `<button type="button" role="tab" data-i="${i}" title="${DMS.esc(h.nama)}" aria-label="${DMS.esc(h.nama)}"></button>`).join('');
  $('#tvTitik').addEventListener('click', (e) => { const b = e.target.closest('[data-i]'); if (b) tampil(+b.dataset.i); });

  // ---------- Selisih dibanding periode lalu ----------
  function selisih(el, kiniV, laluV, p, satuan, arahBaik) {
    if (kiniV === null || kiniV === undefined) { el.innerHTML = '<span class="ket">belum ada data bulan ini</span>'; el.className = 'd'; return; }
    if (!p || kiniV === null || laluV === null || kiniV === undefined || laluV === undefined || !p.availability.unit_hari) { el.innerHTML = p ? `<span class="ket">belum ada data ${DMS.esc(p.label)}</span>` : ''; el.className = 'd'; return; }
    const s = Math.round((kiniV - laluV) * 10) / 10;
    if (s === 0) { el.innerHTML = `<span class="ket">sama dengan ${DMS.esc(p.label)}</span>`; el.className = 'd'; return; }
    const naik = s > 0, nada = arahBaik === 0 ? '' : ((naik ? 1 : -1) === arahBaik ? 'baik' : 'buruk');
    const angka = satuan === 'poin' ? DMS.num1(Math.abs(s)) : DMS.num(Math.round(Math.abs(s)));
    el.className = `d ${nada}`;
    el.innerHTML = `<span class="a">${naik ? '▲' : '▼'} ${angka} ${satuan}</span> <span class="ket">dari ${DMS.esc(p.label)}</span>`;
  }
  const r1 = (v) => (v === null || v === undefined) ? null : Math.round(v * 10) / 10;

  function tren(rows) {
    const ada = rows.some((r) => r.unit_hari > 0);
    $('#tvTren').parentElement.style.visibility = ada ? 'visible' : 'hidden';
    if (!ada) { $('#tvTrenCat').textContent = 'Belum ada data bulan ini.'; return; }
    const c = DMS.charts.utilitas($('#tvTren'), rows.map((r) => DMS.periodeLabel(r.periode)), rows, { type: 'line', yTitle: 'unit' });
    c.options.scales.x.ticks.color = '#c9cec8';
    c.options.plugins.legend.labels.font = { size: 15 };
    c.update('none');
    $('#tvTrenCat').textContent = '';
  }

  // ---------- Halaman ----------
  async function renderSemua() {
    const d = await ambil(`${T.url.beranda}?${qs}`);
    const a = d.availability, p = d.pembanding;
    $('#tlUnit').textContent = 'Jumlah unit';
    $('#tkAr').textContent = DMS.pct(a.availability_rate);
    $('#tkUtil').textContent = DMS.pct(a.utilisasi);
    $('#tkUnit').textContent = DMS.num(a.unit);
    $('#tkPerb').textContent = DMS.num(a.jam.PERBAIKAN);
    selisih($('#tdAr'), r1(a.availability_rate), r1(p?.availability.availability_rate), p, 'poin', 1);
    selisih($('#tdUtil'), r1(a.utilisasi), r1(p?.availability.utilisasi), p, 'poin', 1);
    selisih($('#tdUnit'), a.unit_hari ? a.unit : null, p?.availability.unit, p, 'unit', 0);
    selisih($('#tdPerb'), a.unit_hari ? a.jam.PERBAIKAN : null, p?.availability.jam.PERBAIKAN, p, 'jam', -1);
    $('#tvTrenJudul').textContent = 'Jumlah unit per hari';
    tren(d.tren);
    $('#tvSampingJudul').textContent = 'Per bidang';
    $('#tvSamping').innerHTML = d.bidang.map((b) => `<div class="tv-baris">
        <span class="n">${DMS.esc(b.nama)}</span>
        <span class="v">${DMS.pct(b.availability_rate)}</span>
        <span class="bar"><span style="width:${b.availability_rate ?? 0}%"></span></span>
        <span class="u">Util ${DMS.pct(b.utilisasi)}</span></div>`).join('');
    const h = d.kelengkapan[d.kelengkapan.length - 1];
    const terbaru = h.bidang.filter((b) => b.terakhir).sort((x, y) => (x.terakhir < y.terakhir ? 1 : -1))[0];
    $('#tvKelengkapan').innerHTML = terbaru
      ? `<i class="fas fa-clock-rotate-left"></i> Update terakhir hari ini: <b>${terbaru.terakhir.slice(11, 16).replace(':', '.')}</b> · ${DMS.esc(terbaru.nama)}`
      : '<i class="fas fa-clipboard-list"></i> Hari ini belum ada update input';
  }

  async function renderBidang(b) {
    const d = await ambil(`${T.url.summary}/${b.kode}?${qs}`);
    const a = d.availability, p = d.pembanding;
    $('#tlUnit').textContent = 'Unit jalan per hari';
    $('#tkAr').textContent = DMS.pct(a.availability_rate);
    $('#tkUtil').textContent = DMS.pct(a.utilisasi);
    $('#tkUnit').textContent = DMS.unit(a.avg_jalan);
    $('#tkPerb').textContent = a.unit_hari ? DMS.num(a.jam.PERBAIKAN) : '–';
    selisih($('#tdAr'), r1(a.availability_rate), r1(p?.availability.availability_rate), p, 'poin', 1);
    selisih($('#tdUtil'), r1(a.utilisasi), r1(p?.availability.utilisasi), p, 'poin', 1);
    selisih($('#tdUnit'), a.unit_hari ? Math.round(a.avg_jalan) : null, p ? Math.round(p.availability.avg_jalan ?? 0) : null, p, 'unit', 1);
    selisih($('#tdPerb'), a.unit_hari ? a.jam.PERBAIKAN : null, p?.availability.jam.PERBAIKAN, p, 'jam', -1);
    $('#tvTrenJudul').textContent = d.tren_mode === 'hari' ? 'Unit per hari' : 'Unit per bulan';
    tren(d.tren);
    $('#tvSampingJudul').textContent = 'Perbaikan terbanyak';
    const m = d.maintenance.slice(0, 6), maks = Math.max(1, ...m.map((r) => r.perbaikan));
    $('#tvSamping').innerHTML = m.length ? m.map((r) => `<div class="tv-baris perb">
        <span class="n">${DMS.esc(r.nomor)}<small>${DMS.esc(r.jenis)}</small></span>
        <span class="v">${DMS.num(r.perbaikan)} jam</span>
        <span class="bar"><span style="width:${r.perbaikan / maks * 100}%"></span></span></div>`).join('')
      : '<div class="tv-kosong">Tidak ada jam perbaikan bulan ini.</div>';
  }

  async function tampil(i) {
    clearTimeout(timer);
    idx = (i + halaman.length) % halaman.length;
    const h = halaman[idx];
    $('#tvJudul').textContent = h.nama;
    document.querySelectorAll('#tvTitik [data-i]').forEach((b, n) => b.setAttribute('aria-selected', n === idx ? 'true' : 'false'));
    document.body.classList.remove('tv-masuk'); void document.body.offsetWidth; document.body.classList.add('tv-masuk');
    try {
      await (h.jenis === 'semua' ? renderSemua() : renderBidang(h));
      $('#tvOffline').hidden = true;
      $('#tvDiperbarui').textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      if (e.message === 'unauthenticated') return;
      $('#tvOffline').hidden = false; // tetap tampilkan data terakhir, coba lagi di putaran berikutnya
    }
    jalankan();
  }

  function jalankan() {
    const bar = $('#tvProgres');
    bar.style.animation = 'none'; void bar.offsetWidth;
    if (jeda) { bar.style.animation = 'none'; bar.style.width = '0'; return; }
    bar.style.width = ''; bar.style.animation = `tvProgres ${T.detik}s linear forwards`;
    timer = setTimeout(() => tampil(idx + 1), T.detik * 1000);
  }

  // ---------- Kontrol ----------
  const setJeda = (v) => {
    jeda = v;
    $('#tvJeda').innerHTML = jeda ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
    $('#tvJeda').setAttribute('aria-label', jeda ? 'Lanjutkan' : 'Jeda');
    clearTimeout(timer); jalankan();
  };
  $('#tvJeda').addEventListener('click', () => setJeda(!jeda));
  $('#tvPrev').addEventListener('click', () => tampil(idx - 1));
  $('#tvNext').addEventListener('click', () => tampil(idx + 1));
  $('#tvPenuh').addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen?.().catch(() => {});
  });
  document.addEventListener('fullscreenchange', () => {
    $('#tvPenuh').innerHTML = document.fullscreenElement ? '<i class="fas fa-compress"></i> Keluar layar penuh' : '<i class="fas fa-expand"></i> Layar penuh';
  });
  $('#tvIngat').addEventListener('click', async () => {
    const ok = await DMS.confirm({ title: 'Tetap masuk di layar ini?', icon: 'fa-tv', ok: 'Tetap masuk',
      text: 'Layar ini tidak perlu login ulang walau sesi habis atau server dinyalakan ulang. Pakai hanya di komputer ruang kontrol, bukan komputer pribadi.' });
    if (!ok) return;
    try { const r = await DMS.fetchJson(T.url.ingat, { method: 'POST', body: '{}', ...HDR }); DMS.toast(DMS.esc(r.pesan), 'success', 5000, { title: 'Tersimpan' }); $('#tvIngat').innerHTML = '<i class="fas fa-lock"></i> Tetap masuk aktif'; }
    catch (e) { DMS.toast(DMS.esc(e.message), 'danger', 6000, { title: 'Gagal' }); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, dialog')) return;
    if (e.key === ' ') { e.preventDefault(); setJeda(!jeda); }
    else if (e.key === 'ArrowRight') tampil(idx + 1);
    else if (e.key === 'ArrowLeft') tampil(idx - 1);
    else if (e.key.toLowerCase() === 'f') $('#tvPenuh').click();
  });
  // Kursor & kontrol disembunyikan bila mouse diam 4 detik.
  let diam = null;
  const gerak = () => { document.body.classList.remove('tv-diam'); clearTimeout(diam); diam = setTimeout(() => document.body.classList.add('tv-diam'), 4000); };
  ['mousemove', 'mousedown', 'touchstart', 'keydown'].forEach((ev) => document.addEventListener(ev, gerak, { passive: true }));
  gerak();

  tampil(0);
})();
