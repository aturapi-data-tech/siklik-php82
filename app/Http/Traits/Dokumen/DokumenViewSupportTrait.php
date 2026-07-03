<?php

namespace App\Http\Traits\Dokumen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Helper bersama untuk komponen viewer dokumen di display Rekam Medis RJ.
 *
 * Menyediakan:
 * - navField/navIdList/navTotal/navPos/prevRecord/nextRecord — navigasi antar-record
 *   dalam satu modal Lihat (tanpa buka-tutup).
 * - dvPasien()/dvTtdPath()/dvIdentitasRs() — data pasien, path TTD, identitas RS (kop).
 * - renderDokumenPreview() — render blade cetak jadi HTML self-contained utk iframe preview.
 *
 * Dipakai bersama MasterPasienTrait (findDataMasterPasien) di komponen pemakai.
 * (Versi RJ-only: method khusus RI di sirus tidak diikutkan.)
 */
trait DokumenViewSupportTrait
{
    /**
     * Navigasi Prev/Next antar-record dalam satu modal (tanpa buka-tutup).
     * Komponen list mengeset $navField = nama field id (createdAt/signatureDate/…);
     * navigasi berjalan di atas $this->list + $this->selected, memanggil lihat().
     */
    public string $navField = '';

    /** Daftar id record yg bisa dinavigasi (urut sama dgn baris list). */
    protected function navIdList(): array
    {
        if ($this->navField === '') {
            return [];
        }
        return collect($this->list ?? [])
            ->filter(fn($entri) => filled(data_get($entri, $this->navField)))
            ->pluck($this->navField)
            ->map(fn($id) => (string) $id)
            ->values()->all();
    }

    public function navTotal(): int
    {
        return count($this->navIdList());
    }

    /** Posisi 1-based record aktif (0 bila tak ada). */
    public function navPos(): int
    {
        $pos = array_search((string) data_get($this->selected ?? [], $this->navField, ''), $this->navIdList(), true);
        return $pos === false ? 0 : $pos + 1;
    }

    public function prevRecord(): void
    {
        $ids = $this->navIdList();
        $pos = array_search((string) data_get($this->selected ?? [], $this->navField, ''), $ids, true);
        if ($pos !== false && $pos > 0) {
            $this->lihat($ids[$pos - 1]);
        }
    }

    public function nextRecord(): void
    {
        $ids = $this->navIdList();
        $pos = array_search((string) data_get($this->selected ?? [], $this->navField, ''), $ids, true);
        if ($pos !== false && $pos < count($ids) - 1) {
            $this->lihat($ids[$pos + 1]);
        }
    }

    /** Data pasien (findDataMasterPasien) + hitung umur ($pasien['thn']). */
    protected function dvPasien(?string $regNo): array
    {
        $pasien = $this->findDataMasterPasien($regNo ?? '')['pasien'] ?? [];
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }
        return $pasien;
    }

    /** Path TTD dari myuser_code (null bila tak ada / file hilang). */
    protected function dvTtdPath(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }
        $ttdPath = DB::table('users')->where('myuser_code', $code)->value('myuser_ttd_image');
        return (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath)))
            ? public_path('storage/' . $ttdPath)
            : null;
    }

    /** Identitas RS untuk kop cetak. Siklik memakai tabel dimst_identitases. */
    protected function dvIdentitasRs()
    {
        return DB::table('dimst_identitases')
            ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')
            ->first();
    }

    /**
     * Render blade print jadi HTML self-contained (untuk preview iframe di modal Lihat).
     * $view = nama blade lengkap (mis. 'pages.components.modul-dokumen.r-j.xxx.cetak-xxx-print').
     * Memakai payload yg sama dgn cetak → isi Lihat = persis tampilan Cetak.
     */
    protected function renderDokumenPreview(string $view, array $data): string
    {
        $html = view($view, ['data' => $data])->render();

        // Print blade memakai path filesystem absolut (public_path) untuk gambar
        // logo & TTD — DomPDF butuh itu, tapi di iframe browser jadi URL 404.
        // Untuk preview saja: ubah prefix public_path() → path web-relatif
        // ('/images/...', '/storage/...'). Cetak PDF tetap pakai HTML asli.
        $html = str_replace(public_path(), '', $html);

        // Perbesar tampilan preview (font & layout) TANPA mengubah blade cetak —
        // cukup inject zoom khusus iframe. Cetak PDF tidak terpengaruh.
        return str_replace('</head>', "<style>body{zoom:1.4}</style></head>", $html);
    }
}
