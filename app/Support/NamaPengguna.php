<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Nama pengguna untuk login (mis. "admin").
 * Isian lama berbentuk email tetap diterima: "admin@gmail.com" → "admin".
 */
final class NamaPengguna
{
    /** Pola yang boleh dipakai: huruf kecil, angka, titik, garis bawah, strip. */
    public const POLA = '/^[a-z0-9][a-z0-9._-]{1,49}$/';

    public static function normal(mixed $isian): mixed
    {
        if (! is_string($isian)) {
            return $isian;
        }
        $isian = Str::lower(trim($isian));
        if (str_contains($isian, '@')) {
            $isian = (string) strstr($isian, '@', true);
        }

        return $isian;
    }
}
