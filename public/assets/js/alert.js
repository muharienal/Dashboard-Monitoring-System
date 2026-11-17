/*
 * DMS - alert standar untuk seluruh web (tanpa npm / build tool).
 *
 *   DMSAlert.confirm({ title, text, ok, cancel, tone })  → Promise<boolean>
 *       Dialog konfirmasi sebelum aksi. tone: 'primary' | 'danger' | 'warning'.
 *   DMSAlert.toast(pesan, tipe, ms, { title })
 *       Notifikasi hasil aksi. tipe: 'success' | 'danger' | 'warning' | 'info'.
 *
 * Form otomatis:
 *   <form data-confirm="Teks" data-confirm-title="Judul" data-confirm-ok="Simpan" data-confirm-tone="danger">
 *   atau form.dmsConfirm = (form) => ({ ...opsi })  untuk teks yang dihitung saat submit
 *   (kembalikan false untuk membatalkan tanpa dialog, null untuk lanjut tanpa konfirmasi).
 *   Setiap form POST juga mendapat status "memproses" pada tombolnya agar tidak terkirim dua kali.
 *
 * Pesan dari server (session flash / error validasi) ditulis sebagai <div class="dms-flash" data-type="...">
 * dan otomatis ditampilkan sebagai toast.
 */
(function () {
  'use strict';

  const IKON = {
    success: 'fa-circle-check', danger: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info',
    primary: 'fa-circle-question',
  };
  const JUDUL = { success: 'Berhasil', danger: 'Gagal', warning: 'Perhatian', info: 'Info' };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const kurangGerak = () => window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------------------------------------------------------------- Toast
  function area() {
    let a = document.getElementById('toastArea');
    if (!a) { a = document.createElement('div'); a.id = 'toastArea'; document.body.appendChild(a); }
    a.setAttribute('aria-live', 'polite');
    return a;
  }

  function tutupToast(el) {
    if (!el || el.dataset.tutup) return;
    el.dataset.tutup = '1';
    el.classList.add('keluar');
    setTimeout(() => el.remove(), kurangGerak() ? 0 : 220);
  }

  /**
   * @param {string} message  HTML yang sudah di-escape pemanggil (kompatibel dengan DMS.toast lama).
   * @param {'success'|'danger'|'warning'|'info'} type
   * @param {number} ms  0 = tidak hilang sendiri.
   */
  function toast(message, type = 'success', ms = 4500, opts = {}) {
    if (!IKON[type] || type === 'primary') type = 'info';
    const a = area();
    // Pesan sama yang masih tampil tidak ditumpuk.
    const kunci = type + '|' + message;
    a.querySelectorAll('.dms-toast').forEach((t) => { if (t.dataset.kunci === kunci) tutupToast(t); });
    while (a.querySelectorAll('.dms-toast:not(.keluar)').length >= 4) tutupToast(a.querySelector('.dms-toast:not(.keluar)'));

    const el = document.createElement('div');
    el.className = `dms-toast t-${type}`;
    el.dataset.kunci = kunci;
    el.setAttribute('role', type === 'danger' || type === 'warning' ? 'alert' : 'status');
    const judul = opts.title === undefined ? JUDUL[type] : opts.title;
    el.innerHTML = `<span class="t-ikon" aria-hidden="true"><i class="fas ${IKON[type]}"></i></span>
      <div class="t-isi">${judul ? `<strong class="t-judul">${esc(judul)}</strong>` : ''}<div class="t-pesan">${message}</div></div>
      <button type="button" class="t-tutup" aria-label="Tutup"><i class="fas fa-xmark" aria-hidden="true"></i></button>
      ${ms ? `<span class="t-waktu" style="animation-duration:${ms}ms" aria-hidden="true"></span>` : ''}`;
    a.appendChild(el);
    el.querySelector('.t-tutup').addEventListener('click', () => tutupToast(el));

    if (ms) {
      // Berhenti sementara saat disorot mouse / fokus, supaya pesan panjang sempat dibaca.
      let sisa = ms, mulai = Date.now(), timer = setTimeout(() => tutupToast(el), sisa);
      const jeda = () => { clearTimeout(timer); sisa -= Date.now() - mulai; el.classList.add('jeda'); };
      const lanjut = () => { mulai = Date.now(); clearTimeout(timer); timer = setTimeout(() => tutupToast(el), Math.max(sisa, 1200)); el.classList.remove('jeda'); };
      el.addEventListener('mouseenter', jeda); el.addEventListener('mouseleave', lanjut);
      el.addEventListener('focusin', jeda); el.addEventListener('focusout', lanjut);
    }
    return el;
  }

  // ------------------------------------------------------------- Konfirmasi
  let dialogAktif = null;

  /**
   * @returns {Promise<boolean>} true bila pengguna memilih tombol aksi.
   */
  function confirm(opts = {}) {
    if (typeof opts === 'string') opts = { text: opts };
    const tone = ['danger', 'warning'].includes(opts.tone) ? opts.tone : 'primary';
    const title = opts.title || 'Lanjutkan?';
    const ok = opts.ok || 'Ya, lanjutkan';
    const cancel = opts.cancel || 'Batal';
    const ikon = opts.icon || (tone === 'danger' ? 'fa-trash-can' : tone === 'warning' ? 'fa-triangle-exclamation' : IKON.primary);

    if (typeof HTMLDialogElement === 'undefined') {
      return Promise.resolve(window.confirm(title + (opts.text ? '\n\n' + opts.text : '')));
    }
    if (dialogAktif) dialogAktif.close('batal');

    return new Promise((resolve) => {
      const d = document.createElement('dialog');
      d.className = `dms-dialog d-${tone}`;
      d.setAttribute('aria-labelledby', 'dmsDlgJudul');
      d.setAttribute('aria-describedby', 'dmsDlgTeks');
      d.innerHTML = `<form method="dialog" class="d-kartu">
          <span class="d-ikon" aria-hidden="true"><i class="fas ${esc(ikon)}"></i></span>
          <h2 id="dmsDlgJudul">${esc(title)}</h2>
          ${opts.text ? `<p id="dmsDlgTeks">${opts.html ? opts.text : esc(opts.text)}</p>` : ''}
          <div class="d-aksi">
            <button type="submit" value="batal" class="btn btn-outline-secondary">${esc(cancel)}</button>
            <button type="submit" value="ok" class="btn ${tone === 'danger' ? 'btn-dms-danger' : 'btn-dms'}">${esc(ok)}</button>
          </div>
        </form>`;
      // Bila dibuka dari dalam modal Bootstrap, dialog ditaruh di modal tersebut agar
      // focus-trap Bootstrap tidak merebut fokus dari tombol dialog.
      (document.querySelector('.modal.show') || document.body).appendChild(d);
      dialogAktif = d;
      // Esc hanya menutup dialog ini, bukan modal Bootstrap di belakangnya.
      d.addEventListener('keydown', (e) => { if (e.key === 'Escape') e.stopPropagation(); });
      d.addEventListener('close', () => {
        const hasil = d.returnValue === 'ok';
        dialogAktif = null;
        d.classList.add('keluar');
        setTimeout(() => d.remove(), kurangGerak() ? 0 : 160);
        resolve(hasil);
      }, { once: true });
      // Klik di luar kartu = batal.
      d.addEventListener('click', (e) => { if (e.target === d) d.close('batal'); });
      d.showModal();
      // Aksi berbahaya: fokus ke Batal agar Enter tidak langsung menghapus.
      d.querySelector(tone === 'danger' ? 'button[value="batal"]' : 'button[value="ok"]').focus();
    });
  }

  // ------------------------------------------------- Form: konfirmasi + memproses
  function opsiForm(form) {
    if (typeof form.dmsConfirm === 'function') return form.dmsConfirm(form);
    if (!form.hasAttribute('data-confirm')) return null;
    const ds = form.dataset;
    return { text: ds.confirm, title: ds.confirmTitle, ok: ds.confirmOk, tone: ds.confirmTone, icon: ds.confirmIcon };
  }

  function tandaiMemproses(form, submitter) {
    const btn = submitter || form.querySelector('button[type="submit"], button:not([type])') ||
      (form.id && document.querySelector(`button[form="${form.id}"]`));
    if (!btn) return;
    btn.disabled = true;
    btn.classList.add('memproses');
    btn.dataset.asli = btn.innerHTML;
    const lebar = btn.offsetWidth;
    if (lebar) btn.style.minWidth = lebar + 'px';
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' +
      (btn.textContent.trim() ? ' <span>Memproses…</span>' : '<span class="visually-hidden">Memproses…</span>');
  }

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.method === 'dialog') return;
    const metode = (form.getAttribute('method') || 'get').toLowerCase();

    if (form.dataset.dmsLolos !== '1') {
      const opsi = opsiForm(form);
      // dmsConfirm boleh mengembalikan false = batalkan tanpa dialog (mis. tidak ada perubahan).
      if (opsi === false) { e.preventDefault(); return; }
      if (opsi) {
        e.preventDefault();
        const submitter = e.submitter;
        if (!(await confirm(opsi))) return;
        form.dataset.dmsLolos = '1';
        // Kirim ulang lewat requestSubmit agar validasi & event lain tetap berjalan.
        if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        else { tandaiMemproses(form, submitter); form.submit(); }
        return;
      }
    }
    delete form.dataset.dmsLolos;
    if (e.defaultPrevented || metode !== 'post') return;
    tandaiMemproses(form, e.submitter);
  });

  // Tombol kembali (bfcache): pulihkan tombol yang tadi "memproses".
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('button.memproses').forEach((b) => {
      b.disabled = false; b.classList.remove('memproses'); b.style.minWidth = '';
      if (b.dataset.asli !== undefined) b.innerHTML = b.dataset.asli;
    });
  });

  // ------------------------------------------------------------ Pesan server
  function tampilkanFlash() {
    document.querySelectorAll('.dms-flash').forEach((f, i) => {
      const type = f.dataset.type || 'info';
      const ms = type === 'danger' ? 8000 : type === 'warning' ? 6500 : 4500;
      setTimeout(() => toast(f.innerHTML, type, ms, { title: f.dataset.title }), i * 120);
      f.remove();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tampilkanFlash);
  else tampilkanFlash();

  window.DMSAlert = { toast, confirm, esc };
})();
