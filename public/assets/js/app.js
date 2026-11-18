/* DMS - helper bersama (tanpa build tool / npm). */
(function () {
  'use strict';
  const cfg = window.DMS_CONFIG || {};
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
  const nf1 = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  const LABEL = { JALAN: 'Jalan', STANDBY: 'Standby', RUSAK: 'Rusak', PERBAIKAN: 'Perbaikan', PM: 'PM' };

  const DMS = {
    warna: cfg.warna || {},
    label: (k) => LABEL[k] || k,
    BULAN,
    num: (v) => (v === null || v === undefined || Number.isNaN(v)) ? '–' : nf.format(v),
    num1: (v) => (v === null || v === undefined) ? '–' : nf1.format(v),
    /** Jumlah unit selalu bilangan bulat: rata-rata unit dibulatkan ke unit terdekat. */
    unit: (v) => (v === null || v === undefined || Number.isNaN(v)) ? '–' : nf.format(Math.round(v)),
    pct: (v) => (v === null || v === undefined) ? '–' : nf1.format(v) + '%',
    /** Tautan ke profil kendaraan (nomor sudah di-escape oleh fungsi ini). */
    linkUnit: (id, nomor, attrs = '') => id ? `<a class="nomor-link" href="${cfg.profilUrl}/${id}" ${attrs}>${DMS.esc(nomor)}</a>` : DMS.esc(nomor),
    esc: (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])),

    tanggalPanjang(iso) {
      if (!iso) return '–';
      const [d, t] = String(iso).split(' ');
      const [y, m, dd] = d.split('-').map(Number);
      return `${dd} ${BULAN[m - 1]} ${y}` + (t ? ' ' + t.slice(0, 5) : '');
    },
    periodeLabel(p) {
      if (/^\d{4}-\d{2}$/.test(p)) { const [y, m] = p.split('-').map(Number); return `${BULAN[m - 1].slice(0, 3)} ${y}`; }
      if (/^\d{4}-\d{2}-\d{2}$/.test(p)) { const [, m, d] = p.split('-').map(Number); return `${d} ${BULAN[m - 1].slice(0, 3)}`; }
      return p;
    },

    async fetchJson(url, opts = {}) {
      const res = await fetch(url, {
        credentials: 'same-origin',
        ...opts,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, ...(opts.body ? { 'Content-Type': 'application/json' } : {}), ...(opts.headers || {}) },
      });
      if (res.status === 401 || res.status === 419) {
        DMS.toast('Sesi habis. Silakan login lagi.', 'warning');
        setTimeout(() => location.assign(cfg.loginUrl || '/login'), 1200);
        throw new Error('unauthenticated');
      }
      let data = null;
      try { data = await res.json(); } catch (_) { /* bukan JSON */ }
      if (!res.ok) {
        // Error server (500 dst.) tidak ditampilkan mentah ke pengguna; detail ada di log server & console.
        let msg = data?.message || `Gagal (${res.status})`;
        if (res.status >= 500 && res.status !== 503) {
          console.error('DMS server error', res.status, data);
          msg = 'Terjadi kesalahan di server. Coba lagi, atau hubungi IT bila berulang.';
        }
        const e = new Error(msg); e.status = res.status; e.data = data; throw e;
      }
      return data;
    },

    /** Notifikasi & konfirmasi standar: lihat assets/js/alert.js */
    toast: (message, type = 'success', ms = 4500, opts = {}) => window.DMSAlert.toast(message, type, ms, opts),
    confirm: (opts) => window.DMSAlert.confirm(opts),

    /** Ambil filter dari form .filter-bar, sinkronkan ke URL agar bisa di-bookmark. */
    filterParams(form) {
      const p = new URLSearchParams();
      new FormData(form).forEach((v, k) => p.append(k, v));
      history.replaceState(null, '', location.pathname + '?' + p.toString());
      return p;
    },
    initFilterFromUrl(form) {
      const u = new URLSearchParams(location.search);
      u.forEach((v, k) => { const el = form.elements[k]; if (el) el.value = v; });
    },

    /**
     * Realtime: polling versi data. onChange dipanggil bila ada data baru dari inputan user.
     * Berhenti sementara ketika tab tidak terlihat.
     */
    live(onChange, indicator) {
      let version = null, timer = null;
      const sec = Math.max(5, cfg.refresh || 15);
      const setInd = (ok, text) => {
        if (!indicator) return;
        indicator.classList.toggle('off', !ok);
        indicator.querySelector('.txt').textContent = text;
      };
      const tick = async () => {
        if (document.hidden) return;
        try {
          const r = await DMS.fetchJson(cfg.versionUrl);
          if (version && r.version !== version) await onChange();
          version = r.version;
          setInd(true, 'Live '  + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
        } catch (e) { setInd(false, 'Offline'); }
      };
      const api = {
        setVersion(v) { version = v; setInd(true, 'Live '  + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })); },
        start() { clearInterval(timer); timer = setInterval(tick, sec * 1000); },
      };
      document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
      api.start();
      return api;
    },

    /** Ekspor tabel HTML / array ke .xlsx (SheetJS, file lokal). */
    exportXlsx(filename, sheetName, aoaOrTable) {
      if (!window.XLSX) { DMS.toast('Fitur Excel belum siap, coba lagi sebentar.', 'warning', 4000, { title: 'Tunggu sebentar' }); return; }
      const wb = XLSX.utils.book_new();
      const ws = Array.isArray(aoaOrTable) ? XLSX.utils.aoa_to_sheet(aoaOrTable) : XLSX.utils.table_to_sheet(aoaOrTable, { raw: true });
      XLSX.utils.book_append_sheet(wb, ws, sheetName.slice(0, 31));
      // Nama file hanya karakter standar: tanda "–" (rentang bulan) dll. membuat browser menamai file "download".
      const nama = filename.replace(/[–—]/g, '-').replace(/[\\/:*?"<>|]/g, '-').replace(/[^\x20-\x7E]/g, '').replace(/\s+/g, ' ').trim() + '.xlsx';
      try {
        XLSX.writeFile(wb, nama);
        DMS.toast(`<b>${DMS.esc(nama)}</b> sedang diunduh.`, 'success', 4000, { title: 'File Excel dibuat' });
      } catch (e) {
        console.error(e);
        DMS.toast('File Excel tidak bisa dibuat. Coba lagi.', 'danger', 6000, { title: 'Gagal ekspor' });
      }
    },
  };

  // ---------- Grafik (Chart.js) ----------
  if (window.Chart) {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#4f5551';
    Chart.defaults.borderColor = '#eceeea';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(31,36,33,.92)';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.animation.duration = 300;
    Chart.defaults.maintainAspectRatio = false;

  }

  DMS.charts = {
    _reg: new Map(),
    upsert(canvas, config) {
      const old = this._reg.get(canvas);
      if (old && old.config.type === config.type) {
        old.data.labels = config.data.labels;
        config.data.datasets.forEach((ds, i) => {
          if (old.data.datasets[i]) Object.assign(old.data.datasets[i], ds); else old.data.datasets.push(ds);
        });
        old.data.datasets.length = config.data.datasets.length;
        // Ganti opsi secara utuh agar callback tooltip memakai data terbaru (bukan data filter sebelumnya).
        old.options = config.options || {};
        old.update();
        return old;
      }
      old?.destroy();
      const c = new Chart(canvas, config);
      this._reg.set(canvas, c);
      return c;
    },

    /** Donut status jam (pengganti pie AVAILABILITY Tableau). */
    donut(canvas, jam, opts = {}) {
      const labels = Object.keys(jam);
      const values = labels.map((k) => jam[k]);
      const total = values.reduce((a, b) => a + b, 0);
      return this.upsert(canvas, {
        type: 'doughnut',
        plugins: window.ChartDataLabels ? [ChartDataLabels] : [],
        data: { labels: labels.map((l) => DMS.label(l)), datasets: [{ data: values, backgroundColor: labels.map((l) => DMS.warna[l]), borderColor: '#fff', borderWidth: 2, hoverOffset: 6 }] },
        options: {
          cutout: '62%',
          layout: { padding: 6 },
          plugins: {
            legend: { display: opts.legend ?? false, position: 'bottom' },
            tooltip: { callbacks: { label: (c) => ` ${c.label}: ${DMS.num(c.raw)} jam (${total ? DMS.num1(c.raw / total * 100) : 0}%)` } },
            datalabels: {
              display: (c) => total > 0 && c.dataset.data[c.dataIndex] / total >= 0.06,
              formatter: (v) => DMS.num1(v / total * 100) + '%',
              color: (c) => ['STANDBY'].includes(labels[c.dataIndex]) ? '#1f2421' : '#fff',
              font: { weight: 700, size: opts.small ? 10 : 12 },
            },
          },
        },
      });
    },

    /** Bar rata-rata unit utilitas (JALAN / STANDBY / RUSAK). */
    utilitas(canvas, labels, rows, opts = {}) {
      const ds = [['jalan', 'Jalan', 'util_jalan'], ['standby', 'Standby', 'util_standby'], ['rusak', 'Rusak', 'util_rusak']].map(([k, label, w]) => ({
        // Unit ditampilkan utuh: nilai rata-rata dibulatkan ke unit terdekat.
        label, data: rows.map((r) => (r[k] === null || r[k] === undefined) ? null : Math.round(r[k])), backgroundColor: DMS.warna[w], borderColor: DMS.warna[w],
        borderRadius: opts.type === 'line' ? 0 : 4, borderSkipped: 'start', maxBarThickness: 34,
        borderWidth: opts.type === 'line' ? 2 : 0, pointRadius: opts.type === 'line' ? 3 : 0, pointHoverRadius: 5, cubicInterpolationMode: 'monotone',
      }));
      return this.upsert(canvas, {
        type: opts.type || 'bar',
        data: { labels, datasets: ds },
        options: {
          indexAxis: opts.horizontal ? 'y' : 'x',
          // Sumbu acuan tooltip mengikuti arah grafik: horizontal → per baris (y), vertikal → per kolom (x).
          interaction: { mode: 'index', intersect: false, axis: opts.horizontal ? 'y' : 'x' },
          scales: {
            x: { stacked: false, grid: { display: !!opts.horizontal }, beginAtZero: true, title: { display: !!opts.xTitle, text: opts.xTitle },
              ticks: opts.horizontal ? { precision: 0 } : {} },
            y: { beginAtZero: true, grid: { display: !opts.horizontal }, title: { display: !!opts.yTitle, text: opts.yTitle },
              ticks: opts.horizontal ? {} : { precision: 0 } },
          },
          plugins: {
            legend: { position: 'top', align: 'end' },
            tooltip: { position: 'nearest', callbacks: {
              label: (c) => ` ${c.dataset.label}: ${DMS.unit(c.raw)} unit`,
            } },
          },
        },
      });
    },
  };

  // ---------- Cari nomor kendaraan ----------
  document.querySelectorAll('form.cari-unit').forEach((form) => {
    const inp = form.querySelector('input'), list = form.querySelector('.cari-hasil');
    let hasil = [], aktif = -1, timer = null, urut = 0;
    const tutup = () => { list.hidden = true; inp.setAttribute('aria-expanded', 'false'); aktif = -1; };
    const tandai = (i) => {
      aktif = i;
      list.querySelectorAll('[role=option]').forEach((li, n) => li.setAttribute('aria-selected', n === i ? 'true' : 'false'));
      const el = list.querySelector(`[data-i="${i}"]`);
      if (el) { inp.setAttribute('aria-activedescendant', el.id); el.scrollIntoView({ block: 'nearest' }); }
    };
    const tampil = () => {
      list.innerHTML = hasil.length ? hasil.map((h, i) => `<li role="option" id="${list.id}-${i}" data-i="${i}" aria-selected="false">
          <b>${DMS.esc(h.nomor)}</b>${h.aktif ? '' : ' <span class="badge text-bg-secondary">Nonaktif</span>'}<span>${DMS.esc(h.jenis)} · ${DMS.esc(h.bidang)}</span></li>`).join('')
        : `<li class="kosong">Tidak ada nomor yang cocok dengan "${DMS.esc(inp.value.trim())}".</li>`;
      list.hidden = false; inp.setAttribute('aria-expanded', 'true');
      if (hasil.length) tandai(0);
    };
    const cari = async () => {
      const q = inp.value.trim();
      if (!q) { hasil = []; tutup(); return; }
      const n = ++urut;
      try {
        const r = await DMS.fetchJson(`${cfg.cariUrl}?q=${encodeURIComponent(q)}`);
        if (n !== urut) return; // jawaban lama, abaikan
        hasil = r.hasil; tampil();
      } catch (_) { /* jaringan: diam saja */ }
    };
    const buka = (i) => { const h = hasil[i]; if (h) location.href = h.url; };
    inp.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(cari, 150); });
    inp.addEventListener('keydown', async (e) => {
      if (e.key === 'ArrowDown' && hasil.length) { e.preventDefault(); if (list.hidden) tampil(); tandai((aktif + 1) % hasil.length); }
      else if (e.key === 'ArrowUp' && hasil.length) { e.preventDefault(); tandai((aktif - 1 + hasil.length) % hasil.length); }
      else if (e.key === 'Escape') { tutup(); inp.blur(); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(timer);
        if (!hasil.length || list.hidden) await cari();
        if (hasil.length) buka(aktif >= 0 ? aktif : 0);
        else if (inp.value.trim()) DMS.toast(`Nomor "${DMS.esc(inp.value.trim())}" tidak ditemukan.`, 'warning', 3500, { title: 'Tidak ditemukan' });
      }
    });
    list.addEventListener('mousedown', (e) => { const li = e.target.closest('[data-i]'); if (li) { e.preventDefault(); buka(+li.dataset.i); } });
    inp.addEventListener('blur', () => setTimeout(tutup, 120));
    inp.addEventListener('focus', () => { if (hasil.length && inp.value.trim()) tampil(); });
  });
  // Tekan "/" di mana saja (selain saat mengetik) untuk langsung mencari.
  document.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = e.target;
    if (t.closest('input, textarea, select, [contenteditable="true"], dialog')) return;
    const kotak = [...document.querySelectorAll('form.cari-unit input')].find((i) => i.offsetParent !== null);
    if (kotak) { e.preventDefault(); kotak.focus(); kotak.select(); }
  });

  // ---------- Keluar otomatis bila tidak aktif ----------
  // Aktivitas dicatat bersama di semua tab (localStorage), peringatan 1 menit sebelum keluar.
  if (cfg.idleMenit > 0 && cfg.logoutUrl) {
    const BATAS = cfg.idleMenit * 60 * 1000, KUNCI = 'dms-aktif';
    let catatLokal = Date.now(), terakhirPing = Date.now(), peringatan = false;
    const baca = () => { try { return Math.max(+localStorage.getItem(KUNCI) || 0, catatLokal); } catch (_) { return catatLokal; } };
    const tandai = () => {
      const k = Date.now();
      if (k - catatLokal < 5000) return;
      catatLokal = k;
      try { localStorage.setItem(KUNCI, String(k)); } catch (_) { /* abaikan */ }
    };
    ['mousedown', 'keydown', 'touchstart', 'wheel', 'scroll'].forEach((ev) => window.addEventListener(ev, tandai, { passive: true, capture: true }));
    tandai(); catatLokal = Date.now();
    const keluar = (alasan) => {
      window.__dmsKeluar = true; // lewati peringatan "perubahan belum disimpan" (isian aman di draft perangkat)
      const f = document.createElement('form');
      f.method = 'POST'; f.action = cfg.logoutUrl;
      f.innerHTML = `<input type="hidden" name="_token" value="${csrf}">${alasan ? `<input type="hidden" name="alasan" value="${alasan}">` : ''}`;
      document.body.appendChild(f); f.submit();
    };
    setInterval(async () => {
      const diam = Date.now() - baca();
      if (diam >= BATAS) { keluar('tidak_aktif'); return; }
      // Kabari server bahwa pengguna masih aktif (maks. 1x per 5 menit).
      if (baca() > terakhirPing && Date.now() - terakhirPing > 5 * 60 * 1000) {
        terakhirPing = Date.now();
        DMS.fetchJson(cfg.aktifUrl, { method: 'POST', body: '{}' }).catch(() => {});
      }
      if (diam >= BATAS - 60 * 1000 && !peringatan) {
        peringatan = true;
        const tetap = await DMS.confirm({ title: 'Masih di sana?', tone: 'warning', icon: 'fa-hourglass-half', ok: 'Tetap masuk', cancel: 'Keluar sekarang',
          text: 'Anda akan keluar otomatis dalam 1 menit karena tidak ada aktivitas. Isian yang belum disimpan tetap aman di perangkat ini.' });
        peringatan = false;
        if (Date.now() - baca() >= BATAS) return; // sudah lewat batas, interval berikut akan keluar
        if (tetap) { catatLokal = 0; tandai(); terakhirPing = Date.now(); DMS.fetchJson(cfg.aktifUrl, { method: 'POST', body: '{}' }).catch(() => {}); }
        else keluar('');
      }
    }, 10000);
  }

  // ---------- Tombol panduan "?" di samping judul halaman ----------
  const panduan = document.getElementById('panduan');
  const judul = document.querySelector('.page-head h1');
  if (panduan && judul) {
    judul.insertAdjacentHTML('beforeend', ' <button type="button" class="btn-panduan" data-bs-toggle="offcanvas" data-bs-target="#panduan" aria-controls="panduan" title="Panduan halaman ini" aria-label="Panduan halaman ini"><i class="fas fa-question" aria-hidden="true"></i></button>');
  }
  // Tekan "?" (Shift+/) untuk membuka panduan
  document.addEventListener('keydown', (e) => {
    if (e.key !== '?' || !panduan || e.target.closest('input, textarea, select, [contenteditable="true"], dialog')) return;
    e.preventDefault(); bootstrap.Offcanvas.getOrCreateInstance(panduan).toggle();
  });

  // ---------- Pasang sebagai aplikasi (PWA) ----------
  let ajakanPasang = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    ajakanPasang = e;
    const item = document.getElementById('itemPasang');
    if (item) item.hidden = false;
  });
  document.getElementById('btnPasang')?.addEventListener('click', async () => {
    if (!ajakanPasang) return;
    ajakanPasang.prompt();
    const { outcome } = await ajakanPasang.userChoice;
    ajakanPasang = null;
    document.getElementById('itemPasang').hidden = true;
    if (outcome === 'accepted') DMS.toast('DMS v2 terpasang. Buka dari ikon DMS v2 di layar utama.', 'success', 5000, { title: 'Terpasang' });
  });
  window.addEventListener('appinstalled', () => { const i = document.getElementById('itemPasang'); if (i) i.hidden = true; });

  // ---------- Sidebar ----------
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-toggle-sidebar]')) document.body.classList.toggle('sidebar-open');
    else if (e.target.classList.contains('sidebar-backdrop')) document.body.classList.remove('sidebar-open');
  });

  // Tombol "?" penjelasan (popover). Konten boleh berisi HTML sederhana (b, br, ul, li, span.rumus).
  function pasangInfo(root = document) {
    if (!window.bootstrap?.Popover) return;
    const izin = { ...bootstrap.Popover.Default.allowList, span: ['class'], b: [], br: [], ul: [], li: [], small: [] };
    root.querySelectorAll('.info-q[data-bs-toggle="popover"]').forEach((el) => {
      if (bootstrap.Popover.getInstance(el)) return;
      new bootstrap.Popover(el, { allowList: izin, container: 'body', fallbackPlacements: ['bottom', 'right', 'left', 'top'] });
      el.addEventListener('keydown', (e) => { if (e.key === 'Escape') bootstrap.Popover.getInstance(el)?.hide(); });
      el.addEventListener('click', (e) => e.preventDefault());
    });
  }
  DMS.pasangInfo = pasangInfo;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => pasangInfo());
  else pasangInfo();

  /**
   * Jendela pilihan sebelum Laporan PDF / Excel: satu bulan atau beberapa bulan, dan bidang.
   * awal: { tahun, bulan, bidang } sebagai nilai awal (biasanya dari filter halaman).
   * Hasil: Promise<{ dari: 'YYYY-MM', ke: 'YYYY-MM', bidang: kode|'' } | null bila dibatalkan>.
   */
  DMS.pilihUnduh = function ({ format = 'pdf', judul, tahun, bulan, bidang } = {}) {
    const m = document.getElementById('modalUnduh');
    if (!m || !window.bootstrap) return Promise.resolve(null);
    const f = document.getElementById('formUnduh');
    const el = (id) => document.getElementById(id);
    const kini = new Date();
    const t0 = String(tahun || kini.getFullYear()), b0 = String(bulan || kini.getMonth() + 1);
    const pasang = (sel, v) => { if ([...sel.options].some((o) => o.value === v)) sel.value = v; };
    pasang(el('unDariT'), t0); pasang(el('unDariB'), b0); pasang(el('unKeT'), t0); pasang(el('unKeB'), b0);
    if (!el('unBidang').disabled) pasang(el('unBidang'), bidang || '');
    f.jenisPeriode.value = 'satu';
    el('muJudul').textContent = judul || (format === 'pdf' ? 'Laporan PDF' : 'Unduh Excel');
    el('unOk').innerHTML = format === 'pdf' ? '<i class="fas fa-file-pdf" aria-hidden="true"></i> Buka laporan' : '<i class="fas fa-file-excel" aria-hidden="true"></i> Unduh Excel';
    const ym = (t, b) => `${t}-${String(b).padStart(2, '0')}`;
    const sinkron = () => {
      const rentang = f.jenisPeriode.value === 'rentang';
      el('unKeBaris').hidden = !rentang;
      el('unDariLbl').textContent = rentang ? 'Dari' : 'Bulan';
      const a = ym(el('unDariT').value, el('unDariB').value), z = ym(el('unKeT').value, el('unKeB').value);
      const [x, y] = a <= z ? [a, z] : [z, a];
      const n = (+y.slice(0, 4) - +x.slice(0, 4)) * 12 + (+y.slice(5) - +x.slice(5)) + 1;
      el('unInfo').hidden = !rentang;
      el('unInfo').textContent = n > 24 ? 'Maksimal 24 bulan; yang dipakai 24 bulan terakhir dari rentang ini.' : `${n} bulan.`;
    };
    f.onchange = sinkron;
    sinkron();
    return new Promise((selesai) => {
      let hasil = null;
      const modal = bootstrap.Modal.getOrCreateInstance(m);
      f.onsubmit = (e) => {
        e.preventDefault();
        const dari = ym(el('unDariT').value, el('unDariB').value);
        const ke = f.jenisPeriode.value === 'rentang' ? ym(el('unKeT').value, el('unKeB').value) : dari;
        hasil = { dari: dari <= ke ? dari : ke, ke: dari <= ke ? ke : dari, bidang: el('unBidang').value };
        // Selesaikan di sini (masih dalam klik pengguna) agar jendela PDF tidak diblokir browser.
        selesai(hasil);
        modal.hide();
      };
      m.addEventListener('hidden.bs.modal', () => { if (!hasil) selesai(null); }, { once: true });
      modal.show();
    });
  };

  // Tombol "Laporan PDF" (Beranda, Laporan): pilih periode & bidang dulu, lalu buka laporan di tab baru.
  document.querySelectorAll('[data-laporan-pdf]').forEach((btn) => btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const fm = document.getElementById('filterForm');
    const pil = await DMS.pilihUnduh({ format: 'pdf', tahun: fm?.elements.tahun?.value, bulan: fm?.elements.bulan?.value, bidang: fm?.elements.bidang?.value });
    if (!pil) return;
    const q = new URLSearchParams({ dari: pil.dari, ke: pil.ke, cetak: 1 });
    if (pil.bidang) q.set('bidang', pil.bidang);
    // Tanpa fitur 'noopener' (yang membuat window.open selalu null); opener diputus manual.
    const w = window.open(btn.dataset.laporanPdf + '?' + q, '_blank');
    if (w) w.opener = null; else location.href = btn.dataset.laporanPdf + '?' + q;
  }));

  window.DMS = DMS;
})();
