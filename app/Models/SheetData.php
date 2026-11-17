<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetData extends Model
{
    protected $table = 'sheet_data';

    protected $fillable = [
        'tanggal', 'kendaraan_id', 'bidang_id', 'jenis_kendaraan_id',
        'jalan', 'standby', 'rusak', 'perbaikan', 'pm', 'total_jam_kerja',
        'availability_rate', 'utilitas_jalan', 'utilitas_standby', 'utilitas_rusak',
        'otomatis', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'availability_rate' => 'float',
            'otomatis' => 'boolean',
        ];
    }

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class);
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class);
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisKendaraan::class, 'jenis_kendaraan_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
