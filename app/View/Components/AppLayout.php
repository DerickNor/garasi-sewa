<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout halaman setelah masuk (navigasi, judul halaman, dan pesan hasil aksi).
 */
class AppLayout extends Component
{
    /**
     * Tampilan Blade untuk komponen layout ini.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
