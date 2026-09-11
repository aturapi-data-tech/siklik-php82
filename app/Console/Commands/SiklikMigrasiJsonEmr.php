<?php

namespace App\Console\Commands;

use App\Support\OracleLob;
use App\Support\PenilaianLegacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Migrasi isi JSON CLOB SKTXN_RJHDRS.DATADAFTARPOLIRJ_JSON dari bentuk legacy siklik-lite ke bentuk siklik-php82.
 *
 * Node yang ditangani:
 *   penilaian  : resikoJatuh/nyeri objek → list entri (salinan legacy disimpan untuk rollback);
 *                dekubitus/statusPediatrik/fisik/diagnosis objek kosong → [] (berisi → dibiarkan & dilaporkan)
 *   eresep     : list berlubang (tersimpan sebagai objek {0,3,4}) → array_values
 *   diagnosis  : idem (top-level)
 *
 * Default = UJI (tanpa menulis). --jalankan menulis (lock baris, baca ulang, tulis); --rollback memulihkan.
 * Hasil eksekusi nyata dicatat ke docs/migrasi-skema-data.md §3 (di antara penanda LOG-MIGRASI-JSON).
 *
 *   php artisan siklik:migrasi-json-emr                 # uji, statistik saja
 *   php artisan siklik:migrasi-json-emr --jalankan      # tulis
 *   php artisan siklik:migrasi-json-emr --rjno=1923     # satu kunjungan
 *   php artisan siklik:migrasi-json-emr --rollback --jalankan
 */
class SiklikMigrasiJsonEmr extends Command
{
    protected $signature = 'siklik:migrasi-json-emr
        {--jalankan : Tulis ke DB (tanpa ini hanya uji)}
        {--rollback : Pulihkan salinan legacy (penilaian) dan hapus penanda migrasi}
        {--rjno= : Batasi satu rj_no}
        {--chunk=250 : Baris per chunk}
        {--tanpa-log : Jangan tulis log ke docs/migrasi-skema-data.md}';

    protected $description = 'Migrasi node JSON EMR RJ (penilaian legacy → list, eresep/diagnosis berlubang → reindex) — uji dulu, --jalankan untuk menulis';

    public function handle(): int
    {
        $tulis = (bool) $this->option('jalankan');
        $rollback = (bool) $this->option('rollback');
        $this->info(($tulis ? 'MODE TULIS' : 'MODE UJI (tanpa menulis)').($rollback ? ' — ROLLBACK' : ''));

        $q = DB::table('sktxn_rjhdrs')->select('rj_no', DB::raw("to_char(rj_date,'DD/MM/YYYY HH24:MI:SS') rj_date_teks"))
            ->whereNotNull('datadaftarpolirj_json');
        if ($this->option('rjno')) {
            $q->where('rj_no', $this->option('rjno'));
        } elseif ($rollback) {
            $q->whereRaw("INSTR(datadaftarpolirj_json, '\"migrasiPenilaian\"') > 0");
        } else {
            $q->whereRaw("INSTR(datadaftarpolirj_json, '\"penilaian\"') > 0 or INSTR(datadaftarpolirj_json, '\"eresep\":{') > 0 or INSTR(datadaftarpolirj_json, '\"diagnosis\":{') > 0");
        }
        $rjNos = $q->orderBy('rj_no')->pluck('rj_no')->all();
        $this->info('Kandidat: '.count($rjNos).' kunjungan');

        $stat = ['diproses' => 0, 'diubah' => 0, 'rusak' => 0, 'penilaianLegacy' => 0, 'rjKonversi' => 0, 'nyeriKonversi' => 0,
            'eresepReindex' => 0, 'diagnosisReindex' => 0, 'berisiDibiarkan' => 0, 'rollback' => 0];
        $contoh = [];

        foreach (array_chunk($rjNos, (int) $this->option('chunk')) as $kelompok) {
            foreach ($kelompok as $rjNo) {
                $stat['diproses']++;
                // Closure biasa dengan use-by-reference: arrow fn menangkap $stat/$contoh BY VALUE sehingga hitungannya hilang.
                $mutasi = function (array $data, string $rjDate) use ($rollback, $rjNo, &$stat, &$contoh) {
                    return $rollback ? $this->rollbackData($data, $stat) : $this->migrasiData($data, $rjDate, $stat, $contoh, (string) $rjNo);
                };
                try {
                    if ($tulis) {
                        DB::transaction(function () use ($rjNo, $mutasi, &$stat) {
                            $row = DB::table('sktxn_rjhdrs')->where('rj_no', $rjNo)->lockForUpdate()
                                ->select('datadaftarpolirj_json', DB::raw("to_char(rj_date,'DD/MM/YYYY HH24:MI:SS') rj_date_teks"))->first();
                            $data = $this->decode($row->datadaftarpolirj_json, (string) $rjNo);
                            if ($data === null) {
                                $stat['rusak']++;

                                return;
                            }
                            [$baru, $berubah] = $mutasi($data, (string) $row->rj_date_teks);
                            if ($berubah) {
                                DB::table('sktxn_rjhdrs')->where('rj_no', $rjNo)->update([
                                    'datadaftarpolirj_json' => json_encode($baru, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                                ]);
                                $stat['diubah']++;
                            }
                        });
                    } else {
                        $row = DB::table('sktxn_rjhdrs')->where('rj_no', $rjNo)
                            ->select('datadaftarpolirj_json', DB::raw("to_char(rj_date,'DD/MM/YYYY HH24:MI:SS') rj_date_teks"))->first();
                        $data = $this->decode($row->datadaftarpolirj_json, (string) $rjNo);
                        if ($data === null) {
                            $stat['rusak']++;

                            continue;
                        }
                        [, $berubah] = $mutasi($data, (string) $row->rj_date_teks);
                        if ($berubah) {
                            $stat['diubah']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $stat['rusak']++;
                    $this->error("rj_no $rjNo: ".substr($e->getMessage(), 0, 160));
                }
            }
            $this->output->write('.');
        }
        $this->newLine();

        $this->table(['Ukuran', 'Jumlah'], array_map(fn ($k, $v) => [$k, $v], array_keys($stat), $stat));
        if ($contoh !== []) {
            $this->line('Contoh konversi berisi:');
            foreach ($contoh as $c) {
                $this->line('  '.$c);
            }
        }

        if ($tulis && ! $this->option('tanpa-log')) {
            $this->tulisLog($stat, $rollback);
        }

        return self::SUCCESS;
    }

    /** @return array{0: array, 1: bool} */
    private function migrasiData(array $data, string $rjDate, array &$stat, array &$contoh, string $rjNo): array
    {
        $berubah = false;

        // 1. penilaian legacy
        $penilaian = $data['penilaian'] ?? null;
        if (is_array($penilaian) && PenilaianLegacy::adalahLegacy($penilaian)) {
            $stat['penilaianLegacy']++;
            $tgl = trim((string) ($data['rjDate'] ?? '')) ?: (trim((string) ($data['userLogs'][0]['userLogDate'] ?? '')) ?: $rjDate);
            $petugas = trim((string) ($data['userLogs'][0]['userLog'] ?? '')) ?: 'Migrasi Data';
            $hasil = PenilaianLegacy::konversi($penilaian, ['tgl' => $tgl, 'petugas' => $petugas]);
            $data['penilaian'] = $hasil['penilaian'];
            $berubah = true;
            $lap = $hasil['laporan'];
            if (str_starts_with($lap['resikoJatuh'] ?? '', 'dikonversi')) {
                $stat['rjKonversi']++;
                $contoh[] = "rj_no $rjNo: resikoJatuh {$lap['resikoJatuh']}".(isset($lap['resikoJatuhCatatan']) ? " [{$lap['resikoJatuhCatatan']}]" : '');
            }
            if (str_starts_with($lap['nyeri'] ?? '', 'dikonversi')) {
                $stat['nyeriKonversi']++;
                $contoh[] = "rj_no $rjNo: nyeri {$lap['nyeri']}";
            }
            foreach ($lap as $k => $v) {
                if (str_starts_with($v, 'BERISI')) {
                    $stat['berisiDibiarkan']++;
                    $contoh[] = "rj_no $rjNo: $k $v";
                }
            }
        }

        // 2. list berlubang → reindex
        foreach (['eresep' => 'eresepReindex', 'diagnosis' => 'diagnosisReindex'] as $k => $ukuran) {
            if (isset($data[$k]) && is_array($data[$k]) && $data[$k] !== [] && ! array_is_list($data[$k])) {
                $data[$k] = array_values($data[$k]);
                $stat[$ukuran]++;
                $berubah = true;
            }
        }

        return [$data, $berubah];
    }

    /** @return array{0: array, 1: bool} */
    private function rollbackData(array $data, array &$stat): array
    {
        if (! isset($data['penilaian']['migrasiPenilaian'])) {
            return [$data, false];
        }
        $data['penilaian'] = PenilaianLegacy::rollback($data['penilaian']);
        $stat['rollback']++;

        return [$data, true];
    }

    private function decode(mixed $raw, string $rjNo): ?array
    {
        $teks = OracleLob::read($raw, 'sktxn_rjhdrs', 'rj_no', $rjNo, 'datadaftarpolirj_json');
        if ($teks === null || trim((string) $teks) === '') {
            return null;
        }
        $data = json_decode((string) $teks, true);

        return is_array($data) ? $data : null;
    }

    private function tulisLog(array $stat, bool $rollback): void
    {
        $berkas = base_path('docs/migrasi-skema-data.md');
        if (! File::exists($berkas)) {
            return;
        }
        $isi = File::get($berkas);
        $awal = '<!-- LOG-MIGRASI-JSON:awal -->';
        $akhir = '<!-- LOG-MIGRASI-JSON:akhir -->';
        $i = strpos($isi, $awal);
        $j = strpos($isi, $akhir);
        if ($i === false || $j === false) {
            return;
        }
        $lama = trim(substr($isi, $i + strlen($awal), $j - $i - strlen($awal)));
        if (str_starts_with($lama, '_(belum ada')) {
            $lama = "| Tgl | Host DB | Mode | Diproses | Diubah | RJ konversi | Nyeri konversi | eresep reindex | diagnosis reindex | Berisi dibiarkan | Rusak |\n|---|---|---|---|---|---|---|---|---|---|---|";
        }
        $baris = sprintf('| %s | %s | %s | %d | %d | %d | %d | %d | %d | %d | %d |', now()->format('Y-m-d H:i'), config('database.connections.'.config('database.default').'.host'),
            $rollback ? 'ROLLBACK' : 'migrasi v'.PenilaianLegacy::VERSI, $stat['diproses'], $stat['diubah'], $stat['rjKonversi'], $stat['nyeriKonversi'],
            $stat['eresepReindex'], $stat['diagnosisReindex'], $stat['berisiDibiarkan'], $stat['rusak']);
        $isi = substr($isi, 0, $i + strlen($awal))."\n".$lama."\n".$baris."\n".substr($isi, $j);
        File::put($berkas, $isi);
        $this->info('Log ditulis ke docs/migrasi-skema-data.md');
    }
}
