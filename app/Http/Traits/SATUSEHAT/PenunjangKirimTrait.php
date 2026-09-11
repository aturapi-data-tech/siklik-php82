<?php

namespace App\Http\Traits\SATUSEHAT;

/**
 * Indeks kiriman penunjang PER ORDER — supaya kiriman ulang menyusul yang bolong,
 * bukan mengulang semuanya atau memblokir semuanya.
 *
 * MASALAHNYA. Sender lab & radiologi menyimpan hasil kiriman sebagai array DATAR
 * (`radServiceRequestIds`, `labDiagnosticReportIds`, …) — daftar UUID tanpa keterangan
 * order mana punya siapa. Begitu satu order gagal di tengah (SR terbentuk, DR belum),
 * tak ada cara tahu order mana yang bolong. Guard lama cuma bisa dua sikap, dua-duanya
 * salah: lolos → SR di-POST ulang dengan identifier yang sama → ditolak duplikat
 * (RuleNumber 20002) dan macet selamanya; atau tolak semua → DR-nya tak pernah
 * tersusul, data di SATUSEHAT tinggal separuh.
 *
 * CARANYA. Tiap order sudah punya identifier stabil yang dipakai saat POST
 * (radiologi `rad-{rjNo}-{rad_dtl}`, lab `{rjNo}-{checkup_no}`). Identifier itu jadi
 * kunci indeks baru di samping array datar:
 *
 *     satusehat: {
 *       radServiceRequestIds:   [...],            // TETAP — bentuk datar yang sudah
 *       radDiagnosticReportIds: [...],            //   dipakai indikator & laporan
 *       radKirim: { "rad-23977-2": { sr: uuid, obs: uuid, dr: uuid } }   // BARU
 *     }
 *
 * Array datar sengaja dipertahankan apa adanya: hitungan "berapa laporan terkirim" di
 * kartu dan indikator daftar RJ membacanya, dan ada pembaca yang mencocokkan string
 * mentah `"radServiceRequestIds":["` ke CLOB — mengubah bentuknya memutus mereka
 * diam-diam. Di siklik TIDAK ada ImagingStudy (bagian 'is' milik sirus): radiologi
 * klinik pratama dikirim tanpa PACS/Orthanc.
 *
 * RECORD LAMA tak punya indeks. Id-nya dipulihkan sekali lewat pencarian identifier ke
 * SATUSEHAT — identifier-nya unik per order, jadi hasilnya eksak. Gagal atau tak ketemu
 * diperlakukan aman: order dianggap sudah tuntas, persis perilaku guard sekarang.
 */
trait PenunjangKirimTrait
{
    use SatuSehatTrait;

    /** Indeks per-order dari node satusehat. Selalu array, walau nodenya belum ada. */
    protected function indeksKirim(array $satuSehat, string $kunciNode): array
    {
        $indeks = $satuSehat[$kunciNode] ?? [];

        return is_array($indeks) ? $indeks : [];
    }

    /** Satu order tuntas bila SEMUA bagian wajibnya sudah punya id. */
    protected function orderTuntas(array $indeks, string $kunciOrder, array $bagianWajib): bool
    {
        foreach ($bagianWajib as $bagian) {
            if (blank($indeks[$kunciOrder][$bagian] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Id satu bagian (sr/sp/dr) — null bila belum terbentuk. */
    protected function idKirim(array $indeks, string $kunciOrder, string $bagian): ?string
    {
        $id = $indeks[$kunciOrder][$bagian] ?? null;

        return filled($id) ? (string) $id : null;
    }

    /** Daftar id Observation satu order — kosong bila belum ada. */
    protected function daftarIdKirim(array $indeks, string $kunciOrder, string $bagian): array
    {
        $daftar = $indeks[$kunciOrder][$bagian] ?? [];

        return is_array($daftar) ? array_values(array_filter($daftar)) : [];
    }

    /** Catat id yang baru terbentuk. Nilai kosong diabaikan supaya indeks tak terisi null. */
    protected function catatKirim(array &$indeks, string $kunciOrder, string $bagian, $id): void
    {
        if (blank($id)) {
            return;
        }

        $indeks[$kunciOrder][$bagian] = is_array($id) ? array_values($id) : (string) $id;
    }

    /**
     * Record LAMA — pernah dikirim (array datar terisi) tapi belum punya indeks per-order.
     * Hanya record begini yang perlu dipulihkan; yang belum pernah dikirim sama sekali
     * tak ada yang perlu dicari.
     */
    protected function perluPulihIndeks(array $satuSehat, string $kunciNode, array $kunciDatarList): bool
    {
        if (filled($satuSehat[$kunciNode] ?? null)) {
            return false;
        }

        foreach ($kunciDatarList as $kunciDatar) {
            if (filled($satuSehat[$kunciDatar] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cari id resource yang SUDAH ada di SATUSEHAT lewat identifier.
     *
     * Dipakai memulihkan record lama, dan sebagai jaring pengaman sebelum POST: kalau
     * resource-nya ternyata sudah ada, pakai id yang itu daripada kena tolak duplikat.
     * Apa pun yang meleset (jaringan, bundle kosong, bentuk balikan tak terduga)
     * mengembalikan null — pemanggil yang memutuskan sikap amannya.
     */
    protected function cariIdLewatIdentifier(string $resource, string $system, string $value): ?string
    {
        if (blank($system) || blank($value)) {
            return null;
        }

        try {
            $hasil = $this->makeRequest('get', "/{$resource}?identifier=" . rawurlencode("{$system}|{$value}"));
            $id    = $hasil['entry'][0]['resource']['id'] ?? null;

            return filled($id) ? (string) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
