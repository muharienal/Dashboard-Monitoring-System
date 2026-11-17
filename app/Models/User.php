<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_BIDANG = 'bidang';
    public const ROLE_PEMELIHARAAN = 'pemeliharaan';
    public const ROLE_MANAJEMEN = 'manajemen';

    public const ROLES = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_BIDANG => 'Bidang',
        self::ROLE_PEMELIHARAAN => 'Pemeliharaan',
        self::ROLE_MANAJEMEN => 'Manajemen',
    ];

    protected $fillable = ['name', 'username', 'password', 'role', 'bidang_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Batas bidang untuk Riwayat perubahan data input.
     * null = semua bidang (admin, pemeliharaan, manajemen, atau petugas bidang tanpa bidang tetap);
     * angka = petugas bidang hanya melihat riwayat bidangnya sendiri.
     */
    public function bidangRiwayat(): ?int
    {
        return $this->role === self::ROLE_BIDANG ? $this->bidang_id : null;
    }

    /** Boleh melihat riwayat input untuk bidang ini? (tab Data master & Login tetap khusus admin) */
    public function bolehLihatRiwayat(?int $bidangId): bool
    {
        $batas = $this->bidangRiwayat();

        return $batas === null || $batas === $bidangId;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Boleh membuka halaman input untuk bidang tertentu? */
    public function canInput(Bidang $bidang): bool
    {
        if ($this->isAdmin() || $this->role === self::ROLE_PEMELIHARAAN) {
            return true;
        }
        if ($this->role === self::ROLE_BIDANG) {
            return $this->bidang_id === null || $this->bidang_id === $bidang->id;
        }

        return false; // manajemen: hanya lihat dashboard
    }

    public function canInputAny(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN, self::ROLE_BIDANG, self::ROLE_PEMELIHARAAN);
    }

    /**
     * Kolom yang boleh diubah user ini pada bidang tsb.
     * Aturan sama dengan sistem lama:
     *  - admin: semua kolom
     *  - bidang dengan input_semua_kolom (Alber Wil 2 & 3): semua kolom untuk semua role input
     *  - role bidang: JALAN & RUSAK
     *  - role pemeliharaan: PERBAIKAN & PM
     * STANDBY tidak pernah diinput, selalu dihitung = 24 - (jalan + rusak + perbaikan + pm).
     *
     * @return string[]
     */
    public function editableColumns(Bidang $bidang): array
    {
        if (! $this->canInput($bidang)) {
            return [];
        }
        if ($this->isAdmin() || $bidang->input_semua_kolom) {
            return ['jalan', 'rusak', 'perbaikan', 'pm'];
        }

        return $this->role === self::ROLE_BIDANG ? ['jalan', 'rusak'] : ['perbaikan', 'pm'];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }
}
