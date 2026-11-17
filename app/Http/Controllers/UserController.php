<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Models\PermintaanResetPassword;
use App\Models\User;
use App\Support\NamaPengguna;
use App\Support\RiwayatMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $batasOnline = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
        $online = DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', $batasOnline)
            ->selectRaw('user_id, MAX(last_activity) terakhir, COUNT(*) jumlah')->groupBy('user_id')->get()->keyBy('user_id');

        $q = User::query()->with('bidang');
        if ($s = $this->teks($request, 'q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('username', 'like', "%{$s}%"));
        }
        if (array_key_exists($this->teks($request, 'role'), User::ROLES)) {
            $q->where('role', $this->teks($request, 'role'));
        }
        if ($bk = $this->teks($request, 'bidang')) {
            $q->whereHas('bidang', fn ($b) => $b->where('kode', $bk));
        }
        match ($this->teks($request, 'status')) {
            'aktif' => $q->where('is_active', true),
            'nonaktif' => $q->where('is_active', false),
            'online' => $q->where('is_active', true)->whereIn('id', $online->keys()),
            'offline' => $q->where('is_active', true)->whereNotIn('id', $online->keys()),
            default => null,
        };
        $sort = in_array($this->teks($request, 'sort'), ['name', 'username', 'role', 'last_login_at'], true) ? $this->teks($request, 'sort') : 'id';
        $dir = $this->teks($request, 'dir') === 'desc' ? 'desc' : 'asc';

        return view('akun.index', [
            'users' => $q->orderBy($sort, $dir)->paginate(10)->withQueryString(),
            'online' => $online,
            'bidangList' => Bidang::ordered(),
            'permintaan' => PermintaanResetPassword::menunggu()->with('user.bidang')->latest('updated_at')->get(),
            'sort' => $sort, 'dir' => $dir,
            'f' => collect(['q', 'role', 'bidang', 'status'])->mapWithKeys(fn ($k) => [$k => $this->teks($request, $k)])->all(),
        ]);
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:2', 'max:50', 'regex:'.NamaPengguna::POLA, Rule::unique('users', 'username')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'bidang_id' => ['nullable', 'exists:bidang,id'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    /** Nama pengguna disimpan huruf kecil tanpa spasi di awal/akhir. */
    private function siapkan(Request $request): void
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => mb_strtolower(trim($request->input('username')))]);
        }
    }

    private const PESAN = [
        'username.regex' => 'Nama pengguna hanya boleh huruf kecil, angka, titik, garis bawah, atau strip, tanpa spasi.',
        'username.unique' => 'Nama pengguna ini sudah dipakai akun lain.',
    ];

    private const ATRIBUT = ['name' => 'Nama', 'username' => 'Nama pengguna', 'role' => 'Role', 'bidang_id' => 'Bidang', 'password' => 'Password'];

    public function store(Request $request)
    {
        $this->siapkan($request);
        $data = $request->validate($this->rules(), self::PESAN, self::ATRIBUT);
        $data['bidang_id'] = $data['role'] === User::ROLE_BIDANG ? ($data['bidang_id'] ?? null) : null;
        $data['is_active'] = $request->boolean('is_active', true);
        $baru = User::create($data);
        RiwayatMaster::catat('akun', $baru->id, $baru->name, 'tambah', null, RiwayatMaster::akun($baru));

        return back()->with('success', "Akun {$data['name']} ditambahkan.");
    }

    public function update(Request $request, User $user)
    {
        $this->siapkan($request);
        $data = $request->validate($this->rules($user), self::PESAN, self::ATRIBUT);
        $data['bidang_id'] = $data['role'] === User::ROLE_BIDANG ? ($data['bidang_id'] ?? null) : null;
        $data['is_active'] = $request->boolean('is_active');

        if ($user->id === $request->user()->id && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])) {
            return back()->with('error', 'Role dan status akun sendiri tidak bisa diubah.');
        }
        if ($user->isAdmin() && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])
            && User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Harus ada minimal satu admin aktif.');
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $sebelum = RiwayatMaster::akun($user);
        $user->update($data);
        RiwayatMaster::catat('akun', $user->id, $user->name, 'ubah', $sebelum, RiwayatMaster::akun($user->fresh()),
            isset($data['password']) ? 'Password diganti oleh admin' : null);
        if (! $user->is_active || isset($data['password'])) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        if (isset($data['password'])) {
            PermintaanResetPassword::selesaikanUntuk($user, $request->user());
        }

        return back()->with('success', "Akun {$user->name} disimpan.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Akun sendiri tidak bisa dihapus.');
        }
        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('error', 'Admin terakhir tidak bisa dihapus.');
        }
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $sebelum = RiwayatMaster::akun($user);
        $user->delete();
        RiwayatMaster::catat('akun', $user->id, $user->name, 'hapus', $sebelum);

        return back()->with('success', "Akun {$user->name} dihapus.");
    }

    /** Paksa logout: hapus semua sesi aktif user (pengganti userLogout.php). */
    /** Permintaan lupa password: admin mengatur password baru. */
    public function resetSelesai(Request $request, PermintaanResetPassword $permintaan)
    {
        if ($permintaan->status !== PermintaanResetPassword::MENUNGGU) {
            return back()->with('warning', 'Permintaan ini sudah ditangani.');
        }
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']], [], ['password' => 'Password baru']);
        $user = $permintaan->user;
        $user->update(['password' => $data['password']]);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        PermintaanResetPassword::selesaikanUntuk($user, $request->user());
        RiwayatMaster::catat('akun', $user->id, $user->name, 'password', null, null, 'Dari permintaan lupa password');

        return back()->with('success', "Password {$user->name} sudah diganti. Beri tahu password baru kepada yang bersangkutan.");
    }

    public function resetTolak(Request $request, PermintaanResetPassword $permintaan)
    {
        if ($permintaan->status !== PermintaanResetPassword::MENUNGGU) {
            return back()->with('warning', 'Permintaan ini sudah ditangani.');
        }
        $permintaan->update(['status' => PermintaanResetPassword::DITOLAK, 'ditangani_oleh' => $request->user()->id, 'ditangani_at' => now()]);
        RiwayatMaster::catat('akun', $permintaan->user->id, $permintaan->user->name, 'reset_tolak', null, null, 'Permintaan lupa password ditolak');

        return back()->with('success', "Permintaan dari {$permintaan->user->name} ditolak.");
    }

    public function forceLogout(Request $request, User $user)
    {
        $n = DB::table('sessions')->where('user_id', $user->id)
            ->when($user->id === $request->user()->id, fn ($q) => $q->where('id', '!=', $request->session()->getId()))
            ->delete();
        RiwayatMaster::catat('akun', $user->id, $user->name, 'keluar', null, null, 'Dikeluarkan dari semua perangkat');

        return back()->with('success', "{$user->name} sudah dikeluarkan.");
    }
}
