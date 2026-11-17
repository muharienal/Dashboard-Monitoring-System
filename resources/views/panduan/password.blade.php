<p class="lead-p">Ganti password akun Anda.</p>
<ul>
    <li>Password minimal 8 karakter. Setelah diganti, perangkat lain yang memakai akun ini akan keluar.</li>
    <li>Periksa <b>Aktivitas login</b> di bawah formulir; bila ada yang tidak Anda kenali, segera ganti password.</li>
    <li>DMS v2 keluar otomatis bila tidak ada aktivitas selama {{ (int) config('dms.idle_menit') >= 60 ? ((int) config('dms.idle_menit') / 60).' jam' : (int) config('dms.idle_menit').' menit' }}.</li>
</ul>
