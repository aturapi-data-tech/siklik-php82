<?php

namespace App\Console\Commands;

use App\Http\Traits\SATUSEHAT\SnomedTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Validasi isi cache skmst_snomed_codes terhadap edisi SNOMED yang di-pin
 * (config txfhir.snomed_version) dan hapus kode yang tak dikenal edisi itu.
 *
 * Latar: LOV SNOMED sempat mencari tanpa pin versi, sehingga konsep baru
 * (mis. 1306548008, effective 2024-04-01) masuk cache padahal ditolak
 * SATUSEHAT (OperationOutcome RuleNumber 10003). Setelah pin dipasang,
 * kode lama di cache tetap bisa terpilih dokter — command ini pembersihnya.
 */
class BersihkanCacheSnomed extends Command
{
    use SnomedTrait;

    protected $signature = 'snomed:bersihkan-cache
        {--dry-run : Hanya tampilkan kode yang akan dihapus, jangan hapus}';

    protected $description = 'Hapus kode skmst_snomed_codes yang tak dikenal edisi SNOMED di-pin (txfhir.snomed_version)';

    /** Kode mapan untuk sanity check koneksi (Myocardial infarction, ada sejak rilis awal). */
    private const KODE_UJI_KONEKSI = '22298006';

    public function handle(): int
    {
        $this->initializeTxFhir();

        if (empty($this->snomedVersion)) {
            $this->error('txfhir.snomed_version kosong — tidak ada edisi acuan, tidak ada yang dibersihkan.');
            return self::FAILURE;
        }

        $this->info("Edisi acuan : {$this->snomedVersion}");
        $this->info("Server      : {$this->txFhirBaseUrl}");

        // Sanity check: kalau kode mapan saja tak ditemukan, berarti server/versi
        // bermasalah — jangan lanjut, bisa-bisa seluruh cache terhapus.
        if ($this->snomedCodeExists(self::KODE_UJI_KONEKSI) !== true) {
            $this->error('Sanity check gagal: kode uji ' . self::KODE_UJI_KONEKSI . ' tak terverifikasi. Server tak sehat atau versi tak di-host — dibatalkan.');
            return self::FAILURE;
        }

        $rows = DB::table('skmst_snomed_codes')
            ->select('snomed_code', 'display_en', 'display_id')
            ->orderBy('snomed_code')
            ->get();

        $this->info('Total kode di cache: ' . $rows->count());

        $dryRun = (bool) $this->option('dry-run');
        $jumlahHapus = 0;
        $jumlahGagalCek = 0;

        foreach ($rows as $row) {
            $kode = (string) $row->snomed_code;
            $ada = $this->snomedCodeExists($kode);

            if ($ada === true) {
                continue;
            }

            $tampilan = $row->display_id ?: $row->display_en;

            if ($ada === null) {
                $jumlahGagalCek++;
                $this->warn("  ? {$kode} {$tampilan} — tak bisa dipastikan (server error), dilewati");
                continue;
            }

            $jumlahHapus++;
            if ($dryRun) {
                $this->line("  x {$kode} {$tampilan} — TIDAK ADA di edisi acuan (dry-run, tidak dihapus)");
            } else {
                DB::table('skmst_snomed_codes')->where('snomed_code', $kode)->delete();
                $this->line("  x {$kode} {$tampilan} — dihapus");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Akan dihapus' : 'Dihapus') . ": {$jumlahHapus} kode" .
            ($jumlahGagalCek > 0 ? " | gagal dicek: {$jumlahGagalCek} (jalankan ulang nanti)" : ''));

        return self::SUCCESS;
    }
}
