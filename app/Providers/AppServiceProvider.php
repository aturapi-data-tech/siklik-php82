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
        Gate::define('daftar.edit', fn ($user) => $user->hasAnyRole(AksiRole::DAFTAR_EDIT));
        Gate::define('pcare.kirimPendaftaran', fn ($user) => $user->hasAnyRole(AksiRole::PCARE_KIRIM_PENDAFTARAN));
        Gate::define('pcare.kelolaKunjungan', fn ($user) => $user->hasAnyRole(AksiRole::PCARE_KELOLA_KUNJUNGAN));
        Gate::define('pcare.lihatRiwayat', fn ($user) => $user->hasAnyRole(AksiRole::PCARE_LIHAT_RIWAYAT));
        Gate::define('antrean.taskId', fn ($user) => $user->hasAnyRole(AksiRole::ANTREAN_TASK_ID));
        Gate::define('eresep.tulis', fn ($user) => $user->hasAnyRole(AksiRole::ERESEP_TULIS));
        Gate::define('rm.salinResep', fn ($user) => $user->hasAnyRole(AksiRole::RM_SALIN_RESEP));
        Gate::define('penunjang.lihatBerkas', fn ($user) => $user->hasAnyRole(AksiRole::PENUNJANG_LIHAT_BERKAS));
        Gate::define('penunjang.unggah', fn ($user) => $user->hasAnyRole(AksiRole::PENUNJANG_UNGGAH));
        Gate::define('radiologi.lihatHasil', fn ($user) => $user->hasAnyRole(AksiRole::RADIOLOGI_LIHAT_HASIL));
        Gate::define('lab.lihatHasil', fn ($user) => $user->hasAnyRole(AksiRole::LAB_LIHAT_HASIL));
        Gate::define('lab.cetak', fn ($user) => $user->hasAnyRole(AksiRole::LAB_CETAK));
        Gate::define('gudang.medis', fn ($user) => $user->hasAnyRole(AksiRole::GUDANG_MEDIS));
        Gate::define('gudang.nonMedis', fn ($user) => $user->hasAnyRole(AksiRole::GUDANG_NON_MEDIS));
        Gate::define('gudang.hapusPenerimaan', fn ($user) => $user->hasAnyRole(AksiRole::GUDANG_HAPUS_PENERIMAAN));
        Gate::define('kas.hapusTransaksi', fn ($user) => $user->hasAnyRole(AksiRole::KAS_HAPUS_TRANSAKSI));
        Gate::define('laporan.pendapatan', fn ($user) => $user->hasAnyRole(AksiRole::LAPORAN_PENDAPATAN));
        Gate::define('administrasi.batalTransfer', fn ($user) => $user->hasAnyRole(AksiRole::ADMINISTRASI_BATAL_TRANSFER));
        Gate::define('satusehat.kirim', fn ($user) => $user->hasAnyRole(AksiRole::SATUSEHAT_KIRIM));
        Gate::define('antrean.batal', fn ($user) => $user->hasAnyRole(AksiRole::ANTREAN_BATAL));
        Gate::define('daftar.hapus', fn ($user) => $user->hasAnyRole(AksiRole::DAFTAR_HAPUS));
        Gate::define('rujukan.kirim', fn ($user) => $user->hasAnyRole(AksiRole::RUJUKAN_KIRIM));
        Gate::define('rujukan.batal', fn ($user) => $user->hasAnyRole(AksiRole::RUJUKAN_BATAL));

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
