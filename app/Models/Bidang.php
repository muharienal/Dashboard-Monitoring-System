<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Bidang extends Model
{
    protected $table = 'bidang';

    protected $fillable = ['kode', 'nama', 'nama_tampil', 'urutan', 'input_semua_kolom'];

    protected function casts(): array
    {
        return ['input_semua_kolom' => 'boolean', 'urutan' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'kode';
    }

    public function jenisKendaraan(): HasMany
    {
        return $this->hasMany(JenisKendaraan::class)->orderBy('nama');
    }

    public function kendaraan(): HasManyThrough
    {
        return $this->hasManyThrough(Kendaraan::class, JenisKendaraan::class);
    }

    public static function ordered()
    {
        return static::query()->orderBy('urutan')->orderBy('nama')->get();
    }
}
