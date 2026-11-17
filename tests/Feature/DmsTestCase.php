<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\JenisKendaraan;
use App\Models\Kendaraan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class DmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected Bidang $anglu;
    protected Bidang $wil2;
    protected Kendaraan $k1;
    protected Kendaraan $k2;
    protected Kendaraan $k3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->anglu = Bidang::where('kode', 'angkutan-luar')->first();
        $this->wil2 = Bidang::where('kode', 'alber-wil-2')->first();
        $dt = JenisKendaraan::where('bidang_id', $this->anglu->id)->where('nama', 'DUMP TRUK B3')->first();
        $this->k1 = Kendaraan::create(['jenis_kendaraan_id' => $dt->id, 'nomor_kendaraan' => 'W 1 AA']);
        $this->k2 = Kendaraan::create(['jenis_kendaraan_id' => $dt->id, 'nomor_kendaraan' => 'W 2 AA']);
        $exc = JenisKendaraan::where('bidang_id', $this->wil2->id)->where('nama', 'EXCAVATOR')->first();
        $this->k3 = Kendaraan::create(['jenis_kendaraan_id' => $exc->id, 'nomor_kendaraan' => 'EXC 01']);
    }

    protected function user(string $role, ?int $bidangId = null): User
    {
        return User::create(['name' => ucfirst($role), 'username' => $role.uniqid(), 'password' => 'Rahasia#123',
            'role' => $role, 'bidang_id' => $bidangId, 'is_active' => true]);
    }

    protected function simpan(User $u, Bidang $b, string $tgl, array $rows)
    {
        return $this->actingAs($u)->postJson(route('input.simpan', $b), ['tanggal' => $tgl, 'rows' => $rows]);
    }
}
