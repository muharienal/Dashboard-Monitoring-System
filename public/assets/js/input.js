/* Halaman input data harian. Rumus final selalu dihitung ulang di server (App\Support\JamKerja). */
(function () {
  'use strict';
  const C = window.INPUT_CFG;
  const KOL = ['jalan', 'rusak', 'perbaikan', 'pm'];
  const $ = (s) => document.querySelector(s);
  const tbody = $('#grid tbody'), tfoot = $('#grid tfoot'), inpTanggal = $('#tanggal');
  const kartuList = $('#kartuList');
  const LABEL = { jalan: 'Jalan', rusak: 'Rusak', perbaikan: 'Perbaikan', pm: 'PM' };
  let state = { rows: [], kolomEdit: [], tanggal: null, loadedAt: null };
  let saving = false;

  const isDirty = (r) => KOL.some((k) => r[k] !== r.asal[k]);
  const anyDirty = () => state.rows.some(isDirty);
  const hitungStandby = (r) => 24 - (r.jalan + r.rusak + r.perbaikan + r.pm);

  function render() {
    if (!state.rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="empty-state">Belum ada kendaraan aktif. Tambahkan di menu Kendaraan.</td></tr>';
      tfoot.innerHTML = '';
      kartuList.innerHTML = '<div class="empty-state">Belum ada kendaraan aktif. Tambahkan di menu Kendaraan.</div>';
      $('#kartuTotal').textContent = '';
      return;
    }
    const can = (k) => state.kolomEdit.includes(k);
    tbody.innerHTML = state.rows.map((r, i) => {
      // type="text" + inputmode numeric: keyboard angka di HP, tanpa tanda minus/desimal, tanpa berubah saat scroll mouse
      const cell = (k) => `<td class="text-end"><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2" autocomplete="off"
          class="form-control form-control-sm ms-auto" data-i="${i}" data-k="${k}" value="${r[k]}"
          aria-label="${k} ${DMS.esc(r.nomor)}" ${can(k) ? '' : 'readonly tabindex="-1"'}></td>`;
      return `<tr data-i="${i}" class="${r.aktif ? '' : 'inactive'}">
        <td class="text-end text-secondary num no">${i + 1}</td>
        <td>${DMS.esc(r.jenis)}</td>
        <td class="nomor fw-semibold" title="${r.oleh ? 'Diisi ' + DMS.esc(r.oleh) + ', ' + DMS.tanggalPanjang(r.diperbarui) : ''}">${DMS.linkUnit(r.kendaraan_id, r.nomor, 'target="_blank" rel="noopener" tabindex="-1"')}</td>
        ${cell('jalan')}
        <td class="text-end"><input class="form-control form-control-sm ms-auto standby" value="${r.standby}" readonly tabindex="-1" aria-label="standby"></td>
        ${cell('rusak')}${cell('perbaikan')}${cell('pm')}
        <td class="text-end"><input class="form-control form-control-sm ms-auto total" value="${r.total}" readonly tabindex="-1" aria-label="total"></td>
      </tr>`;
    }).join('');
    renderKartu();
    state.rows.forEach((r, i) => paintRow(i));
    renderFooter();
    isiOpsiJenis();
    applySearch();
  }

  // Tampilan kartu (layar HP): satu kartu per unit, tombol − / + untuk jam.
  function renderKartu() {
    const can = (k) => state.kolomEdit.includes(k);
    kartuList.innerHTML = state.rows.map((r, i) => {
      const f = (k) => can(k)
        ? `<div class="kt-f"><span class="kt-l">${LABEL[k]}</span><div class="stepper">
            <button type="button" data-aksi="-1" data-i="${i}" data-k="${k}" aria-label="Kurangi ${LABEL[k]} ${DMS.esc(r.nomor)}">−</button>
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2" autocomplete="off" data-i="${i}" data-k="${k}" value="${r[k]}" aria-label="${LABEL[k]} ${DMS.esc(r.nomor)}">
            <button type="button" data-aksi="1" data-i="${i}" data-k="${k}" aria-label="Tambah ${LABEL[k]} ${DMS.esc(r.nomor)}">+</button></div></div>`
        : `<div class="kt-f terkunci"><span class="kt-l">${LABEL[k]} <i class="fas fa-lock" title="Bukan bagian Anda"></i></span><span class="kt-v" data-v="${k}">${r[k]}</span></div>`;
      return `<article class="kartu ${r.aktif ? '' : 'inactive'}" data-i="${i}">
          <header><span class="no">${i + 1}</span><span class="kt-nomor">${DMS.linkUnit(r.kendaraan_id, r.nomor, 'target="_blank" rel="noopener"')}</span><span class="kt-jenis">${DMS.esc(r.jenis)}</span></header>
          <div class="kt-isian">${['jalan', 'rusak', 'perbaikan', 'pm'].map(f).join('')}</div>
          <footer><span>Standby <b class="kt-standby">${r.standby}</b> jam</span><span class="kt-status"></span></footer>
        </article>`;
    }).join('');
  }

  function renderFooter() {
    const s = { jalan: 0, standby: 0, rusak: 0, perbaikan: 0, pm: 0, total: 0 };
    state.rows.forEach((r) => { s.jalan += r.jalan; s.standby += r.standby; s.rusak += r.rusak; s.perbaikan += r.perbaikan; s.pm += r.pm; s.total += r.total; });
    const ar = s.total ? (s.jalan + s.standby) / s.total * 100 : null;
    tfoot.innerHTML = `<tr><td></td><td colspan="2">Total ${state.rows.length} unit · Availability ${DMS.pct(ar)}</td>
      ${['jalan', 'standby', 'rusak', 'perbaikan', 'pm', 'total'].map((k) => `<td class="text-end num">${DMS.num(s[k])}</td>`).join('')}</tr>`;
    const ubah = state.rows.filter(isDirty).length;
    $('#kartuTotal').innerHTML = `${state.rows.length} unit · Availability <b>${DMS.pct(ar)}</b>${ubah ? ` · <span class="text-warning-emphasis">${ubah} belum disimpan</span>` : ''}`;
  }

  function paintRow(i) {
    const r = state.rows[i];
    const tr = tbody.querySelector(`tr[data-i="${i}"]`);
    if (!tr) return;
    tr.classList.toggle('dirty', isDirty(r));
    tr.classList.toggle('err', !!r.error);
    tr.classList.toggle('conflict', !!r.conflict);
    tr.querySelector('.standby').value = r.standby;
    tr.querySelector('.total').value = r.total;
    tr.title = r.error || (r.conflict ? 'Diubah orang lain: ' + r.conflict : '');
    const aktifEl = document.activeElement;
    tr.querySelectorAll('input[data-k]').forEach((el) => { if (el !== aktifEl) el.value = r[el.dataset.k]; });
    const kt = kartuList.querySelector(`.kartu[data-i="${i}"]`);
    if (kt) {
      kt.classList.toggle('dirty', isDirty(r));
      kt.classList.toggle('err', !!r.error);
      kt.classList.toggle('conflict', !!r.conflict);
      kt.querySelectorAll('input[data-k]').forEach((el) => { if (el !== aktifEl) el.value = r[el.dataset.k]; });
      kt.querySelectorAll('[data-v]').forEach((el) => { el.textContent = r[el.dataset.v]; });
      kt.querySelector('.kt-standby').textContent = r.standby;
      kt.querySelector('.kt-status').textContent = r.error ? r.error : (r.conflict ? 'Diubah orang lain' : (isDirty(r) ? 'Belum disimpan' : (r.tersimpan ? 'Tersimpan' : (r.otomatis ? 'Standby otomatis' : 'Belum diisi'))));
    }
  }

  function fromServer(data, keepEdits = false) {
    const prev = new Map(state.rows.map((r) => [r.kendaraan_id, r]));
    state.kolomEdit = data.kolom_edit;
    state.tanggal = data.tanggal;
    state.rows = data.rows.map((x) => {
      const r = { ...x, total: x.total_jam_kerja, asal: { jalan: x.jalan, rusak: x.rusak, perbaikan: x.perbaikan, pm: x.pm } };
      const p = prev.get(x.kendaraan_id);
      if (keepEdits && p && isDirty(p)) {
        state.kolomEdit.forEach((k) => {
          // Pertahankan isian user, kecuali kolom yang juga diubah orang lain (konflik -> pakai data server)
          if (p[k] !== p.asal[k] && p.asal[k] === x[k]) r[k] = p[k];
          else if (p[k] !== p.asal[k] && p.asal[k] !== x[k]) r.conflict = k + ' diganti data terbaru';
        });
        r.standby = hitungStandby(r);
        r.total = 24;
      }
      return r;
    });
    const n = data.rows.length, saved = data.jumlah_tersimpan;
    $('#infoSaved').textContent = `${saved} dari ${n} unit tersimpan`;
    setLast(data.terakhir_bidang);
    document.querySelectorAll('#grid th[data-k]').forEach((th) => {
      th.querySelector('.fa-lock')?.remove();
      if (!data.kolom_edit.includes(th.dataset.k)) th.insertAdjacentHTML('beforeend', '<i class="fas fa-lock" title="Bukan bagian Anda"></i>');
    });
    if (data.version && window.__live) window.__live.setVersion(data.version);
    state.snapshot = JSON.stringify(data.rows.map((r) => [r.kendaraan_id, r.jalan, r.rusak, r.perbaikan, r.pm]));
    render();
  }

  async function load(keepEdits = false) {
    const tgl = inpTanggal.value;
    if (!tgl) return;
    try {
      const data = await DMS.fetchJson(`${C.dataUrl}?tanggal=${encodeURIComponent(tgl)}`);
      fromServer(data, keepEdits);
      $('#conflictBanner').classList.add('d-none');
      if (!keepEdits) cekDraft();
    } catch (e) {
      if (e.message === 'unauthenticated') return;
      const msg = e.data?.errors?.tanggal?.[0] || e.message;
      if (!state.rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="empty-state">${DMS.esc(msg)}<br><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnRetry"><i class="fas fa-rotate"></i> Coba lagi</button></td></tr>`;
        $('#btnRetry').addEventListener('click', () => load());
        $('#infoSaved').textContent = '–';
      } else {
        DMS.toast(DMS.esc(msg), 'danger');
      }
    }
  }

  function setLast(t) {
    $('#infoLast').textContent = t ? `${DMS.tanggalPanjang(t.waktu)}${t.oleh ? ' (' + t.oleh + ')' : ''}` : '–';
  }

  // ---- Input ----
  // Satu pintu untuk mengubah nilai: hanya angka bulat 0..sisa jam (24 - kolom lain).
  function setNilai(i, k, raw) {
    const r = state.rows[i];
    let v = parseInt(String(raw).replace(/\D/g, ''), 10);
    if (!Number.isFinite(v)) v = 0;
    const maks = 24 - KOL.filter((x) => x !== k).reduce((a, x) => a + r[x], 0);
    const dipotong = v > maks;
    if (dipotong) v = maks;
    r[k] = v;
    r.standby = hitungStandby(r);
    r.total = 24;
    r.error = null;
    paintRow(i);
    simpanDraft();
    return { v, dipotong, maks };
  }

  // ---- Kartu (HP): ketik langsung atau tombol − / + (tahan untuk mengulang) ----
  function langkah(i, k, d) {
    const r = state.rows[i];
    const res = setNilai(i, k, Math.max(0, r[k] + d));
    // Tombol bukan sedang mengetik: tulis angkanya walau kotak itu masih fokus.
    const el = kartuList.querySelector(`.kartu[data-i="${i}"] input[data-k="${k}"]`);
    if (el) el.value = r[k];
    if (d > 0 && res.dipotong) warnMax(r, k, res.maks);
    renderFooter();
  }
  let ulang = null, tunda = null;
  const henti = () => { clearTimeout(tunda); clearInterval(ulang); };
  kartuList.addEventListener('pointerdown', (e) => {
    const b = e.target.closest('button[data-aksi]');
    if (!b) return;
    e.preventDefault();
    const go = () => langkah(+b.dataset.i, b.dataset.k, +b.dataset.aksi);
    go();
    tunda = setTimeout(() => { ulang = setInterval(go, 110); }, 400);
  });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) => kartuList.addEventListener(ev, henti));
  kartuList.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-aksi]');
    if (b && e.detail === 0) langkah(+b.dataset.i, b.dataset.k, +b.dataset.aksi); // dari keyboard
  });
  kartuList.addEventListener('focusin', (e) => { if (e.target.matches('input[data-k]')) requestAnimationFrame(() => e.target.select()); });
  kartuList.addEventListener('keydown', (e) => {
    if (!e.target.matches('input[data-k]') || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key.length === 1 && !/[0-9]/.test(e.key)) e.preventDefault();
  });
  kartuList.addEventListener('input', (e) => {
    const el = e.target;
    if (!el.matches('input[data-k]')) return;
    const bersih = el.value.replace(/\D/g, '').slice(0, 2);
    if (bersih !== el.value) el.value = bersih;
    const i = +el.dataset.i, res = setNilai(i, el.dataset.k, bersih);
    if (bersih !== '' && String(res.v) !== bersih) { el.value = res.v; if (res.dipotong) warnMax(state.rows[i], el.dataset.k, res.maks); }
    renderFooter();
  });
  kartuList.addEventListener('focusout', (e) => { if (e.target.matches('input[data-k]') && e.target.value === '') e.target.value = '0'; });

  // ---- Simpan sementara di perangkat (bila sinyal hilang / sesi habis / tab tertutup) ----
  const kunciDraft = (tgl = state.tanggal) => `dms-draft:${C.userId}:${C.kode}:${tgl}`;
  let timerDraft = null, draftTunda = null;
  function simpanDraft() {
    clearTimeout(timerDraft);
    const tgl = state.tanggal;
    timerDraft = setTimeout(() => {
      try {
        const d = state.rows.filter(isDirty).map((r) => ({ id: r.kendaraan_id, jalan: r.jalan, rusak: r.rusak, perbaikan: r.perbaikan, pm: r.pm }));
        if (d.length) localStorage.setItem(kunciDraft(tgl), JSON.stringify({ waktu: Date.now(), rows: d }));
        else localStorage.removeItem(kunciDraft(tgl));
      } catch (_) { /* penyimpanan tidak tersedia */ }
    }, 300);
  }
  function hapusDraft(tgl = state.tanggal) {
    clearTimeout(timerDraft);
    try { localStorage.removeItem(kunciDraft(tgl)); } catch (_) { /* abaikan */ }
    draftTunda = null;
    $('#draftBanner').classList.add('d-none');
  }
  function cekDraft() {
    let d = null;
    try { d = JSON.parse(localStorage.getItem(kunciDraft()) || 'null'); } catch (_) { d = null; }
    const ada = d && Array.isArray(d.rows) && d.rows.some((x) => state.rows.some((r) => r.kendaraan_id === x.id));
    draftTunda = ada ? d : null;
    $('#draftBanner').classList.toggle('d-none', !ada);
    if (ada) {
      const w = new Date(d.waktu);
      $('#draftText').textContent = `Ada isian ${d.rows.length} unit untuk tanggal ini yang belum terkirim (tersimpan di perangkat ini, ${w.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })} ${w.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}).`;
    }
  }
  $('#btnDraftPulih').addEventListener('click', () => {
    if (!draftTunda) return;
    let n = 0;
    draftTunda.rows.forEach((x) => {
      const i = state.rows.findIndex((r) => r.kendaraan_id === x.id);
      if (i < 0) return;
      state.kolomEdit.forEach((k) => { if (x[k] !== undefined) setNilai(i, k, x[k]); });
      n++;
    });
    renderFooter();
    $('#draftBanner').classList.add('d-none');
    DMS.toast(`Isian ${n} unit dipulihkan. Periksa lalu Simpan.`, 'success', 4000, { title: 'Dipulihkan' });
  });
  $('#btnDraftBuang').addEventListener('click', async () => {
    const ok = await DMS.confirm({ title: 'Buang isian sementara?', tone: 'danger', ok: 'Buang', text: 'Isian yang belum terkirim di perangkat ini akan dihapus.' });
    if (ok) { hapusDraft(); DMS.toast('Isian sementara dibuang.', 'info', 2500); }
  });
  const tandaOnline = () => $('#offlineBanner').classList.toggle('d-none', navigator.onLine);
  window.addEventListener('offline', tandaOnline);
  window.addEventListener('online', () => { tandaOnline(); if (anyDirty()) DMS.toast('Sinyal kembali. Tekan Simpan untuk mengirim isian.', 'info', 5000, { title: 'Online' }); });
  tandaOnline();

  const editCols = () => ['jalan', 'rusak', 'perbaikan', 'pm'].filter((k) => state.kolomEdit.includes(k));
  const visibleRows = () => [...tbody.querySelectorAll('tr[data-i]:not(.hidden-row)')];
  const cellAt = (tr, k) => tr?.querySelector(`input[data-k="${k}"]`);
  const focusCell = (el) => { if (el) { el.focus(); el.select(); } };

  function flash(el) {
    el.classList.remove('flash');
    void el.offsetWidth;
    el.classList.add('flash');
  }
  let lastWarn = 0;
  function warnMax(r, k, maks) {
    if (Date.now() - lastWarn < 1500) return;
    lastWarn = Date.now();
    DMS.toast(`${DMS.esc(r.nomor)}: ${k} maksimal ${maks} jam (total 24 jam).`, 'warning', 2500);
  }

  tbody.addEventListener('focusin', (e) => {
    const el = e.target;
    if (!el.dataset.k || el.readOnly) return;
    el.dataset.awal = el.value;
    requestAnimationFrame(() => el.select()); // langsung ketik untuk mengganti
  });

  // Tolak tombol selain angka (minus, titik, koma, huruf, e)
  tbody.addEventListener('keydown', (e) => {
    const el = e.target;
    if (!el.dataset.k || el.readOnly) return;
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key.length === 1 && !/[0-9]/.test(e.key)) { e.preventDefault(); return; }

    const tr = el.closest('tr');
    const rows = visibleRows();
    const idx = rows.indexOf(tr);
    const cols = editCols();
    const ci = cols.indexOf(el.dataset.k);
    const diAwal = el.selectionStart === 0 && el.selectionEnd === 0;
    const diAkhir = el.selectionStart === el.value.length;
    const semua = el.selectionStart === 0 && el.selectionEnd === el.value.length;

    if (e.key === 'Enter' || e.key === 'ArrowDown') {
      e.preventDefault();
      focusCell(cellAt(rows[idx + ((e.key === 'Enter' && e.shiftKey) ? -1 : 1)], el.dataset.k));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      focusCell(cellAt(rows[idx - 1], el.dataset.k));
    } else if (e.key === 'ArrowRight' && (diAkhir || semua) && ci < cols.length - 1) {
      e.preventDefault();
      focusCell(cellAt(tr, cols[ci + 1]));
    } else if (e.key === 'ArrowLeft' && (diAwal || semua) && ci > 0) {
      e.preventDefault();
      focusCell(cellAt(tr, cols[ci - 1]));
    } else if (e.key === 'Escape' && el.dataset.awal !== undefined) {
      e.preventDefault();
      el.value = el.dataset.awal;
      setNilai(+el.dataset.i, el.dataset.k, el.value);
      renderFooter();
      el.select();
    }
  });

  tbody.addEventListener('input', (e) => {
    const el = e.target, k = el.dataset.k;
    if (!k || el.readOnly) return;
    const bersih = el.value.replace(/\D/g, '').slice(0, 2);
    if (bersih !== el.value) el.value = bersih;
    const i = +el.dataset.i;
    const res = setNilai(i, k, bersih);
    if (bersih !== '' && String(res.v) !== bersih) {
      el.value = res.v;
      flash(el);
      if (res.dipotong) warnMax(state.rows[i], k, res.maks);
    }
    renderFooter();
  });

  tbody.addEventListener('focusout', (e) => {
    const el = e.target;
    if (el.dataset.k && !el.readOnly && el.value === '') el.value = '0';
  });

  // Tempel dari Excel: beberapa baris/kolom sekaligus, mulai dari sel yang dipilih
  tbody.addEventListener('paste', (e) => {
    const el = e.target;
    if (!el.dataset.k || el.readOnly) return;
    const text = (e.clipboardData || window.clipboardData).getData('text');
    const lines = text.replace(/\r/g, '').split('\n');
    while (lines.length && lines[lines.length - 1].trim() === '') lines.pop();
    if (lines.length <= 1 && !text.includes('\t')) return; // satu nilai: biarkan, dibersihkan oleh event input
    e.preventDefault();
    const rows = visibleRows(), cols = editCols();
    const r0 = rows.indexOf(el.closest('tr')), c0 = cols.indexOf(el.dataset.k);
    let n = 0, potong = 0;
    lines.forEach((line, dr) => {
      const tr = rows[r0 + dr];
      if (!tr) return;
      line.split('\t').forEach((val, dc) => {
        const k = cols[c0 + dc];
        const input = k && cellAt(tr, k);
        if (!input) return;
        const res = setNilai(+tr.dataset.i, k, val);
        input.value = res.v;
        if (res.dipotong) { potong++; flash(input); }
        n++;
      });
    });
    renderFooter();
    DMS.toast(`${n} sel ditempel${potong ? `, ${potong} dikurangi agar total 24 jam` : ''}. Periksa lalu Simpan.`, potong ? 'warning' : 'info', 3500);
  });


  // ---- Cari & filter (nomor selalu urut 1, 2, 3 ... sesuai baris yang tampil) ----
  const FILTER = {
    belum: (r) => !r.tersimpan,
    diubah: (r) => isDirty(r),
    jalan: (r) => r.jalan > 0,
    rusak: (r) => r.rusak + r.perbaikan + r.pm > 0,
    standby: (r) => r.standby === 24,
  };
  function isiOpsiJenis() {
    const sel = $('#fJenis'), pilih = sel.value;
    const daftar = [...new Set(state.rows.map((r) => r.jenis))].sort();
    sel.innerHTML = '<option value="">Semua</option>' + daftar.map((j) => `<option>${DMS.esc(j)}</option>`).join('');
    sel.value = daftar.includes(pilih) ? pilih : '';
  }
  function applySearch() {
    const term = $('#search').value.trim().toLowerCase();
    const jenis = $('#fJenis').value;
    const tampil = FILTER[$('#fTampil').value];
    let n = 0;
    tbody.querySelectorAll('tr[data-i]').forEach((tr) => {
      const r = state.rows[+tr.dataset.i];
      const ok = (!term || r.nomor.toLowerCase().includes(term) || r.jenis.toLowerCase().includes(term))
        && (!jenis || r.jenis === jenis)
        && (!tampil || tampil(r));
      tr.classList.toggle('hidden-row', !ok);
      if (ok) tr.querySelector('.no').textContent = ++n;
      const kt = kartuList.querySelector(`.kartu[data-i="${tr.dataset.i}"]`);
      if (kt) { kt.classList.toggle('hidden-row', !ok); if (ok) kt.querySelector('.no').textContent = n; }
    });
    const total = state.rows.length;
    // Jumlah total sudah ada di bilah bawah; di sini hanya saat filter memperkecil daftar.
    $('#infoTampil').textContent = n === total ? '' : `Menampilkan ${n} dari ${total} unit`;
    let kosong = tbody.querySelector('tr.no-match');
    if (!n && total) {
      if (!kosong) tbody.insertAdjacentHTML('beforeend', '<tr class="no-match"><td colspan="9" class="empty-state">Tidak ada kendaraan yang cocok dengan filter.</td></tr>');
    } else kosong?.remove();
  }
  $('#search').addEventListener('input', applySearch);
  $('#fJenis').addEventListener('change', applySearch);
  $('#fTampil').addEventListener('change', applySearch);

  // ---- Tanggal ----
  const geser = (n) => {
    const d = new Date(inpTanggal.value + 'T00:00:00');
    d.setDate(d.getDate() + n);
    const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    if (iso > inpTanggal.max) return;
    gantiTanggal(iso);
  };
  // Konfirmasi standar bila ada isian yang belum disimpan.
  const relaBuang = async (aksi) => !anyDirty() || DMS.confirm({
    title: 'Perubahan belum disimpan', tone: 'warning', ok: aksi, cancel: 'Kembali',
    text: `Isian ${state.rows.filter(isDirty).length} unit akan hilang bila dilanjutkan. Simpan dulu bila masih diperlukan.`,
  });
  async function gantiTanggal(iso) {
    if (iso === state.tanggal) return;
    const adaUbah = anyDirty();
    if (!(await relaBuang('Ganti tanggal'))) { inpTanggal.value = state.tanggal; return; }
    if (adaUbah) hapusDraft();
    inpTanggal.value = iso;
    state.rows = [];
    load();
  }
  inpTanggal.addEventListener('change', () => gantiTanggal(inpTanggal.value));
  $('#prevDay').addEventListener('click', () => geser(-1));
  $('#nextDay').addEventListener('click', () => geser(1));
  $('#today').addEventListener('click', () => gantiTanggal(inpTanggal.max));
  $('#btnReload').addEventListener('click', async () => {
    const adaUbah = anyDirty();
    if (!(await relaBuang('Muat ulang'))) return;
    if (adaUbah) hapusDraft();
    state.rows = [];
    await load();
    DMS.toast('Data dimuat ulang dari server.', 'info', 2500);
  });
  $('#btnMerge').addEventListener('click', async () => {
    await load(true);
    DMS.toast('Data terbaru sudah dimuat. Isian Anda tetap ada, cek baris oranye lalu Simpan.', 'info', 4500);
  });

  // ---- Simpan ----
  async function simpan() {
    if (saving) return;
    const kirim = state.rows.filter((r) => isDirty(r) || !r.tersimpan).map((r) => {
      const o = { kendaraan_id: r.kendaraan_id, asal: {} };
      state.kolomEdit.forEach((k) => { o[k] = r[k]; o.asal[k] = r.asal[k]; });
      return o;
    });
    if (!kirim.length) { DMS.toast('Tidak ada perubahan untuk disimpan.', 'info', 2500, { title: 'Sudah tersimpan' }); return; }
    saving = true;
    const ok = await DMS.confirm({
      title: 'Simpan data?', ok: 'Simpan', icon: 'fa-floppy-disk',
      text: `${kirim.length} unit ${C.bidang} untuk ${DMS.tanggalPanjang(state.tanggal)} akan disimpan.`,
    });
    if (!ok) { saving = false; return; }
    const btn = $('#btnSave');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan…';
    try {
      const res = await DMS.fetchJson(C.saveUrl, { method: 'POST', body: JSON.stringify({ tanggal: state.tanggal, rows: kirim }) });
      hapusDraft();
      fromServer(res.grid);
      DMS.toast(`${res.dibuat + res.disimpan} unit tersimpan untuk ${DMS.esc(DMS.tanggalPanjang(state.tanggal))}.`, 'success', 3500, { title: 'Data tersimpan' });
    } catch (e) {
      if (e.status === 422 && e.data?.errors) {
        const errs = Array.isArray(e.data.errors) ? e.data.errors : Object.values(e.data.errors).flat().map((m) => ({ pesan: m }));
        errs.forEach((x) => { const r = state.rows.find((r) => r.kendaraan_id === x.kendaraan_id); if (r) r.error = x.pesan; });
        state.rows.forEach((r, i) => paintRow(i));
        DMS.toast(`Perbaiki baris merah:<ul>${errs.slice(0, 5).map((x) => `<li>${DMS.esc((x.nomor ? x.nomor + ': ' : '') + x.pesan)}</li>`).join('')}</ul>`, 'danger', 9000, { title: 'Belum tersimpan' });
      } else if (e.status === 409) {
        const list = e.data.konflik.map((x) => `${x.nomor} (${x.kolom.join(', ')}${x.oleh ? ' oleh ' + x.oleh : ''})`);
        $('#conflictText').textContent = 'Belum tersimpan. Sudah diubah orang lain: ' + list.join('; ') + '. Klik tombol di kanan, cek baris oranye, lalu Simpan lagi.';
        $('#conflictBanner').classList.remove('d-none');
        DMS.toast('Sebagian data sudah diubah orang lain. Lihat keterangan di atas tabel.', 'warning', 6000, { title: 'Belum tersimpan' });
      } else if (!e.status && e.message !== 'unauthenticated') {
        // Tidak sampai ke server (sinyal hilang): isian tetap aman di perangkat ini.
        simpanDraft();
        $('#offlineBanner').classList.remove('d-none');
        DMS.toast('Tidak ada sinyal. Isian disimpan sementara di perangkat ini; tekan Simpan lagi saat sinyal kembali.', 'warning', 8000, { title: 'Belum terkirim' });
      } else if (e.message !== 'unauthenticated') {
        DMS.toast(DMS.esc(e.message), 'danger', 8000, { title: 'Gagal menyimpan' });
      }
    } finally {
      saving = false;
      btn.disabled = !state.kolomEdit.length;
      btn.innerHTML = '<i class="fas fa-floppy-disk"></i> Simpan';
    }
  }
  $('#btnSave').addEventListener('click', simpan);
  document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); simpan(); } });
  window.addEventListener('beforeunload', (e) => { if (anyDirty() && !window.__dmsKeluar) { e.preventDefault(); e.returnValue = ''; } });

  // ---- Import dari Excel ----
  // Dibaca di browser (SheetJS), diperiksa, lalu diisikan ke tabel; penyimpanan tetap lewat tombol Simpan.
  const rapat = (v) => String(v ?? '').toUpperCase().replace(/\s+/g, '');
  let impSiap = [];
  const modalImport = $('#modalImport');
  if (modalImport) {
    modalImport.addEventListener('show.bs.modal', () => {
      $('#impTanggal').textContent = DMS.tanggalPanjang(state.tanggal);
      $('#impKolom').innerHTML = state.kolomEdit.map((k) => `<b>${LABEL[k]}</b>`).join(', ');
      $('#impFile').value = ''; $('#impHasil').innerHTML = ''; $('#btnTerapkan').disabled = true; impSiap = [];
    });
    $('#btnTemplate').addEventListener('click', () => {
      const kol = state.kolomEdit.map((k) => LABEL[k]);
      const aoa = [['Tanggal', 'Nomor', 'Jenis', ...kol]];
      state.rows.forEach((r) => aoa.push([state.tanggal, r.nomor, r.jenis, ...state.kolomEdit.map((k) => r[k])]));
      DMS.exportXlsx(`Template input ${C.bidang} ${state.tanggal}`, 'Input', aoa);
    });
    $('#impFile').addEventListener('change', async (e) => {
      const file = e.target.files[0];
      impSiap = []; $('#btnTerapkan').disabled = true;
      if (!file) return;
      if (!window.XLSX) { DMS.toast('Fitur Excel belum siap, coba lagi sebentar.', 'warning'); return; }
      let baris;
      try {
        const wb = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: true });
        baris = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, raw: true, defval: '' });
      } catch (err) {
        $('#impHasil').innerHTML = '<div class="alert alert-danger py-2 mb-0">File tidak bisa dibaca. Pastikan formatnya .xlsx, .xls, atau .csv.</div>';
        return;
      }
      // Cari baris judul yang memuat kolom "Nomor"
      const hi = baris.findIndex((r) => r.some((c) => rapat(c) === 'NOMOR'));
      if (hi < 0) { $('#impHasil').innerHTML = '<div class="alert alert-danger py-2 mb-0">Kolom <b>Nomor</b> tidak ditemukan. Pakai template agar susunannya sesuai.</div>'; return; }
      const head = baris[hi].map(rapat);
      const col = (nama) => head.indexOf(nama);
      const iNomor = col('NOMOR'), iTgl = col('TANGGAL');
      const peta = Object.fromEntries(KOL.map((k) => [k, col(rapat(LABEL[k]))]));
      const diabaikan = KOL.filter((k) => peta[k] >= 0 && !state.kolomEdit.includes(k)).map((k) => LABEL[k]);
      const tglIso = (v) => {
        if (v instanceof Date) return `${v.getFullYear()}-${String(v.getMonth() + 1).padStart(2, '0')}-${String(v.getDate()).padStart(2, '0')}`;
        const t = String(v).trim(); let m;
        if ((m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/))) return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`;
        if ((m = t.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/))) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
        return t;
      };
      const indeks = new Map(state.rows.map((r, i) => [rapat(r.nomor), i]));
      const salah = [], dilihat = new Set();
      baris.slice(hi + 1).forEach((r, n) => {
        const nomor = String(r[iNomor] ?? '').trim();
        if (!nomor) return;
        const no = hi + n + 2; // nomor baris di Excel
        const i = indeks.get(rapat(nomor));
        if (i === undefined) { salah.push([no, nomor, 'nomor tidak dikenal di bidang ini']); return; }
        if (dilihat.has(i)) { salah.push([no, nomor, 'nomor muncul lebih dari sekali']); return; }
        dilihat.add(i);
        if (iTgl >= 0 && String(r[iTgl]).trim() !== '' && tglIso(r[iTgl]) !== state.tanggal) { salah.push([no, nomor, `tanggal ${tglIso(r[iTgl])} berbeda dengan ${state.tanggal}`]); return; }
        const nilai = {}; let pesan = null;
        state.kolomEdit.forEach((k) => {
          if (pesan || peta[k] < 0) return;
          const v = r[peta[k]];
          if (v === '' || v === null) { nilai[k] = 0; return; }
          const x = Number(String(v).replace(',', '.'));
          if (!Number.isInteger(x) || x < 0 || x > 24) pesan = `${LABEL[k]} "${v}" harus angka bulat 0–24`;
          else nilai[k] = x;
        });
        if (pesan) { salah.push([no, nomor, pesan]); return; }
        const row = state.rows[i];
        const total = KOL.reduce((a, k) => a + (k in nilai ? nilai[k] : row[k]), 0);
        if (total > 24) { salah.push([no, nomor, `total ${total} jam, lebih dari 24`]); return; }
        impSiap.push({ i, nilai });
      });
      const ubah = impSiap.filter((x) => Object.entries(x.nilai).some(([k, v]) => state.rows[x.i][k] !== v)).length;
      $('#impHasil').innerHTML = `
        <div class="imp-ringkas"><span class="ok"><b>${impSiap.length}</b> baris siap (${ubah} berubah)</span>${salah.length ? `<span class="no"><b>${salah.length}</b> baris dilewati</span>` : ''}</div>
        ${diabaikan.length ? `<div class="small text-secondary mt-2">Kolom ${diabaikan.join(', ')} diabaikan karena bukan bagian Anda.</div>` : ''}
        ${salah.length ? `<div class="table-wrap mt-2" style="max-height:220px"><table class="table table-sm mb-0"><thead><tr><th>Baris</th><th>Nomor</th><th>Masalah</th></tr></thead><tbody>
          ${salah.slice(0, 50).map(([b, n, m]) => `<tr><td>${b}</td><td>${DMS.esc(n)}</td><td>${DMS.esc(m)}</td></tr>`).join('')}</tbody></table></div>
          ${salah.length > 50 ? `<div class="small text-secondary mt-1">… dan ${salah.length - 50} lainnya.</div>` : ''}` : ''}`;
      $('#btnTerapkan').disabled = !impSiap.length;
      $('#btnTerapkan').textContent = impSiap.length ? `Terapkan ${impSiap.length} baris` : 'Terapkan';
    });
    $('#btnTerapkan').addEventListener('click', () => {
      impSiap.forEach(({ i, nilai }) => {
        // Turunkan dulu semua kolom, baru isi, agar batas 24 jam per baris tidak memotong urutan isian.
        Object.keys(nilai).forEach((k) => setNilai(i, k, 0));
        Object.entries(nilai).forEach(([k, v]) => setNilai(i, k, v));
      });
      renderFooter();
      bootstrap.Modal.getInstance(modalImport)?.hide();
      DMS.toast(`${impSiap.length} unit diisi dari Excel. Baris kuning = belum disimpan; periksa lalu tekan Simpan.`, 'success', 6000, { title: 'Import selesai' });
      impSiap = [];
    });
  }

  // ---- Ekspor Excel: rentang tanggal (data yang sudah disimpan) ----
  const modalEkspor = $('#modalEkspor');
  const ekDari = $('#ekDari'), ekSampai = $('#ekSampai');
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const hariIni = () => iso(new Date());
  const jumlahHari = () => Math.round((new Date(ekSampai.value) - new Date(ekDari.value)) / 86400000) + 1;
  const infoEkspor = () => {
    const n = jumlahHari();
    const salah = !ekDari.value || !ekSampai.value ? 'Isi kedua tanggal.' : (n < 1 ? 'Tanggal akhir tidak boleh sebelum tanggal awal.' : (n > 366 ? 'Rentang maksimal 366 hari.' : ''));
    $('#ekInfo').textContent = salah || `${n} hari`;
    $('#ekInfo').classList.toggle('text-danger', !!salah);
    $('#ekOk').disabled = !!salah;
  };
  [ekDari, ekSampai].forEach((el) => el.addEventListener('input', infoEkspor));
  modalEkspor.querySelectorAll('[data-ek]').forEach((b) => b.addEventListener('click', () => {
    const t = new Date(), k = b.dataset.ek;
    if (k === 'ini') { ekDari.value = ekSampai.value = state.tanggal; }
    if (k === '7') { const a = new Date(t); a.setDate(a.getDate() - 6); ekDari.value = iso(a); ekSampai.value = iso(t); }
    if (k === 'bulan') { ekDari.value = iso(new Date(t.getFullYear(), t.getMonth(), 1)); ekSampai.value = iso(t); }
    if (k === 'lalu') { ekDari.value = iso(new Date(t.getFullYear(), t.getMonth() - 1, 1)); ekSampai.value = iso(new Date(t.getFullYear(), t.getMonth(), 0)); }
    infoEkspor();
  }));
  $('#btnExport').addEventListener('click', () => {
    ekDari.value = ekSampai.value = state.tanggal;
    $('#ekBelum').classList.toggle('d-none', !anyDirty());
    infoEkspor();
    bootstrap.Modal.getOrCreateInstance(modalEkspor).show();
  });
  $('#formEkspor').addEventListener('submit', async (e) => {
    e.preventDefault();
    if ($('#ekOk').disabled) return;
    const tombol = $('#ekOk'); tombol.disabled = true;
    try {
      const d = await DMS.fetchJson(`${C.eksporUrl}?${new URLSearchParams({ dari: ekDari.value, sampai: ekSampai.value })}`);
      if (!d.rows.length) { DMS.toast('Tidak ada kendaraan pada rentang tanggal ini.', 'warning', 5000, { title: 'Tidak ada data' }); return; }
      const label = d.dari === d.sampai ? DMS.tanggalPanjang(d.dari) : `${DMS.tanggalPanjang(d.dari)} - ${DMS.tanggalPanjang(d.sampai)}`;
      const judul = `Input ${d.bidang} ${label}`;
      const aoa = [[judul], ['Tanggal', 'Jenis', 'Nomor', 'Jalan', 'Standby', 'Rusak', 'Perbaikan', 'PM', 'Total', 'Availability (%)', 'Status', 'Waktu simpan']];
      // Baris: [tanggal, jenis, nomor, jalan, standby, rusak, perbaikan, pm, total, availability, waktu simpan, status].
      // status: diisi = disimpan petugas; otomatis = Standby 24 jam dari sistem; belum = tidak ada data.
      const STATUS = { diisi: 'Sudah diisi', otomatis: 'Otomatis (Standby)', belum: 'Belum diisi' };
      d.rows.forEach((r) => {
        aoa.push([r[0], r[1], r[2], ...r.slice(3, 10).map((v) => (v === null ? '' : v)), STATUS[r[11]] || '', r[10] || '']);
      });
      DMS.exportXlsx(judul, 'Data ' + d.bidang, aoa);
      bootstrap.Modal.getInstance(modalEkspor)?.hide();
    } catch (err) {
      if (err.message !== 'unauthenticated') DMS.toast(DMS.esc(err.message), 'danger', 6000, { title: 'Gagal ekspor' });
    } finally { tombol.disabled = false; infoEkspor(); }
  });

  // ---- Realtime: perubahan oleh pengguna lain ----
  const live = DMS.live(async () => {
    const data = await DMS.fetchJson(`${C.dataUrl}?tanggal=${encodeURIComponent(state.tanggal)}`);
    const snap = JSON.stringify(data.rows.map((r) => [r.kendaraan_id, r.jalan, r.rusak, r.perbaikan, r.pm]));
    if (snap === state.snapshot) { setLast(data.terakhir_bidang); return; }
    if (!anyDirty()) { fromServer(data); DMS.toast('Data diperbarui oleh orang lain.', 'info', 3000); }
    else { $('#conflictText').textContent = 'Data ini baru diubah orang lain. Isian Anda tetap aman.'; $('#conflictBanner').classList.remove('d-none'); }
  }, $('#liveInd'));
  window.__live = live;

  load();
})();
