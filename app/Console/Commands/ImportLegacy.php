<?php

namespace App\Console\Commands;

use App\Models\Bidang;
use App\Models\JenisKendaraan;
use App\Models\Kendaraan;
use App\Models\User;
use App\Support\DumpSqlLama;
use App\Support\NamaPengguna;
use App\Support\JamKerja;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Memindahkan data dari database lama (db_dashboard, PHP native) ke skema Laravel baru.
 *
 * Perbaikan yang dilakukan otomatis:
 *  - Jam negatif / > 24 / total != 24 dihitung ulang (standby = 24 - jalan - rusak - perbaikan - pm).
 *  - availability_rate & status utilitas dihitung ulang dengan SATU rumus yang sama dengan form input.
 *  - Tabel availability_data (pivot hasil trigger) TIDAK dipindah: berisi baris yatim yang membuat
 *    angka Tableau dobel. Sekarang pivot dihitung langsung dari sheet_data (view v_availability_data).
 *  - Kendaraan yang ada di riwayat tapi sudah dihapus dari master dibuat ulang sebagai NON-AKTIF.
 *  - Waktu updated_at lama (UTC) dikonversi ke zona waktu aplikasi.
 */
class ImportLegacy extends Command
{
    protected $signature = 'dms:import-legacy
        {--connection=legacy : Nama koneksi database lama (lihat config/database.php)}
        {--file= : Baca langsung dari file dump .sql DMS lama (tanpa impor ke server)}
        {--keep-utilitas : Pertahankan status utilitas lama (tidak dihitung ulang)}
        {--with-master-data : Ikut impor baris tabel master_data (form lama Jan 2025) yang belum ada di sheet_data}
        {--skip-users : Jangan impor akun pengguna}
        {--timpa-pengguna : Akun yang sudah ada di DMS v2 ikut ditimpa (nama, role, password lama). Bawaan: tidak diubah}
        {--chunk=2000 : Ukuran batch}';

    protected $description = 'Impor data dari database DMS lama (v1, PHP native) ke database Laravel';

    private array $kendaraanMap = [];   // "BIDANG|JENIS|NOMOR" => [kendaraan_id, jenis_id, bidang_id]
    private array $bidangMap = [];      // "ANGKUTAN LUAR" => id
    private array $jenisMap = [];       // "BIDANG|JENIS" => id
    /** Sumber data: koneksi database lama ATAU file dump .sql (salah satu). */
    private $old = null;
    private ?DumpSqlLama $dump = null;
    /** @var array<string, array<int, object>> tabel kecil dari file dump */
    private array $kecil = [];

    private array $stat = ['diperbaiki' => 0, 'utilitas_berubah' => 0, 'dilewati' => 0, 'diimpor' => 0, 'kendaraan_nonaktif' => 0, 'master_data' => 0];

    /** Nama bidang pada tabel master_data lama -> nama bidang baku. */
    private const ALIAS_BIDANG = [
        'ALBER WIL 1 PG' => 'ALBER WIL 1',
        'ALBER WIL 2 PG' => 'ALBER WIL 2',
        'ALBER WIL 2 FI' => 'ALBER WIL 3', // halaman lama "sheetAlberWil2fi" = Alat Berat Wilayah 3
    ];

    public function handle(): int
    {
        if ($file = $this->option('file')) {
            try {
                $this->dump = new DumpSqlLama($file);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info('Membaca file: '.$file);
            $this->kecil = $this->dump->ambil(['bidang', 'jenis_kendaraan', 'kendaraan', 'users', 'master_data']);
            if (! $this->kecil['bidang'] && ! $this->kecil['kendaraan']) {
                $this->error('File ini bukan dump database DMS lama (tabel bidang/kendaraan tidak ditemukan).');

                return self::FAILURE;
            }
            $sumber = basename($file);
        } else {
            $this->old = DB::connection($this->option('connection'));
            try {
                $this->old->getPdo();
            } catch (\Throwable $e) {
                $this->error('Tidak bisa konek ke database lama: '.$e->getMessage());

                return self::FAILURE;
            }
            $sumber = $this->old->getDatabaseName();
        }
        $tz = config('app.timezone');
        $this->info("Sumber: {$sumber}  ->  Tujuan: ".DB::connection()->getDatabaseName());

        // Akun yang sudah ada sebelum impor (dibuat/diubah di DMS v2) tidak disentuh kecuali --timpa-pengguna.
        $penggunaLama = User::pluck('id')->all();
        $this->call('db:seed', ['--force' => true]);

        // 1. Bidang
        foreach ($this->sumberBidang() as $b) {
            $nama = trim($b->nama_bidang);
            $this->bidangMap[$nama] = Bidang::firstOrCreate(['nama' => $nama], [
                'kode' => Str::slug($nama), 'nama_tampil' => Str::title(Str::lower($nama)), 'urutan' => 99,
            ])->id;
        }
        foreach (Bidang::all() as $b) {
            $this->bidangMap[$b->nama] = $b->id;
        }

        // 2. Jenis kendaraan (master + yang muncul di riwayat)
        // (dari file dump, jenis yang hanya ada di riwayat dibuat saat data harian dibaca)
        foreach ($this->sumberJenis() as $r) {
            $this->jenisId(trim($r->bidang), trim($r->jenis));
        }

        // 3. Kendaraan master (aktif)
        $kLama = $this->sumberKendaraan();
        foreach ($kLama as $r) {
            $this->kendaraanId(trim($r->bidang), trim($r->jenis), trim($r->nomor), true);
        }
        $this->line('Kendaraan master: '.count($kLama));
        // Kendaraan yang masih aktif di DMS v2 tapi sudah tidak ada di master lama (mungkin dijual/dipindah): dilaporkan, tidak diubah.
        $kunciLama = $kLama->map(fn ($r) => $this->namaBidang($r->bidang).'|'.trim($r->jenis).'|'.trim($r->nomor))->flip();
        $hilang = Kendaraan::query()->join('jenis_kendaraan as j', 'j.id', '=', 'kendaraan.jenis_kendaraan_id')->join('bidang as b', 'b.id', '=', 'j.bidang_id')
            ->whereNull('kendaraan.nonaktif_sejak')->whereNull('kendaraan.keterangan')
            ->get(['b.nama as bidang', 'j.nama as jenis', 'kendaraan.nomor_kendaraan as nomor'])
            ->reject(fn ($r) => $kunciLama->has($r->bidang.'|'.$r->jenis.'|'.$r->nomor));
        if ($hilang->isNotEmpty()) {
            $this->warn("Perlu dicek: {$hilang->count()} kendaraan aktif di DMS v2 tidak ada di master lama: ".$hilang->take(10)->pluck('nomor')->implode(', ').($hilang->count() > 10 ? ', …' : ''));
        }

        // 4. Pengguna
        if (! $this->option('skip-users')) {
            $diimpor = [];
            foreach ($this->sumberUsers() as $u) {
                // DMS lama login memakai email; di DMS v2 nama pengguna = bagian email sebelum "@".
                $username = NamaPengguna::normal((string) $u->email);
                $diimpor[] = $username;
                $user = User::firstOrNew(['username' => $username]);
                if ($user->exists && in_array($user->id, $penggunaLama, true) && ! $this->option('timpa-pengguna')) {
                    continue; // sudah ada di DMS v2: password/role/bidang/aktif tetap seperti di v2
                }
                $user->fill([
                    'name' => $u->username,
                    'role' => in_array($u->role, array_keys(User::ROLES), true) ? $u->role : User::ROLE_MANAJEMEN,
                    'is_active' => true,
                ]);
                if (! $user->exists) {
                    $user->setRawAttributes($user->getAttributes() + ['password' => '!']);
                    $user->created_at = $u->created_at ? Carbon::parse($u->created_at, $tz) : now();
                }
                $user->save();
                // Hash bcrypt lama ($2y$10$...) ditulis apa adanya: kompatibel dengan Hash::check Laravel,
                // dan otomatis di-rehash ke cost baru saat user login pertama kali.
                DB::table('users')->where('id', $user->id)->update(['password' => $u->password]);
            }
            // Akun default seeder tidak diperlukan bila admin lama sudah ada
            $adminAwal = config('dms.admin_username');
            if (! in_array($adminAwal, $diimpor, true) && User::where('role', 'admin')->where('username', '!=', $adminAwal)->exists()) {
                User::where('username', $adminAwal)->delete();
            }
            $this->line('Pengguna: '.User::count().' (akun baru memakai password lama; akun yang sudah ada di v2 tidak diubah)');
        }

        // 5. sheet_data (dibaca berurutan per id, disimpan per batch)
        $total = $this->old?->table('sheet_data')->count();
        $this->info('Mengimpor '.($total !== null ? $total.' ' : '').'baris sheet_data ...');
        $bar = $this->output->createProgressBar($total ?? 0);
        $batch = [];
        foreach ($this->sumberSheet() as $r) {
            $bar->advance();
            $row = $this->mapRow($r->bidang, $r->jenis_kendaraan, $r->nomor_kendaraan, $r->tanggal,
                (int) $r->jalan, (int) $r->standby, (int) $r->rusak, (int) $r->perbaikan, (int) $r->pm,
                [(int) $r->utilitas_jalan, (int) $r->utilitas_standby, (int) $r->utilitas_rusak],
                $r->updated_at ? Carbon::parse($r->updated_at, 'UTC')->setTimezone($tz)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'));
            if ($row) {
                $batch[] = $row;
            }
            if (count($batch) >= (int) $this->option('chunk')) {
                $this->upsert($batch);
                $batch = [];
            }
        }
        $this->upsert($batch);
        $bar->finish();
        $this->newLine();

        // 6. master_data (opsional)
        if ($this->option('with-master-data')) {
            $this->importMasterData();
        }

        // Periode aktif: mulai = data pertama, nonaktif = sehari setelah data terakhir (kendaraan dari data lama)
        Kendaraan::isiPeriodeAktifKosong();
        Cache::forget('dms:data-version');

        $this->table(['Keterangan', 'Jumlah'], [
            ['Baris sheet_data diimpor', $this->stat['diimpor']],
            ['Baris diperbaiki (jam negatif / total != 24)', $this->stat['diperbaiki']],
            ['Status utilitas dihitung ulang (berbeda dari data lama)', $this->stat['utilitas_berubah']],
            ['Baris dilewati (tidak valid)', $this->stat['dilewati']],
            ['Kendaraan riwayat dibuat non-aktif', $this->stat['kendaraan_nonaktif']],
            ['Baris master_data ditambahkan', $this->stat['master_data']],
            ['Total sheet_data sekarang', DB::table('sheet_data')->count()],
        ]);
        $this->info('Selesai. Tabel availability_data lama tidak diperlukan lagi (diganti view v_availability_data).');

        return self::SUCCESS;
    }

    private function namaBidang(string $nama): string
    {
        $nama = trim($nama);

        return self::ALIAS_BIDANG[$nama] ?? $nama;
    }

    private function jenisId(string $bidang, string $jenis): int
    {
        $bidang = $this->namaBidang($bidang);
        $key = "{$bidang}|{$jenis}";
        if (! isset($this->jenisMap[$key])) {
            if (! isset($this->bidangMap[$bidang])) {
                $this->bidangMap[$bidang] = Bidang::firstOrCreate(['nama' => $bidang], [
                    'kode' => Str::slug($bidang), 'nama_tampil' => Str::title(Str::lower($bidang)), 'urutan' => 99,
                ])->id;
            }
            $this->jenisMap[$key] = JenisKendaraan::firstOrCreate(['bidang_id' => $this->bidangMap[$bidang], 'nama' => $jenis])->id;
        }

        return $this->jenisMap[$key];
    }

    /** @return array{0:int,1:int,2:int} [kendaraan_id, jenis_id, bidang_id] */
    private function kendaraanId(string $bidang, string $jenis, string $nomor, bool $aktif): array
    {
        $bidang = $this->namaBidang($bidang);
        $key = "{$bidang}|{$jenis}|{$nomor}";
        if (! isset($this->kendaraanMap[$key])) {
            $jid = $this->jenisId($bidang, $jenis);
            $k = Kendaraan::firstOrCreate(['jenis_kendaraan_id' => $jid, 'nomor_kendaraan' => $nomor], [
                'is_active' => $aktif,
                'keterangan' => $aktif ? null : 'Dari data lama',
            ]);
            if ($k->wasRecentlyCreated && ! $aktif) {
                $this->stat['kendaraan_nonaktif']++;
            }
            $this->kendaraanMap[$key] = [$k->id, $jid, $this->bidangMap[$bidang]];
        }

        return $this->kendaraanMap[$key];
    }

    private function mapRow(string $bidang, string $jenis, string $nomor, string $tanggal, int $jalan, int $standby, int $rusak,
        int $perbaikan, int $pm, array $utilLama, string $updatedAt): ?array
    {
        [$kid, $jid, $bid] = $this->kendaraanId(trim($bidang), trim($jenis), trim($nomor), false);

        $asli = [$jalan, $standby, $rusak, $perbaikan, $pm];
        $jalan = max(0, min(24, $jalan));
        $rusak = max(0, min(24, $rusak));
        $perbaikan = max(0, min(24, $perbaikan));
        $pm = max(0, min(24, $pm));
        try {
            $h = JamKerja::hitung($jalan, $rusak, $perbaikan, $pm);
        } catch (\InvalidArgumentException) {
            $this->stat['dilewati']++;
            $this->warn("  Dilewati: {$tanggal} {$bidang} {$nomor} (jam > 24)");

            return null;
        }
        if ($asli !== [$h['jalan'], $h['standby'], $h['rusak'], $h['perbaikan'], $h['pm']]) {
            $this->stat['diperbaiki']++;
        }
        $utilBaru = [$h['utilitas_jalan'], $h['utilitas_standby'], $h['utilitas_rusak']];
        if ($utilLama !== $utilBaru) {
            if ($this->option('keep-utilitas') && array_sum($utilLama) === 1 && min($utilLama) >= 0 && $asli === [$h['jalan'], $h['standby'], $h['rusak'], $h['perbaikan'], $h['pm']]) {
                [$h['utilitas_jalan'], $h['utilitas_standby'], $h['utilitas_rusak']] = $utilLama;
            } else {
                $this->stat['utilitas_berubah']++;
            }
        }
        $this->stat['diimpor']++;

        return $h + [
            'tanggal' => $tanggal, 'kendaraan_id' => $kid, 'bidang_id' => $bid, 'jenis_kendaraan_id' => $jid,
            'created_by' => null, 'updated_by' => null, 'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ];
    }

    private function upsert(array $batch): void
    {
        if (! $batch) {
            return;
        }
        $cols = ['bidang_id', 'jenis_kendaraan_id', 'jalan', 'standby', 'rusak', 'perbaikan', 'pm', 'total_jam_kerja',
            'availability_rate', 'utilitas_jalan', 'utilitas_standby', 'utilitas_rusak', 'updated_at'];
        foreach (array_chunk($batch, 500) as $part) {
            DB::table('sheet_data')->upsert($part, ['kendaraan_id', 'tanggal'], $cols);
        }
    }

    private function importMasterData(): void
    {
        $this->info('Mengimpor master_data yang belum ada ...');
        $rows = $this->old
            ? $this->old->table('master_data')->where('tanggal', '>=', '2020-01-01')->orderBy('id')->get()
            : collect($this->kecil['master_data'])->filter(fn ($r) => $r->tanggal >= '2020-01-01')->sortBy(fn ($r) => (int) $r->id)->values();
        $batch = [];
        foreach ($rows as $r) {
            [$kid] = $this->kendaraanId($r->bidang, trim($r->jenis_kendaraan), trim($r->nomor_kendaraan), false);
            if (DB::table('sheet_data')->where('kendaraan_id', $kid)->where('tanggal', $r->tanggal)->exists()) {
                continue;
            }
            $row = $this->mapRow($r->bidang, $r->jenis_kendaraan, $r->nomor_kendaraan, $r->tanggal, (int) $r->jalan,
                (int) $r->standby, (int) $r->rusak, (int) $r->perbaikan, (int) $r->pm, [-1, -1, -1], $r->tanggal.' 23:59:00');
            if ($row) {
                $batch[$kid.'|'.$r->tanggal] = $row;
                $this->stat['master_data']++;
                $this->stat['utilitas_berubah']--;
            }
        }
        $this->upsert(array_values($batch));
    }

    // ---------------------------------------------------------------- Sumber data (database lama atau file dump)

    /** @return iterable<object{nama_bidang:string}> */
    private function sumberBidang(): iterable
    {
        return $this->old ? $this->old->table('bidang')->get() : $this->kecil['bidang'];
    }

    /** Jenis master (+ jenis di riwayat bila dari database). @return iterable<object{bidang:string,jenis:string}> */
    private function sumberJenis(): iterable
    {
        if ($this->old) {
            return $this->old->table('jenis_kendaraan as j')->join('bidang as b', 'b.id_bidang', '=', 'j.id_bidang')
                ->select('b.nama_bidang as bidang', 'j.nama_jenis as jenis')->get()
                ->concat($this->old->table('sheet_data')->select('bidang', 'jenis_kendaraan as jenis')->distinct()->get());
        }
        $bidang = collect($this->kecil['bidang'])->pluck('nama_bidang', 'id_bidang');

        return collect($this->kecil['jenis_kendaraan'])->filter(fn ($j) => isset($bidang[$j->id_bidang]))
            ->map(fn ($j) => (object) ['bidang' => $bidang[$j->id_bidang], 'jenis' => $j->nama_jenis])->values();
    }

    /** Kendaraan di master lama. */
    private function sumberKendaraan(): \Illuminate\Support\Collection
    {
        if ($this->old) {
            return $this->old->table('kendaraan as k')->join('jenis_kendaraan as j', 'j.id_jenis', '=', 'k.id_jenis')
                ->join('bidang as b', 'b.id_bidang', '=', 'j.id_bidang')
                ->select('b.nama_bidang as bidang', 'j.nama_jenis as jenis', 'k.nomor_kendaraan as nomor')->get();
        }
        $bidang = collect($this->kecil['bidang'])->pluck('nama_bidang', 'id_bidang');
        $jenis = collect($this->kecil['jenis_kendaraan'])->keyBy('id_jenis');

        return collect($this->kecil['kendaraan'])
            ->filter(fn ($k) => isset($jenis[$k->id_jenis]) && isset($bidang[$jenis[$k->id_jenis]->id_bidang]))
            ->map(fn ($k) => (object) ['bidang' => $bidang[$jenis[$k->id_jenis]->id_bidang], 'jenis' => $jenis[$k->id_jenis]->nama_jenis, 'nomor' => $k->nomor_kendaraan])
            ->values();
    }

    private function sumberUsers(): iterable
    {
        return $this->old ? $this->old->table('users')->get() : $this->kecil['users'];
    }

    /** Baris sheet_data lama, urut id. */
    private function sumberSheet(): iterable
    {
        if ($this->old) {
            return $this->old->table('sheet_data')->lazyById((int) $this->option('chunk'));
        }

        return (function () {
            foreach ($this->dump->baris(['sheet_data']) as [, $r]) {
                yield $r;
            }
        })();
    }
}
