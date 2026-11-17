/* DMS v2 - tombol lihat/sembunyikan untuk SEMUA kolom password (login, ganti password, akun, reset).
 * Dipasang otomatis: cukup tulis <input type="password">, tombol mata ditambahkan sendiri. */
(function () {
  'use strict';

  function pasang(inp) {
    if (inp.dataset.togglePass === '1') return;
    inp.dataset.togglePass = '1';

    let wrap = inp.parentElement;
    if (!wrap.classList.contains('input-icon') && !wrap.classList.contains('pass-wrap')) {
      wrap = document.createElement('div');
      wrap.className = 'pass-wrap';
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);
    }
    let btn = wrap.querySelector('.toggle-pass');
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'toggle-pass';
      btn.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
      wrap.appendChild(btn);
    }
    if (inp.id) btn.setAttribute('aria-controls', inp.id);

    const set = (lihat) => {
      inp.type = lihat ? 'text' : 'password';
      btn.querySelector('i').className = lihat ? 'fas fa-eye-slash' : 'fas fa-eye';
      const teks = lihat ? 'Sembunyikan password' : 'Lihat password';
      btn.setAttribute('aria-label', teks);
      btn.title = teks;
      btn.setAttribute('aria-pressed', lihat ? 'true' : 'false');
    };
    set(false);

    btn.addEventListener('click', () => {
      const pos = inp.selectionStart;
      set(inp.type === 'password');
      inp.focus();
      try { if (pos !== null) inp.setSelectionRange(pos, pos); } catch (_) { /* tipe tertentu tidak mendukung */ }
    });
    // Kembali tersembunyi saat form dikosongkan atau jendela (modal) ditutup.
    if (inp.form) inp.form.addEventListener('reset', () => set(false));
    const modal = inp.closest('.modal');
    if (modal) modal.addEventListener('hidden.bs.modal', () => set(false));
  }

  const semua = () => document.querySelectorAll('input[type="password"]').forEach(pasang);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', semua);
  else semua();
  window.DMSPassword = { pasang };
})();
