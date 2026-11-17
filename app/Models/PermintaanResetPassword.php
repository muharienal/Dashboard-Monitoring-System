<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanResetPassword extends Model
{
    public const MENUNGGU = 'menunggu';
    public const SELESAI = 'selesai';
    public const DITOLAK = 'ditolak';

    protected $table = 'permintaan_reset_password';

    protected $fillable = ['user_id', 'catatan', 'ip', 'status', 'ditangani_oleh', 'ditangani_at'];

    protected function casts(): array
    {
        return ['ditangani_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeMenunggu(Builder $q): Builder
    {
        return $q->where('status', self::MENUNGGU);
    }

    /** Tandai semua permintaan yang menunggu milik user ini sebagai selesai. */
    public static function selesaikanUntuk(User $user, User $admin): int
    {
        return static::menunggu()->where('user_id', $user->id)
            ->update(['status' => self::SELESAI, 'ditangani_oleh' => $admin->id, 'ditangani_at' => now(), 'updated_at' => now()]);
    }
}
