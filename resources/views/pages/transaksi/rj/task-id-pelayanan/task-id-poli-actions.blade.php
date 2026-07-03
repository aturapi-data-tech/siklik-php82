<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

/**
 * Aksi Task ID tahap POLI RJ (TaskId4 Masuk Poli, TaskId5 Panggil Antrian,
 * Get TaskId Antrean) dalam SATU komponen per baris.
 *
 * Sebelumnya 3 komponen Livewire terpisah di-mount per baris di pelayanan-rj &
 * daftar-rj → 3× jumlah komponen di list (pemicu payload berat / TooManyComponents).
 * Digabung jadi 1 komponen/baris: logika tiap aksi tetap identik dengan versi lama,
 * spinner per-tombol tetap terisolasi via wire:target masing-masing method.
 *
 * Catatan: integrasi BPJS antrean (push AntrianTrait) belum diwire di siklik —
 * perilaku dipertahankan apa adanya (get-task-id = placeholder).
 */
new class extends Component {
    use EmrRJTrait;

    public ?int $rjNo = null;
    // #[Reactive] → tombol ikut redup saat parent re-render (refresh-after-rj.saved), tanpa remount.
    #[Reactive]
    public bool $isDone4 = false;
    #[Reactive]
    public bool $isDone5 = false;

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

<div class="flex space-x-1">
    {{-- TaskId4 (Masuk Poli) --}}
    <x-primary-button wire:click="prosesTaskId4" wire:loading.attr="disabled" wire:target="prosesTaskId4"
        class="!px-2 !py-1 text-xs {{ $isDone4 ? '!opacity-60' : '' }}"
        title="{{ $isDone4 ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mencatat TaskId4 (Masuk Poli)' }}">
        <span wire:loading.remove wire:target="prosesTaskId4">TaskId4</span>
        <span wire:loading wire:target="prosesTaskId4"><x-loading /></span>
    </x-primary-button>

    {{-- TaskId5 (Panggil Antrian) --}}
    <x-primary-button wire:click="prosesTaskId5" wire:loading.attr="disabled" wire:target="prosesTaskId5"
        class="!px-2 !py-1 text-xs {{ $isDone5 ? '!opacity-60' : '' }}"
        title="{{ $isDone5 ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mencatat TaskId5 (Panggil Antrian)' }}">
        <span wire:loading.remove wire:target="prosesTaskId5">TaskId5</span>
        <span wire:loading wire:target="prosesTaskId5"><x-loading /></span>
    </x-primary-button>

    {{-- Get TaskId Antrean --}}
    <x-primary-button wire:click="prosesTaskidAntrean" wire:loading.attr="disabled" wire:target="prosesTaskidAntrean"
        class="!px-2 !py-1 text-xs" title="Klik untuk mengambil TaskId Antrean">
        <span wire:loading.remove wire:target="prosesTaskidAntrean">TaskId Antrean</span>
        <span wire:loading wire:target="prosesTaskidAntrean"><x-loading /></span>
    </x-primary-button>
</div>
