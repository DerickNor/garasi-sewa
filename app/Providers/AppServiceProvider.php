<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Pengaturan awal aplikasi, termasuk directive Blade @rupiah.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Daftarkan service aplikasi (belum ada yang perlu didaftarkan manual).
     */
    public function register(): void
    {
        //
    }

    /**
     * Jalankan pengaturan saat aplikasi mulai.
     */
    public function boot(): void
    {
        // @rupiah($nilai) menampilkan uang dengan format Indonesia, mis. Rp100.000.
        Blade::directive('rupiah', fn (string $expression): string => "<?php echo 'Rp'.number_format((float) ({$expression}), 0, ',', '.'); ?>");
    }
}
