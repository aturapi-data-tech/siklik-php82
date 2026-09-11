<?php

namespace App\Providers;

use App\Services\AppMenu;
use App\Support\AksiRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gate aksi terbatas — daftar role-nya satu sumber di App\Support\AksiRole.
        // Ubah role cukup di kelas itu; nama Gate di bawah dipakai @can di blade
        // dan ->can() di method server.
        Gate::define('dokumen.hapus', fn ($user) => $user->hasAnyRole(AksiRole::DOKUMEN_HAPUS));
        Gate::define('dokumen.bukaKunci', fn ($user) => $user->hasAnyRole(AksiRole::DOKUMEN_BUKA_KUNCI));
        Gate::define('dokumen.buka', fn ($user) => $user->hasAnyRole(AksiRole::DOKUMEN_BUKA));
        Gate::define('emr.logAktivitas', fn ($user) => $user->hasAnyRole(AksiRole::EMR_LOG_AKTIVITAS));
        Gate::define('emr.buka', fn ($user) => $user->hasAnyRole(AksiRole::EMR_BUKA));
        Gate::define('emr.cetakEresep', fn ($user) => $user->hasAnyRole(AksiRole::EMR_CETAK_ERESEP));
        Gate::define('emr.icare', fn ($user) => $user->hasAnyRole(AksiRole::EMR_ICARE));
        Gate::define('administrasi.buka', fn ($user) => $user->hasAnyRole(AksiRole::ADMINISTRASI_BUKA));

        // Share $sidebarMenus (grouped + filtered by user role) ke sidebar layout.
        // Tidak query DB di guest pages — guard via auth check.
        View::composer('layouts.app-sidebar', function ($view) {
            $roles = auth()->check()
                ? auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->values()->toArray()
                : [];

            $view->with('sidebarMenus', AppMenu::grouped($roles));
        });
    }
}
