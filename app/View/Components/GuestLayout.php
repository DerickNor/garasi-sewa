<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout halaman sebelum masuk (form masuk dan daftar).
 */
class GuestLayout extends Component
{
    /**
     * Tampilan Blade untuk komponen layout ini.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
