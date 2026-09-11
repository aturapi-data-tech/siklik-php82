<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

/**
 * KOMPONEN AKSI Task ID apotek RJ (TaskId6 Masuk Apotek, TaskId7 Keluar Apotek,
 * Get TaskId Antrean) — berisi SEMUA fungsi/logika. Gabungan dari task-id-6,
 * task-id-7, dan get-task-id lama.
 *
 * Arsitektur "cetak-pattern": komponen ini di-mount SEKALI sebagai sibling di
 * antrian-apotek-rj (bukan per baris). Tombol tiap baris ada di antrian-apotek-rj
 * dan memicu komponen ini via
 * wire:click="$dispatch('task-id-apotek-proses-rj', { rjNo, aksi })" (aksi Livewire).
 * Nol komponen Livewire per baris → batch pasca 'refresh-after-rj.saved' tak skala
 * jumlah baris.
 *
 * ⚠️ Logika tiap aksi IDENTIK versi lama (penomoran noAntrianApotek anti-race di
 *    dalam lock). Integrasi BPJS antrean belum diwire di siklik (antrean = placeholder).
 */
new class extends Component {
    use EmrRJTrait;

    public ?int $rjNo = null;

    /* ===============================
     | ROUTER — dipicu tombol baris via wire:click $dispatch
     | Detail event: { rjNo, aksi } dengan aksi ∈ {'6','7','antrean'}.
     =============================== */
    #[On('task-id-apotek-proses-rj')]
    public function proses(int $rjNo, string $aksi): void
    {
        $this->rjNo = $rjNo;

        match ($aksi) {
            '6'       => $this->prosesTaskId6(),
            '7'       => $this->prosesTaskId7(),
            'antrean' => $this->prosesTaskidAntrean(),
            default   => null,
        };
    }

    /* ===============================
     | PROSES TASK ID 6 (Masuk Apotek)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId5 prerequisite
     | 2. Inisialisasi taskIdPelayanan
     | 3. lockRJRow + hitung noAntrianApotek + update waktu_masuk_apt
     |    + patch taskIdPelayanan & noAntrianApotek — ATOMIK
     |
     | ⚠️  noAntrianApotek dihitung DI DALAM transaksi + lock untuk mencegah
     |     race condition (dua pasien selesai bersamaan → nomor antrian dobel)
    =============================== */
    public function prosesTaskId6(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // Validasi prerequisite: taskId5 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId5 (Panggil Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            if (empty($data['taskIdPelayanan']['taskId6'])) {
                $data['taskIdPelayanan']['taskId6'] = $waktuSekarang;
            }

            // Simpan ke DB — ATOMIK:
            //   - lock row
            //   - hitung noAntrianApotek di dalam lock (cegah race condition nomor antrian dobel)
            //   - update waktu_masuk_apt
            //   - patch taskIdPelayanan + noAntrianApotek
            DB::transaction(function () use ($data, $waktuSekarang) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                // Hitung noAntrianApotek di dalam lock — cegah nomor antrian dobel
                if (empty($existingData['noAntrianApotek'])) {
                    $eresepRacikanCount = collect($existingData['eresepRacikan'] ?? [])->count();
                    $jenisResep = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';

                    $refDate = Carbon::now(config('app.timezone'))->format('d/m/Y');
                    $noAntrian = DB::table('sktxn_rjhdrs')
                            ->select('datadaftarpolirj_json')
                            ->where('rj_status', '!=', 'F')
                            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)
                            ->lockForUpdate() // lock tabel untuk cegah race condition
                            ->get()
                            ->filter(fn($item) => isset((json_decode($item->datadaftarpolirj_json, true) ?: [])['noAntrianApotek']))
                            ->count() + 1;

                    $existingData['noAntrianApotek'] = [
                        'noAntrian' => $noAntrian,
                        'jenisResep' => $jenisResep,
                    ];
                }

                // Patch taskIdPelayanan
                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];

                // Update waktu_masuk_apt di header — atomik dengan JSON update
                DB::table('sktxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_masuk_apt' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk apotek pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-rj.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | PROSES TASK ID 7 (Keluar Apotek)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId6 prerequisite
     | 2. Set taskId7 timestamp jika belum ada
     | 3. lockRJRow + update waktu_selesai_pelayanan + patch taskIdPelayanan — ATOMIK
    =============================== */
    public function prosesTaskId7(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // Validasi prerequisite: taskId6 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId6'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId6 (Masuk Apotek) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            if (!empty($data['taskIdPelayanan']['taskId7'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId7 sudah tercatat: {$data['taskIdPelayanan']['taskId7']}", title: 'Info');
            }

            if (empty($data['taskIdPelayanan']['taskId7'])) {
                $data['taskIdPelayanan']['taskId7'] = $waktuSekarang;
            }

            // Simpan ke DB — lock + update waktu_selesai_pelayanan + patch taskIdPelayanan atomik
            DB::transaction(function () use ($data, $waktuSekarang) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock — patch hanya key taskIdPelayanan
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                // Update waktu_selesai_pelayanan di header — atomik dengan JSON update
                DB::table('sktxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_selesai_pelayanan' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil keluar apotek pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-rj.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | GET TASK ID ANTREAN (placeholder — BPJS antrean belum diwire di siklik)
    =============================== */
    public function prosesTaskidAntrean(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            $data = $this->findDataRJ($this->rjNo);
            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            $this->dispatch('refresh-after-rj.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }
};
?>

{{-- Indikator proses global (host tak punya tombol sendiri — tombol ada di baris antrian-apotek-rj). --}}
<div wire:key="task-id-apotek-actions-rj-host">
    <div wire:loading wire:target="proses, prosesTaskId6, prosesTaskId7, prosesTaskidAntrean"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-blue-600 rounded-xl shadow-lg dark:bg-blue-500">
        <x-loading />
        Memproses Task ID Apotek…
    </div>
</div>
