<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

/**
 * KOMPONEN AKSI Task ID tahap POLI RJ (TaskId4 Masuk Poli, TaskId5 Panggil
 * Antrian, Get TaskId Antrean) — berisi SEMUA fungsi/logika.
 *
 * Arsitektur "cetak-pattern": komponen ini di-mount SEKALI sebagai sibling di
 * pelayanan-rj & daftar-rj (bukan per baris). Tombol tiap baris ada di list dan
 * memicu komponen ini via
 * wire:click="$dispatch('task-id-poli-proses-rj', { rjNo, aksi })" (aksi Livewire,
 * bukan Alpine).
 *
 * Sebelumnya komponen ini di-mount per baris dengan prop #[Reactive] → saat list
 * re-render pasca 'refresh-after-rj.saved', semua child reactive ikut satu batch →
 * TooManyComponents saat baris banyak. Dengan mount sekali, batch tak lagi skala
 * jumlah baris. Logika tiap aksi IDENTIK versi lama.
 *
 * Catatan: integrasi BPJS antrean belum diwire di siklik (get-task-id = placeholder).
 */
new class extends Component {
    use EmrRJTrait;

    public ?int $rjNo = null;

    /* ===============================
     | ROUTER — dipicu tombol baris via wire:click $dispatch
     | Detail event: { rjNo, aksi } dengan aksi ∈ {'4','5','antrean'}.
     =============================== */
    #[On('task-id-poli-proses-rj')]
    public function proses(int $rjNo, string $aksi): void
    {
        $this->rjNo = $rjNo;

        match ($aksi) {
            '4'       => $this->prosesTaskId4(),
            '5'       => $this->prosesTaskId5(),
            'antrean' => $this->prosesTaskidAntrean(),
            default   => null,
        };
    }

    /* ===============================
     | PROSES TASK ID 4 (Masuk Poli)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId3 prerequisite
     | 2. Set taskId4 timestamp jika belum ada
     | 3. lockRJRow + update waktu_masuk_poli + patch taskIdPelayanan — atomik
    =============================== */
    public function prosesTaskId4(): void
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

            // Validasi prerequisite: taskId3 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId3'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId3 (Masuk Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            if (!empty($data['taskIdPelayanan']['taskId4'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId4 sudah tercatat: {$data['taskIdPelayanan']['taskId4']}", title: 'Info');
            }

            if (empty($data['taskIdPelayanan']['taskId4'])) {
                $data['taskIdPelayanan']['taskId4'] = $waktuSekarang;
            }

            // Simpan ke DB — lock + update waktu_masuk_poli + patch taskIdPelayanan atomik
            DB::transaction(function () use ($data, $waktuSekarang) {
                $this->lockRJRow($this->rjNo);

                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_masuk_poli' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk poli pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-rj.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | PROSES TASK ID 5 (Panggil Antrian)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId4 prerequisite
     | 2. Set taskId5 timestamp jika belum ada
     | 3. lockRJRow + patch hanya key taskIdPelayanan — atomik
    =============================== */
    public function prosesTaskId5(): void
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

            // Validasi prerequisite: taskId4 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId4'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId4 (Masuk Poli) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            $data['taskIdPelayanan'] ??= [];

            if (!empty($data['taskIdPelayanan']['taskId5'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId5 sudah tercatat: {$data['taskIdPelayanan']['taskId5']}", title: 'Info');
            }

            if (empty($data['taskIdPelayanan']['taskId5'])) {
                $data['taskIdPelayanan']['taskId5'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            }

            // Simpan ke DB — lock + patch hanya key taskIdPelayanan
            DB::transaction(function () use ($data) {
                $this->lockRJRow($this->rjNo);

                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);
            });

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

{{-- Indikator proses global (host tak punya tombol sendiri — tombol ada di baris list). --}}
<div wire:key="task-id-poli-actions-rj-host">
    <div wire:loading wire:target="proses, prosesTaskId4, prosesTaskId5, prosesTaskidAntrean"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-blue-600 rounded-xl shadow-lg dark:bg-blue-500">
        <x-loading />
        Memproses Task ID…
    </div>
</div>
