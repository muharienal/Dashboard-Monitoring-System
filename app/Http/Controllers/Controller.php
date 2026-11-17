<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Ambil parameter teks dengan aman (array / nilai aneh dianggap kosong). */
    protected function teks(\Illuminate\Http\Request $request, string $key): string
    {
        $v = $request->input($key);

        return is_scalar($v) ? trim((string) $v) : '';
    }
    //
}
