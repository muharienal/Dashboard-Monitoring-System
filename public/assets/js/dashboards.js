/* Dashboard realtime (pengganti Tableau). Data diambil dari endpoint JSON Laravel, langsung dari database. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const STATUS = ['JALAN', 'STANDBY', 'RUSAK', 'PERBAIKAN', 'PM'];
  const W = () => DMS.warna;
  const L = (k) => DMS.label(k);

  /** Kerangka umum: filter -> ambil data -> tampilkan, lalu perbarui otomatis bila ada input baru. */
  function boot(getUrl, render) {
    const form = $('#filterForm');
    DMS.initFilterFromUrl(form);
    let busy = false, pending = false, last = null;
    const load = async () => {
      if (busy) { pending = true; return; }
      busy = true;
      try {
        const url = typeof getUrl === 'function' ? getUrl() : getUrl;
        last = await DMS.fetchJson(url + '?' + DMS.filterParams(form).toString());
        render(last);
        live.setVersion(last.version);
      } catch (e) {
        if (e.message !== 'unauthenticated') DMS.toast('Data gagal dimuat. ' + DMS.esc(e.message), 'danger');
      } finally {
        busy = false;
        if (pending) { pending = false; load(); }
      }
    };
    // Jenis menyesuaikan bidang yang dipilih
    const map = form.dataset.jenisMap ? JSON.parse(form.dataset.jenisMap) : null;
    const selB = form.elements.bidang, selJ = form.elements.jenis;
    const semuaJenis = selJ ? [...selJ.options].map((o) => o.value).filter(Boolean) : [];
    const isiJenis = () => {
      if (!map || !selB || !selJ) return;
      const daftar = selB.value ? (map[selB.value] || []) : semuaJenis;
      const pilih = selJ.value;
      selJ.innerHTML = '<option value="">Semua</option>' + daftar.map((j) => `<option value="${DMS.esc(j)}">${DMS.esc(j)}</option>`).join('');
      selJ.value = daftar.includes(pilih) ? pilih : '';
    };
    isiJenis();
    form.addEventListener('change', (e) => {
      if (e.target.dataset.local !== undefined) return;   // kotak cari tidak memuat ulang data
      if (e.target === selB) isiJenis();
      load();
    });
    // Kotak cari: menyaring data yang sudah dimuat, langsung saat mengetik
    form.addEventListener('input', (e) => { if (e.target.dataset.local !== undefined && last) render(last); });
    const live = DMS.live(load, $('#liveInd'));
    load();
    return { get data() { return last; }, reload: load };
  }

  const cari = () => ($('#f-cari')?.value || '').trim().toLowerCase();
  const cocok = (term, ...teks) => !term || teks.some((t) => String(t ?? '').toLowerCase().includes(term));
  const empty = (msg = 'Belum ada data untuk periode ini.') => `<div class="empty-state"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>${msg}</div>`;

  /** Legenda status + jam + persen (menggantikan tabel terpisah). */
  function legendJam(jam) {
    const total = STATUS.reduce((a, s) => a + (jam[s] || 0), 0);
    return STATUS.map((s) => `<span><span class="legend-dot" style="background:${W()[s]}"></span>${L(s)}</span>
      <span></span><span class="n">${DMS.num(jam[s])} jam</span><span class="p">${total ? DMS.num1(jam[s] / total * 100) + '%' : '–'}</span>`).join('');
  }

  /** Tampilkan grafik atau pesan kosong. */
  function toggle(boxEl, emptyEl, has, msg) {
    emptyEl.innerHTML = has ? '' : empty(msg);
    boxEl.classList.toggle('d-none', !has);
  }

  function barPerbaikan(canvas, rows, labelFn) {
    return DMS.charts.upsert(canvas, {
      type: 'bar',
      plugins: window.ChartDataLabels ? [ChartDataLabels] : [],
      data: { labels: rows.map(labelFn), datasets: [{ label: 'Jam perbaikan', data: rows.map((r) => r.perbaikan), backgroundColor: W().PERBAIKAN, borderRadius: 4, borderSkipped: 'start', barThickness: 15 }] },
      options: {
        indexAxis: 'y',
        interaction: { mode: 'index', intersect: false, axis: 'y' },
        layout: { padding: { right: 36 } },
        // Klik batang → profil kendaraan
        onClick: (_e, els) => { const r = els.length ? rows[els[0].index] : null; if (r && r.id) location.href = `${window.DMS_CONFIG.profilUrl}/${r.id}`; },
        onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
        scales: { x: { beginAtZero: true, title: { display: true, text: 'jam' } }, y: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 11 } } } },
        plugins: {
          legend: { display: false },
          datalabels: { anchor: 'end', align: 'end', color: '#555b57', font: { size: 10, weight: 600 }, formatter: (v) => DMS.num(v) },
          tooltip: { callbacks: {
            title: (c) => `${rows[c[0].dataIndex].nomor} · ${rows[c[0].dataIndex].jenis}`,
            label: (c) => ` Perbaikan: ${DMS.num(c.raw)} jam`,
            afterLabel: (c) => { const r = rows[c.dataIndex]; return [`Rata-rata ${DMS.num(r.rata_rata)} jam/hari`, `PM ${r.pm} jam · Rusak ${r.rusak} jam`, 'Klik untuk membuka profil unit']; },
          } },
        },
      },
    });
  }

  // ---------- Tabel detail per kendaraan (kolom = tanggal atau bulan) ----------
  function filterDetail(rep) {
    const term = cari();
    return term ? { ...rep, unit: rep.unit.filter((u) => cocok(term, u.nomor, u.jenis, u.bidang)) } : rep;
  }

  function renderDetail(el, rep) {
    rep = filterDetail(rep);
    if (!rep.unit.length) { el.innerHTML = empty(cari() ? 'Tidak ada kendaraan yang cocok.' : undefined); return; }
    const kolLabel = (k) => rep.mode === 'hari' ? k : DMS.periodeLabel(k);
    const head = `<thead><tr><th class="text-end">No</th><th class="sticky-col">Bidang</th><th>Jenis</th><th>Nomor</th><th>Status</th>
      ${rep.kolom.map((k) => `<th class="text-end">${kolLabel(k)}</th>`).join('')}<th class="text-end">Total</th></tr></thead>`;
    const body = rep.unit.map((u, n) => STATUS.map((st, i) => {
      const vals = rep.kolom.map((k) => u.nilai[st]?.[k]);
      const tot = vals.reduce((a, v) => a + (v || 0), 0);
      return `<tr class="${i === 0 ? 'first' : ''}">
        <td class="v text-secondary">${i === 0 ? n + 1 : ''}</td>
        <td class="grp">${i === 0 ? DMS.esc(u.bidang) : ''}</td>
        <td>${i === 0 ? DMS.esc(u.jenis) : ''}</td><td class="fw-semibold">${i === 0 ? DMS.linkUnit(u.id, u.nomor) : ''}</td>
        <td class="st"><span class="legend-dot" style="background:${W()[st]}"></span>${L(st)}</td>
        ${vals.map((v) => v === undefined ? '<td class="v v0">·</td>' : `<td class="v ${v ? '' : 'v0'}">${v}</td>`).join('')}
        <td class="v fw-bold">${tot}</td></tr>`;
    }).join('')).join('');
    el.innerHTML = `<div class="table-wrap" style="max-height:70vh"><table class="table table-dms table-sm xtab sticky-head">${head}<tbody>${body}</tbody></table></div>
      <div class="small text-secondary mt-2">${rep.unit.length} kendaraan · angka dalam jam · <b>·</b> = belum diinput${rep.mode === 'bulan' ? ' · pilih bulan untuk melihat per tanggal' : ''}</div>`;
  }

  function aoaDetail(rep, judul) {
    rep = filterDetail(rep);
    const aoa = [[judul], ['Bidang', 'Jenis', 'Nomor', 'Status', ...rep.kolom.map((k) => rep.mode === 'hari' ? +k : k), 'Total']];
    rep.unit.forEach((u) => STATUS.forEach((st) => {
      const vals = rep.kolom.map((k) => u.nilai[st]?.[k] ?? '');
      aoa.push([u.bidang, u.jenis, u.nomor, L(st), ...vals, vals.reduce((a, v) => a + (+v || 0), 0)]);
    }));
    return aoa;
  }

  /**
   * Perubahan KPI dibanding periode sebelumnya (bulan lalu / tahun lalu, dipotong di tanggal yang sama
   * bila periode masih berjalan). Selisih dihitung dari angka yang tampil (1 desimal) agar konsisten.
   */
  function renderPembanding(a, p, sel = {}) {
    const el = { ar: $(sel.ar || '#dAr'), util: $(sel.util || '#dUtil'), unit: sel.unit === null ? null : $(sel.unit || '#dUnit'),
      jalan: sel.jalan ? $(sel.jalan) : null, perbaikan: $(sel.perbaikan || '#dPerbaikan') };
    Object.keys(el).forEach((k) => { if (!el[k]) delete el[k]; });
    if (!el.ar) return;
    if (!p) { Object.values(el).forEach((e) => { e.innerHTML = ''; e.className = 'kpi-delta'; }); return; }
    const b = p.availability;
    const r1 = (v) => (v === null || v === undefined) ? null : Math.round(v * 10) / 10;
    // arahBaik: +1 = naik itu baik, -1 = turun itu baik, 0 = netral
    const tulis = (e, kini, lalu, satuan, arahBaik, desimal) => {
      if (kini === null || lalu === null || !b.unit_hari) {
        e.innerHTML = `<span class="kd-ket">Belum ada data ${DMS.esc(p.label)}</span>`; e.className = 'kpi-delta netral'; return;
      }
      const sel = desimal ? Math.round((kini - lalu) * 10) / 10 : kini - lalu;
      const fmt = (v) => desimal ? DMS.num1(Math.abs(v)) : DMS.num(Math.abs(v));
      if (sel === 0) { e.innerHTML = `<span class="kd-ket">sama dengan ${DMS.esc(p.label)}</span>`; e.className = 'kpi-delta netral'; return; }
      const naik = sel > 0;
      const nada = arahBaik === 0 ? 'netral' : ((naik ? 1 : -1) === arahBaik ? 'baik' : 'buruk');
      e.className = `kpi-delta ${nada}`;
      e.innerHTML = `<span class="kd-angka"><span aria-hidden="true">${naik ? '▲' : '▼'}</span><span class="visually-hidden">${naik ? 'naik' : 'turun'}</span> ${fmt(sel)} ${satuan}</span> <span class="kd-ket">dari ${DMS.esc(p.label)}</span>`;
      e.title = `${p.label}: ${desimal ? DMS.num1(lalu) : DMS.num(lalu)}${satuan === 'poin' ? '%' : ' ' + satuan}`;
    };
    tulis(el.ar, r1(a.availability_rate), r1(b.availability_rate), 'poin', 1, true);
    tulis(el.util, r1(a.utilisasi), r1(b.utilisasi), 'poin', 1, true);
    if (el.unit) tulis(el.unit, a.unit_hari ? a.unit : null, b.unit_hari ? b.unit : null, 'unit', 0, false);
    if (el.jalan) tulis(el.jalan, a.unit_hari ? Math.round(a.avg_jalan) : null, b.unit_hari ? Math.round(b.avg_jalan) : null, 'unit', 1, false);
    tulis(el.perbaikan, a.unit_hari ? a.jam.PERBAIKAN : null, b.unit_hari ? b.jam.PERBAIKAN : null, 'jam', -1, false);
  }

  /** Update input harian per bidang (kemarin lusa, kemarin & hari ini) di Beranda: kapan terakhir petugas menyimpan. */
  function renderKelengkapan(kl) {
    const box = $('#kelengkapan');
    if (!box || !kl || !kl.length) return;
    const hariIni = kl[kl.length - 1].tanggal;
    const nama = ['Kemarin lusa', 'Kemarin', 'Hari ini'].slice(-kl.length);
    const judulTgl = (t) => `${nama[kl.findIndex((h) => h.tanggal === t)]}, ${DMS.periodeLabel(t)}`;
    const jam = (iso) => iso.slice(11, 16).replace(':', '.');
    // Jam saja bila disimpan di hari yang sama; selain itu sertakan tanggal simpan.
    const waktu = (h, iso) => (iso.slice(0, 10) === h.tanggal ? jam(iso) : `${DMS.periodeLabel(iso.slice(0, 10))} ${jam(iso)}`);
    const adaUnit = (b) => b.unit > 0;
    const kemarinBelum = kl.slice(0, -1).some((h) => h.bidang.some((b) => adaUnit(b) && !b.terakhir));
    const semuaUpdate = kl.every((h) => h.bidang.every((b) => !adaUnit(b) || b.terakhir));
    box.classList.remove('d-none');
    box.classList.toggle('ok', semuaUpdate);
    box.classList.toggle('warn', kemarinBelum);
    const link = (h, b, teks, kelas) => (b.bisa_input
      ? `<a class="btn btn-sm ${kelas}" href="${window.PAGE.inputBase}/${b.kode}?tanggal=${h.tanggal}">${teks}</a>` : '');
    const sel = (h, b) => {
      if (!adaUnit(b)) return '<span class="kl-none">Tidak ada unit</span>';
      if (b.terakhir) {
        return `<div class="kl-sel lebar"><div class="kl-angka"><b>Diperbarui ${waktu(h, b.terakhir)}</b>${b.oleh ? `<small>oleh ${DMS.esc(b.oleh)}</small>` : ''}</div>${link(h, b, 'Buka', 'btn-outline-secondary')}</div>`;
      }
      return `<div class="kl-sel lebar ${h.tanggal === hariIni ? 'jalan' : 'telat'}"><div class="kl-angka"><b>Belum ada update</b></div>${link(h, b, 'Isi', h.tanggal === hariIni ? 'btn-outline-secondary' : 'btn-gold')}</div>`;
    };
    const bidang = kl[0].bidang.map((b, i) => ({ nama: b.nama, sel: kl.map((h) => [h, h.bidang[i]]) }));
    // Ringkasan satu baris: update terakhir hari ini. Rincian per bidang dibuka lewat klik.
    const terbaru = kl[kl.length - 1].bidang.filter((b) => b.terakhir).sort((x, y) => (x.terakhir < y.terakhir ? 1 : -1))[0];
    const ringkas = terbaru
      ? `Update terakhir hari ini <b>${jam(terbaru.terakhir)}</b> · ${DMS.esc(terbaru.nama)}${terbaru.oleh ? ' oleh ' + DMS.esc(terbaru.oleh) : ''}`
      : 'Hari ini <b>belum ada update</b>';
    const buka = klTerbuka();
    box.classList.toggle('terbuka', buka);
    const html = `<div class="kl-atas"><button type="button" class="kl-head kl-toggle" aria-expanded="${buka}" aria-controls="klIsi">
        <span class="kl-ikon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
        <span class="kl-teks"><strong id="klJudul">Update input harian</strong><span>${ringkas}</span></span>
        <span class="kl-aksi">${buka ? 'Tutup' : 'Lihat bidang'} <i class="fas fa-chevron-down" aria-hidden="true"></i></span></button></div>
      <div class="kl-tabel" id="klIsi" role="table" aria-label="Update input per bidang" ${buka ? '' : 'hidden'}>
        <div class="kl-baris kl-kepala" role="row" style="--kl-hari:${kl.length}"><span role="columnheader">Bidang</span>${kl.map((h) => `<span role="columnheader">${judulTgl(h.tanggal)}</span>`).join('')}</div>
        ${bidang.map((r) => `<div class="kl-baris" role="row" style="--kl-hari:${kl.length}"><span class="kl-nama" role="rowheader">${DMS.esc(r.nama)}</span>
          ${r.sel.map(([h, b]) => `<div role="cell" data-l="${judulTgl(h.tanggal)}">${sel(h, b)}</div>`).join('')}</div>`).join('')}
      </div>`;
    if (box.dataset.html !== html) {
      box.querySelectorAll('.info-q').forEach((el) => window.bootstrap?.Popover.getInstance(el)?.dispose());
      box.innerHTML = html;
      box.dataset.html = html;
      DMS.pasangInfo(box);
    }
    if (!box.dataset.siap) {
      box.dataset.siap = '1';
      // Satu pendengar untuk semua render ulang (data diperbarui otomatis tiap beberapa detik).
      box.addEventListener('click', (e) => {
        const t = e.target.closest('.kl-toggle');
        if (!t) return;
        const kini = t.getAttribute('aria-expanded') !== 'true';
        simpanKl(kini);
        t.setAttribute('aria-expanded', kini);
        box.classList.toggle('terbuka', kini);
        $('#klIsi').hidden = !kini;
        t.querySelector('.kl-aksi').firstChild.textContent = kini ? 'Tutup ' : 'Lihat bidang ';
      });
    }
  }
  // Pilihan buka/tutup diingat per browser (bila penyimpanan tidak tersedia, kembali tertutup).
  let klBuka = null;
  function klTerbuka() {
    if (klBuka === null) { try { klBuka = localStorage.getItem('dms-kl-buka') === '1'; } catch (_) { klBuka = false; } }
    return klBuka;
  }
  function simpanKl(v) {
    klBuka = v;
    try { localStorage.setItem('dms-kl-buka', v ? '1' : '0'); } catch (_) { /* abaikan */ }
  }

  /**
   * Excel: pilih periode (satu/beberapa bulan) & bidang lewat jendela pilihan, ambil data dari server
   * dengan pilihan itu (filter lain di halaman, mis. Jenis, tetap dipakai), lalu susun & unduh.
   * url: string atau fungsi; susun(d) → array baris (aoa) atau elemen tabel.
   */
  async function eksporPilih(url, nama, sheet, susun) {
    const fm = $('#filterForm');
    const pil = await DMS.pilihUnduh({ format: 'excel', judul: 'Unduh Excel ' + nama, tahun: fm.elements.tahun?.value, bulan: fm.elements.bulan?.value, bidang: fm.elements.bidang?.value });
    if (!pil) return;
    const q = DMS.filterParams(fm);
    ['tahun', 'bulan', 'hari', 'bidang', 'cari'].forEach((k) => q.delete(k));
    q.set('dari', pil.dari); q.set('ke', pil.ke); q.set('ekspor', 1);
    if (pil.bidang) q.set('bidang', pil.bidang);
    try {
      const d = await DMS.fetchJson((typeof url === 'function' ? url() : url) + '?' + q.toString());
      const label = d.filter.label + (d.filter.bidang_nama ? ' ' + d.filter.bidang_nama : '');
      DMS.exportXlsx(`${nama} ${label}`, sheet, susun(d, `${nama} ${label}`));
    } catch (e) {
      if (e.message !== 'unauthenticated') DMS.toast(DMS.esc(e.message), 'danger', 6000, { title: 'Gagal ekspor' });
    }
  }

  /** Ringkasan Beranda dalam kalimat untuk manajemen (dibangun dari data yang sama dengan angka di halaman). */
  function ringkasanBeranda(d) {
    const a = d.availability, p = d.pembanding, b = p?.availability;
    if (!a.unit_hari) return ['<span class="text-secondary">Belum ada data untuk periode ini. Pilih bulan lain di filter, atau pastikan petugas sudah menginput.</span>'];
    const out = [];
    const banding = (kini, lalu, satuan, arahBaik) => {
      if (kini === null || lalu === null || !b?.unit_hari) return '';
      // Sama persis dengan kotak KPI: selisih dari angka yang tampil (dibulatkan 1 desimal).
      const r1 = (v) => Math.round(v * 10) / 10;
      const sel = Math.round((r1(kini) - r1(lalu)) * 10) / 10;
      if (sel === 0) return ` (sama dengan ${DMS.esc(p.label)})`;
      const baik = (sel > 0 ? 1 : -1) === arahBaik;
      const angka = satuan === 'poin' ? DMS.num1(Math.abs(sel)) : DMS.num(Math.abs(sel));
      return ` (<span class="${baik ? 'rk-baik' : 'rk-buruk'}">${sel > 0 ? 'naik' : 'turun'} ${angka} ${satuan}</span> dibanding ${DMS.esc(p.label)})`;
    };
    out.push(`Availability armada <b>${DMS.pct(a.availability_rate)}</b>${banding(a.availability_rate, b?.availability_rate, 'poin', 1)}. Dari ${DMS.num(a.total_jam)} jam tercatat, ${DMS.num((a.jam.JALAN || 0) + (a.jam.STANDBY || 0))} jam unit dalam kondisi siap pakai.`);
    out.push(`Utilisasi <b>${DMS.pct(a.utilisasi)}</b>${banding(a.utilisasi, b?.utilisasi, 'poin', 1)}. Rata-rata <b>${DMS.unit(a.avg_jalan)} unit Jalan per hari</b> dari ${DMS.num(a.unit)} unit.`);
    const adaData = d.bidang.filter((x) => x.availability_rate !== null);
    if (adaData.length > 1) {
      const urut = [...adaData].sort((x, y) => y.availability_rate - x.availability_rate);
      const t = urut[0], r = urut[urut.length - 1];
      out.push(t.availability_rate === r.availability_rate
        ? `Availability semua bidang sama, <b>${DMS.pct(t.availability_rate)}</b>.`
        : `Availability tertinggi di <b>${DMS.esc(t.nama)}</b> (${DMS.pct(t.availability_rate)}), terendah di <b>${DMS.esc(r.nama)}</b> (${DMS.pct(r.availability_rate)}).`);
    }
    const tanpa = d.bidang.filter((x) => x.availability_rate === null);
    if (tanpa.length) out.push(`<span class="rk-buruk">${tanpa.length} bidang belum ada data</span>: ${tanpa.map((x) => DMS.esc(x.nama)).join(', ')}.`);
    const perb = a.jam.PERBAIKAN || 0;
    if (perb) {
      const t = d.perbaikan_top?.[0];
      out.push(`Jam perbaikan <b>${DMS.num(perb)} jam</b>${banding(perb, b?.jam?.PERBAIKAN ?? null, 'jam', -1)} di ${DMS.num(d.perbaikan_unit)} unit${t ? `; terbanyak ${DMS.linkUnit(t.id, t.nomor)} (${DMS.esc(t.jenis)}) ${DMS.num(t.perbaikan)} jam` : ''}.`);
    } else {
      out.push('Tidak ada jam perbaikan tercatat pada periode ini.');
    }
    if ((a.jam.RUSAK || 0) > 0) out.push(`Jam rusak <b>${DMS.num(a.jam.RUSAK)} jam</b> (${DMS.num1(a.jam.RUSAK / a.total_jam * 100)}% dari total jam).`);
    const jr = d.jenis_terendah?.[0];
    if (jr && d.jenis_jumlah > 1) out.push(`Jenis dengan availability terendah: <b>${DMS.esc(jr.jenis)}</b> (${DMS.pct(jr.availability_rate)}).`);
    return out;
  }

  const MODULES = {
    // ================= BERANDA =================
    beranda() {
      const P = window.PAGE;
      const qs = () => DMS.filterParams($('#filterForm')).toString();
      boot(P.url, (d) => {
        const a = d.availability, p = d.pembanding?.availability, ada = a.unit_hari > 0;
        $('#periodeLbl').textContent = d.filter.label;
        $('#rkPeriode').textContent = d.filter.label;
        $('#rkDiperbarui').textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

        // ---- KPI utama + rincian ----
        $('#kpiAr').textContent = DMS.pct(a.availability_rate);
        $('#kpiUtil').textContent = DMS.pct(a.utilisasi);
        $('#kpiUnit').textContent = DMS.num(a.unit);
        $('#kpiPerbaikan').textContent = DMS.num(a.jam.PERBAIKAN);
        const siap = (a.jam.JALAN || 0) + (a.jam.STANDBY || 0);
        const ujalan = Math.round((a.utilisasi || 0) / 100 * a.unit_hari);
        $('#xAr').innerHTML = ada ? `${DMS.num(siap)} dari ${DMS.num(a.total_jam)} jam siap pakai` : '';
        $('#xUtil').innerHTML = ada ? `${DMS.num(ujalan)} dari ${DMS.num(a.unit_hari)} unit-hari berstatus Jalan` : '';
        $('#xUnit').innerHTML = '';
        $('#xPerbaikan').innerHTML = ada ? (d.perbaikan_unit ? `di ${DMS.num(d.perbaikan_unit)} unit · rata-rata ${DMS.num(Math.round(a.jam.PERBAIKAN / d.perbaikan_unit))} jam/unit` : 'tidak ada unit yang diperbaiki') : '';
        $('#kpiJalan').textContent = ada ? DMS.unit(a.avg_jalan) : '–';
        renderPembanding(a, d.pembanding, { jalan: '#dJalan' });

        renderKelengkapan(d.kelengkapan);
        $('#resetNotice').innerHTML = d.reset_menunggu ? `<a class="reset-notice" href="${P.akunUrl}">
            <i class="fas fa-key" aria-hidden="true"></i><span><b>${d.reset_menunggu} permintaan reset password</b> menunggu ditangani.</span>
            <span class="go">Buka Akun <i class="fas fa-chevron-right" aria-hidden="true"></i></span></a>` : '';

        // ---- Per bidang ----
        const list = $('#bidangList');
        const head = list.querySelector('.head').outerHTML;
        list.innerHTML = head + d.bidang.map((b) => {
          const ar = b.availability_rate;
          return `<a class="bidang-row" href="${P.summaryBase}/${b.kode}?${qs()}" aria-label="Buka dashboard ${DMS.esc(b.nama)}">
            <span class="nama">${DMS.esc(b.nama)}</span>
            <span data-l="Jumlah unit"><span class="v">${DMS.num(b.unit)}</span> <span class="kpi-sub">unit</span></span>
            ${ar === null
              ? '<span class="nodata" data-l="Availability">Belum ada data</span><span class="nodata" data-l="Utilisasi">–</span>'
              : `<span class="meter" data-l="Availability"><span class="v">${DMS.pct(ar)}</span><span class="bar" aria-hidden="true"><span style="width:${ar}%"></span></span></span>
            <span data-l="Utilisasi"><span class="v">${DMS.pct(b.utilisasi)}</span></span>`}
            <span data-l="Jam perbaikan"><span class="v">${DMS.num(b.perbaikan)}</span> <span class="kpi-sub">jam</span></span>
            <span class="go" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
          </a>`;
        }).join('');

        // ---- Grafik ----
        DMS.charts.donut($('#cDonut'), a.jam);
        // Availability sudah ada di kotak angka utama; tengah lingkaran menampilkan total jam.
        $('#donutAr').textContent = DMS.num(a.total_jam);
        $('#donutLegend').innerHTML = `<div class="legend-inline">${legendJam(a.jam)}</div>`;
        const has = d.tren.some((r) => r.unit_hari > 0);
        toggle($('#cTren').parentElement, $('#trenEmpty'), has);
        if (has) DMS.charts.utilitas($('#cTren'), d.tren.map((r) => DMS.periodeLabel(r.periode)), d.tren, { type: 'line', yTitle: 'unit' });

        // ---- Ringkasan dalam kalimat ----
        $('#rkIsi').innerHTML = ringkasanBeranda(d).map((t) => `<li>${t}</li>`).join('');

        // ---- Unit dengan jam perbaikan terbanyak ----
        $('#topSemua').href = `${$('#topSemua').href.split('?')[0]}?${qs()}`;
        $('#jenisSemua').href = `${$('#jenisSemua').href.split('?')[0]}?${qs()}`;
        const top = d.perbaikan_top || [];
        const maxP = top.length ? top[0].perbaikan : 0;
        $('#topPerbaikan').innerHTML = top.length ? `<ol class="rank-list">${top.map((r) => `<li>
            <span class="rk-nama">${DMS.linkUnit(r.id, r.nomor)}<small>${DMS.esc(r.jenis)}</small></span>
            <span class="rk-bar" aria-hidden="true"><span style="width:${maxP ? r.perbaikan / maxP * 100 : 0}%;background:${W().PERBAIKAN}"></span></span>
            <span class="rk-nilai"><b>${DMS.num(r.perbaikan)} jam</b><small>${DMS.num(r.hari_perbaikan)} hari</small></span></li>`).join('')}</ol>`
          : empty(ada ? 'Tidak ada perbaikan.' : undefined);

        // ---- Jenis dengan availability terendah ----
        const jr = d.jenis_terendah || [];
        $('#jenisRendah').innerHTML = jr.length ? `<ol class="rank-list">${jr.map((r) => `<li>
            <span class="rk-nama">${DMS.esc(r.jenis)}<small>${DMS.num(r.unit)} unit · tidak siap ${DMS.num((r.jam.RUSAK || 0) + (r.jam.PERBAIKAN || 0) + (r.jam.PM || 0))} jam</small></span>
            <span class="rk-bar" aria-hidden="true"><span style="width:${r.availability_rate}%"></span></span>
            <span class="rk-nilai"><b>${DMS.pct(r.availability_rate)}</b><small>utilisasi ${DMS.pct(r.utilisasi)}</small></span></li>`).join('')}</ol>`
          : empty();

      });
    },

    // ================= UTILISASI =================
    utilisasi() {
      const st = boot(window.PAGE.url, (d) => {
        const term = cari();
        const rows = d.jenis.filter((r) => cocok(term, r.jenis));
        $('#lbl').textContent = d.filter.label;
        const box = $('#cAvg').parentElement;
        box.style.height = Math.max(240, rows.length * 46 + 60) + 'px';
        toggle(box, $('#avgEmpty'), rows.length > 0);
        if (rows.length) DMS.charts.utilitas($('#cAvg'), rows.map((r) => r.jenis), rows.map((r) => ({ jalan: r.avg_jalan, standby: r.avg_standby, rusak: r.avg_rusak })), { horizontal: true, xTitle: 'unit per hari' });
        $('#tbl tbody').innerHTML = rows.length ? rows.map((r, n) => `<tr><td class="text-end num text-secondary">${n + 1}</td><td>${DMS.esc(r.jenis)}</td><td class="text-end num">${r.unit}</td>
          <td class="text-end num">${DMS.unit(r.avg_jalan)}</td><td class="text-end num">${DMS.unit(r.avg_standby)}</td><td class="text-end num">${DMS.unit(r.avg_rusak)}</td>
          <td class="text-end num fw-bold">${DMS.pct(r.persen_jalan)}</td></tr>`).join('') : `<tr><td colspan="7">${empty(term ? 'Tidak ada jenis yang cocok.' : undefined)}</td></tr>`;
        const tot = rows.reduce((a, r) => ({ amount: a.amount + r.amount, jalan: a.jalan + r.jalan, unit: a.unit + r.unit }), { amount: 0, jalan: 0, unit: 0 });
        $('#tbl tfoot').innerHTML = rows.length ? `<tr class="fw-bold"><td></td><td>Total</td><td class="text-end num">${tot.unit}</td><td colspan="3"></td>
          <td class="text-end num">${DMS.pct(tot.amount ? tot.jalan / tot.amount * 100 : null)}</td></tr>` : '';
      });
      $('#btnExport').addEventListener('click', () => eksporPilih(window.PAGE.url, 'Utilisasi', 'Utilisasi', (d, judul) => {
        const aoa = [[judul], ['Jenis', 'Unit', 'Rata-rata Jalan/hari', 'Rata-rata Standby/hari', 'Rata-rata Rusak/hari', 'Utilisasi (%)']];
        d.jenis.forEach((r) => aoa.push([r.jenis, r.unit, Math.round(r.avg_jalan), Math.round(r.avg_standby), Math.round(r.avg_rusak), r.persen_jalan]));
        const tot = d.jenis.reduce((a, r) => ({ amount: a.amount + r.amount, jalan: a.jalan + r.jalan, unit: a.unit + r.unit }), { amount: 0, jalan: 0, unit: 0 });
        aoa.push(['Total', tot.unit, '', '', '', tot.amount ? Math.round(tot.jalan / tot.amount * 1000) / 10 : null]);
        return aoa;
      }));
    },

    // ================= AVAILABILITY =================
    availability() {
      const st = boot(window.PAGE.url, (d) => {
        const t = d.total;
        $('#lbl').textContent = d.filter.label;
        $('#arTotal').textContent = DMS.pct(t.availability_rate);
        DMS.charts.donut($('#cTotal'), t.jam);
        $('#jamTotal').innerHTML = legendJam(t.jam);
        const grid = $('#grid');
        if (!d.jenis.length) { grid.innerHTML = `<div class="col-12">${empty()}</div>`; grid.dataset.keys = ''; return; }
        const keys = d.jenis.map((j) => j.jenis).join('|');
        if (grid.dataset.keys !== keys) {
          grid.innerHTML = d.jenis.map((j, i) => `<div class="col-sm-6 col-lg-4 col-xxl-3"><div class="card-dms donut-card h-100">
            <div class="card-head"><h2>${DMS.esc(j.jenis)}</h2><span class="hint" data-u="${i}"></span></div>
            <div class="card-body"><div class="chart-box"><canvas data-c="${i}" role="img" aria-label="Komposisi jam ${DMS.esc(j.jenis)}"></canvas>
            <div class="donut-center"><span class="v" data-v="${i}"></span><span class="l">Availability</span></div></div></div></div></div>`).join('');
          grid.dataset.keys = keys;
        }
        d.jenis.forEach((j, i) => {
          DMS.charts.donut(grid.querySelector(`[data-c="${i}"]`), j.jam, { small: true });
          grid.querySelector(`[data-v="${i}"]`).textContent = DMS.pct(j.availability_rate);
          grid.querySelector(`[data-u="${i}"]`).textContent = `${j.unit} unit`;
        });
      });
      $('#btnExport').addEventListener('click', () => eksporPilih(window.PAGE.url, 'Availability', 'Availability', (d, judul) => {
        const aoa = [[judul], ['Jenis', 'Unit', ...STATUS.map((s) => L(s) + ' (jam)'), 'Total jam', 'Availability (%)']];
        d.jenis.forEach((j) => aoa.push([j.jenis, j.unit, ...STATUS.map((s) => j.jam[s]), j.total_jam, j.availability_rate]));
        aoa.push(['Total', d.total.unit, ...STATUS.map((s) => d.total.jam[s]), d.total.total_jam, d.total.availability_rate]);
        return aoa;
      }));
    },

    // ================= PERBAIKAN (MAINTENANCE) =================
    maintenance() {
      const st = boot(window.PAGE.url, (d) => {
        $('#lbl').textContent = d.filter.label;
        const wrap = $('#bidangCharts');
        const term = cari();
        // Hanya bidang yang ada perbaikannya yang diberi grafik; sisanya disebut dalam satu baris.
        const ada = d.bidang.map((b) => ({ ...b, rows: b.data.filter((r) => cocok(term, r.nomor, r.jenis)) })).filter((b) => b.rows.length);
        const tanpa = d.bidang.filter((b) => !ada.some((x) => x.kode === b.kode)).map((b) => DMS.esc(b.nama));
        const keys = ada.map((b) => b.kode).join('|');
        if (wrap.dataset.keys !== keys || !wrap.dataset.siap) {
          wrap.innerHTML = ada.map((b) => `<div class="col-xl-6"><div class="card-dms h-100">
            <div class="card-head"><h2>${DMS.esc(b.nama)}</h2><span class="hint" data-h="${b.kode}"></span></div>
            <div class="card-body"><div class="chart-box" data-b="${b.kode}"><canvas data-c="${b.kode}" role="img" aria-label="Jam perbaikan ${DMS.esc(b.nama)}"></canvas></div></div></div></div>`).join('')
            + `<div class="col-12" id="tanpaPerbaikan"></div>`;
          wrap.dataset.keys = keys;
          wrap.dataset.siap = '1';
        }
        $('#tanpaPerbaikan').innerHTML = !ada.length
          ? `<div class="card-dms">${empty(term ? 'Tidak ada kendaraan yang cocok.' : 'Tidak ada perbaikan pada periode ini.')}</div>`
          : (tanpa.length ? `<div class="catatan-kecil"><i class="fas fa-circle-check" aria-hidden="true"></i> Tanpa perbaikan: ${tanpa.join(', ')}.</div>` : '');
        ada.forEach((b) => {
          wrap.querySelector(`[data-h="${b.kode}"]`).textContent = `${b.rows.length} unit · ${DMS.num(b.rows.reduce((a, r) => a + r.perbaikan, 0))} jam`;
          const box = wrap.querySelector(`[data-b="${b.kode}"]`);
          box.style.height = Math.max(160, b.rows.length * 24 + 50) + 'px';
          barPerbaikan(wrap.querySelector(`[data-c="${b.kode}"]`), b.rows, (r) => `${r.nomor} · ${r.jenis}`);
        });
      });
      $('#btnExport').addEventListener('click', () => eksporPilih(window.PAGE.url, 'Perbaikan', 'Perbaikan', (d, judul) => {
        const aoa = [[judul], ['Bidang', 'Nomor', 'Jenis', 'Jam perbaikan', 'Hari perbaikan', 'Rata-rata jam/hari', 'Jam PM', 'Jam rusak']];
        d.bidang.forEach((b) => b.data.forEach((r) => aoa.push([b.nama, r.nomor, r.jenis, r.perbaikan, r.hari_perbaikan, r.rata_rata, r.pm, r.rusak])));
        return aoa;
      }));
    },

    // ================= LAPORAN (Rekap + Detail) =================
    laporan() {
      let tab = window.PAGE.tab;
      const panes = { rekap: $('#paneRekap'), detail: $('#paneDetail') };
      // tab ikut disimpan di URL bersama filter
      $('#filterForm').insertAdjacentHTML('beforeend', `<input type="hidden" name="tab" value="${tab}">`);
      const st = boot(() => tab === 'detail' ? window.PAGE.urlDetail : window.PAGE.urlRekap, (d) => {
        $('#lbl').textContent = d.filter.label;
        if (d.report) { renderDetail(panes.detail, d.report); return; }
        const term = cari();
        const rows = d.rekap.filter((r) => cocok(term, r.bidang, r.jenis));
        let prev = null;
        $('#tblRekap tbody').innerHTML = rows.length ? rows.map((r, n) => {
          const g = r.bidang !== prev; prev = r.bidang;
          return `<tr class="${g ? 'grp-start' : ''}"><td class="text-end num text-secondary">${n + 1}</td><td class="fw-semibold">${g ? DMS.esc(r.bidang) : ''}</td><td>${DMS.esc(r.jenis)}</td>
            <td class="text-end num">${r.unit}</td>
            ${['jalan', 'standby', 'rusak', 'perbaikan', 'pm'].map((k) => `<td class="text-end num">${DMS.num(r[k])}</td>`).join('')}
            <td class="text-end num fw-bold">${DMS.pct(r.availability_rate)}</td><td class="text-end num fw-bold">${DMS.pct(r.utilisasi)}</td></tr>`;
        }).join('') : `<tr><td colspan="11">${empty(term ? 'Tidak ada yang cocok.' : undefined)}</td></tr>`;
        // Total mengikuti baris yang tampil (hasil cari)
        let t = d.total;
        if (term) {
          const jam = Object.fromEntries(STATUS.map((st) => [st, rows.reduce((a, r) => a + r[st.toLowerCase()], 0)]));
          const tot = STATUS.reduce((a, st) => a + jam[st], 0);
          const uh = rows.reduce((a, r) => a + r.unit_hari, 0);
          t = { jam, unit: rows.reduce((a, r) => a + r.unit, 0),
            availability_rate: tot ? (jam.JALAN + jam.STANDBY) / tot * 100 : null,
            utilisasi: uh ? rows.reduce((a, r) => a + (r.utilisasi || 0) * r.unit_hari, 0) / uh : null };
        }
        $('#tblRekap tfoot').innerHTML = rows.length ? `<tr><td colspan="3">Total</td><td class="text-end num">${t.unit}</td>
          ${STATUS.map((s) => `<td class="text-end num">${DMS.num(t.jam[s])}</td>`).join('')}
          <td class="text-end num">${DMS.pct(t.availability_rate)}</td><td class="text-end num">${DMS.pct(t.utilisasi)}</td></tr>` : '';
      });
      document.querySelectorAll('.seg a').forEach((a) => a.addEventListener('click', (e) => {
        e.preventDefault();
        tab = a.dataset.tab;
        document.querySelectorAll('.seg a').forEach((x) => x.classList.toggle('active', x === a));
        panes.rekap.classList.toggle('d-none', tab !== 'rekap');
        panes.detail.classList.toggle('d-none', tab !== 'detail');
        $('#filterForm').elements.tab.value = tab;
        st.reload();
      }));
      $('#btnExport').addEventListener('click', () => {
        const detail = tab === 'detail';
        eksporPilih(() => detail ? window.PAGE.urlDetail : window.PAGE.urlRekap, detail ? 'Laporan Detail' : 'Laporan Rekap', detail ? 'Detail' : 'Rekap', (d, judul) => {
          if (detail) return aoaDetail(d.report, judul);
          const aoa = [[judul], ['Bidang', 'Jenis', 'Unit', 'Jalan (jam)', 'Standby (jam)', 'Rusak (jam)', 'Perbaikan (jam)', 'PM (jam)', 'Availability (%)', 'Utilisasi (%)']];
          d.rekap.forEach((r) => aoa.push([r.bidang, r.jenis, r.unit, r.jalan, r.standby, r.rusak, r.perbaikan, r.pm, r.availability_rate, r.utilisasi]));
          const t = d.total;
          if (t) aoa.push(['Total', '', t.unit, ...STATUS.map((x) => t.jam[x]), t.availability_rate, t.utilisasi]);
          return aoa;
        });
      });
    },

    // ================= DASHBOARD BIDANG =================
    summary() {
      boot(window.PAGE.url, (d) => {
        const a = d.availability;
        $('#lbl').textContent = d.filter.label;
        $('#kAr').textContent = DMS.pct(a.availability_rate);
        $('#kUtil').textContent = DMS.pct(a.utilisasi);
        $('#kJalan').textContent = DMS.unit(a.avg_jalan);
        $('#kPerb').textContent = DMS.num(a.jam.PERBAIKAN);
        renderPembanding(a, d.pembanding, { ar: '#dkAr', util: '#dkUtil', unit: null, jalan: '#dkJalan', perbaikan: '#dkPerb' });
        const q = new URLSearchParams({ tab: 'detail', bidang: window.PAGE.kode, tahun: d.filter.tahun ?? '', bulan: d.filter.bulan ?? '' });
        $('#linkDetail').href = $('#linkDetail').href.split('?')[0] + '?' + q;

        const ub = d.utilitas_bulanan;
        toggle($('#cBulanan').parentElement, $('#ubEmpty'), ub.length > 0);
        if (ub.length) DMS.charts.utilitas($('#cBulanan'), ub.map((r) => DMS.periodeLabel(r.periode)), ub, { yTitle: 'unit' });

        const tr = d.tren;
        $('#trenTitle').textContent = d.tren_mode === 'hari' ? 'Unit per hari' : 'Tren per bulan';
        const adaTren = tr.some((r) => r.unit_hari > 0);
        toggle($('#cTren').parentElement, $('#trEmpty'), adaTren);
        if (adaTren) DMS.charts.utilitas($('#cTren'), tr.map((r) => DMS.periodeLabel(r.periode)), tr, { type: 'line', yTitle: 'unit' });

        DMS.charts.donut($('#cAvail'), a.jam);
        $('#arCenter').textContent = DMS.num(a.total_jam);
        $('#availLegend').innerHTML = `<div class="legend-inline">${legendJam(a.jam)}</div>`;

        const m = d.maintenance, box = $('#cMaint').parentElement;
        toggle(box, $('#mEmpty'), m.length > 0, 'Tidak ada perbaikan.');
        if (m.length) {
          box.style.height = Math.max(200, m.length * 22 + 50) + 'px';
          barPerbaikan($('#cMaint'), m, (r) => `${r.nomor} · ${r.jenis}`);
        }
      });
    },
  };

  document.addEventListener('DOMContentLoaded', () => { const p = window.PAGE?.module; if (MODULES[p]) MODULES[p](); });
})();
