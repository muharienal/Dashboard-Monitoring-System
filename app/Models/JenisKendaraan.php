<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKendaraan extends Model
{
    protected $table = 'jenis_kendaraan';

    protected $fillable = ['bidang_id', 'nama'];

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class);
    }

    public function kendaraan(): HasMany
    {
        return $this->hasMany(Kendaraan::class);
    }
}
