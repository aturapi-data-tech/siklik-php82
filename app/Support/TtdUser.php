<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolusi lokasi gambar tanda tangan user (kolom users.myuser_ttd_image).
 *
 * Kolom itu menyimpan DUA format:
 *   - Path relatif lengkap (yang dipakai data siklik saat ini), mis.
 *     "UserTtd/HdKLG9kFIEEOtZOHOrvwPN0elsltC8rGonmIa9xh.webp" atau "ttd/ttd_40002_1712.png"
 *     (Kelola User menyimpan ke folder "ttd/").
 *   - Nama file saja, mis. "08052026081302.png" — berkasnya ada di
 *     storage/app/public/UserTtd/ (format baru yang dipakai sirus; disiapkan
 *     supaya porting berkas cetak/viewer antar repo tidak perlu diubah lagi).
 *
 * Sebelum helper ini ada, titik cetak modul dokumen RJ menyusun path sendiri
 * dengan public_path('storage/' . $nilai) — benar untuk format path relatif,
 * tetapi untuk format nama-file-saja mencari storage/08052026081302.png yang
 * tidak ada, sehingga file_exists gagal dan TTD petugas tampil kosong di PDF.
 *
 * Semua titik cetak/viewer modul dokumen kini lewat sini.
 * Dokumentasi: docs/ttd-pattern-pdf-print.md §6.
 *
 * Catatan: tabel USERS siklik TIDAK punya kolom emp_id (lihat
 * database/sql/_dev/columns_siklik.txt) — pencarian selalu lewat myuser_code.
 */
class TtdUser
{
    /** Folder standar di disk public untuk nilai yang hanya berisi nama berkas. */
    public const FOLDER = 'UserTtd';

    /** Path relatif terhadap disk public (storage/app/public), mis. "UserTtd/x.png". */
    public static function pathDiskPublic(?string $nilai): ?string
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return null;
        }

        return str_contains($nilai, '/') ? $nilai : self::FOLDER . '/' . $nilai;
    }

    /** Path web relatif ("storage/UserTtd/x.png") — untuk <img src> di PDF DomPDF. */
    public static function pathWeb(?string $nilai): string
    {
        $pathDiskPublic = self::pathDiskPublic($nilai);

        return $pathDiskPublic === null ? '' : "storage/{$pathDiskPublic}";
    }

    /** URL absolut via asset() — untuk tampilan di browser. */
    public static function url(?string $nilai): string
    {
        $pathWeb = self::pathWeb($nilai);

        return $pathWeb === '' ? '' : asset($pathWeb);
    }

    /**
     * Path sistem berkas lengkap (public_path) — untuk DomPDF & file_exists().
     * TIDAK mengecek keberadaan berkas; pemanggil tetap file_exists() sendiri
     * bila perlu. Null bila nilai kosong.
     */
    public static function pathBerkas(?string $nilai): ?string
    {
        $pathWeb = self::pathWeb($nilai);

        return $pathWeb === '' ? null : public_path($pathWeb);
    }

    /**
     * Path sistem berkas TTD dari kode user (users.myuser_code), null bila user
     * tak punya TTD atau berkasnya hilang. Satu query ke tabel users.
     */
    public static function pathBerkasDariKode(?string $kode): ?string
    {
        $pathBerkas = self::pathBerkas(self::nilaiKolomDariKode($kode));

        return ($pathBerkas !== null && file_exists($pathBerkas)) ? $pathBerkas : null;
    }

    /**
     * URL gambar TTD dari kode user untuk <img> di layar (komponen ttd-petugas),
     * '' bila user tak punya TTD atau berkasnya hilang.
     */
    public static function urlDariKode(?string $kode): string
    {
        $nilai = self::nilaiKolomDariKode($kode);
        $pathBerkas = self::pathBerkas($nilai);

        return ($pathBerkas !== null && file_exists($pathBerkas)) ? self::url($nilai) : '';
    }

    /** Nilai mentah kolom myuser_ttd_image dari kode user; null bila kode kosong. */
    private static function nilaiKolomDariKode(?string $kode): ?string
    {
        if (empty($kode)) {
            return null;
        }

        return DB::table('users')->where('myuser_code', $kode)->value('myuser_ttd_image');
    }
}
