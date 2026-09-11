<?php

/**
 * Pengeluaran Kas TU — SKTXN_TUCASHOUTS (CO = Cash Out).
 *
 * Schema:
 *   co_no (PK), co_date, co_desc, co_nominal, co_status,
 *   tucico_id (FK → SKACC_TUCICOS where tucico_status='CO'),
 *   kasir_id  (FK → SKMST_KASIRS),
 *   cb_id     (FK → SKACC_CARABAYARS).
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public ?string $editNo = null;
    public array $renderVersions = [];

    // ── Form fields (SKTXN_TUCASHOUTS) ──
    public ?string $tucicoId = null;
    public ?string $cbId = null;
    public ?string $coDate = null;
    public ?string $coDesc = null;
    public ?int    $coNominal = null;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open Create ── */
    #[On('pengeluaran-kas.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->editNo = null;
        $this->coDate = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pengeluaran-kas-tu-actions');
        $this->dispatch('focus-co-date');
    }

    /* ── Open Edit ── */
    #[On('pengeluaran-kas.openEdit')]
    public function openEdit(string $coNo): void
    {
        $row = DB::table('sktxn_tucashouts')->where('co_no', $coNo)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        if (($row->co_status ?? '') === 'L') {
            $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah diposting, tidak bisa diedit.');
            return;
        }

        $this->resetFormFields();
        $this->formMode  = 'edit';
        $this->editNo    = (string) $row->co_no;
        $this->tucicoId  = $row->tucico_id;
        $this->cbId      = $row->cb_id;
        $this->coDate    = $row->co_date ? Carbon::parse($row->co_date)->format('d/m/Y H:i:s') : null;
        $this->coDesc    = $row->co_desc;
        $this->coNominal = (int) ($row->co_nominal ?? 0);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pengeluaran-kas-tu-actions');
        $this->dispatch('focus-co-desc');
    }

    /* ── LOV Listeners ── */
    #[On('lov.selected.tucico-co-tu')]
    public function onTucicoSelected(string $target, ?array $payload): void
    {
        $this->tucicoId = $payload['tucico_id'] ?? null;
        $this->dispatch('focus-cb-tu-co');
    }

    #[On('lov.selected.cb-co-tu')]
    public function onCaraBayarSelected(string $target, ?array $payload): void
    {
        $this->cbId = $payload['cb_id'] ?? null;
        $this->dispatch('focus-btn-save-co');
    }

    /* ── Save ── */
    public function save(): void
    {
        $this->validate([
            'tucicoId'  => 'required|string|exists:skacc_tucicos,tucico_id',
            'cbId'      => 'required|string|exists:skacc_carabayars,cb_id',
            'coDate'    => 'required|date_format:d/m/Y H:i:s',
            'coDesc'    => 'required|string|min:3|max:100',
            'coNominal' => 'required|integer|min:1',
        ], [
            'tucicoId.required'  => 'Kategori pengeluaran (TUCICO) wajib dipilih.',
            'tucicoId.exists'    => 'Kategori TUCICO tidak valid.',
            'cbId.required'      => 'Cara bayar wajib dipilih.',
            'cbId.exists'        => 'Cara bayar tidak valid.',
            'coDate.required'    => 'Tanggal wajib diisi.',
            'coDate.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
            'coDesc.required'    => 'Keterangan wajib diisi.',
            'coDesc.min'         => 'Keterangan minimal 3 karakter.',
            'coNominal.required' => 'Nominal wajib diisi.',
            'coNominal.min'      => 'Nominal minimal Rp 1.',
        ]);

        // Resolve kasir_id dari USERS.kasir_id (mapping di User Control).
        $kasirId = auth()->user()->kasir_id ?? null;
        if ($kasirId) {
            $valid = DB::table('skmst_kasirs')->where('kasir_id', $kasirId)->where('active_status', '1')->exists();
            if (!$valid) $kasirId = null;
        }
        if (!$kasirId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil kasir Anda belum di-mapping (kasir_id). Hubungi admin via User Control.');
            return;
        }

        try {
            DB::transaction(function () use ($kasirId) {
                $payload = [
                    'co_date'    => DB::raw("to_date('{$this->coDate}','dd/mm/yyyy hh24:mi:ss')"),
                    'co_desc'    => $this->coDesc,
                    'co_nominal' => $this->coNominal,
                    'co_status'  => 'L',
                    'tucico_id'  => $this->tucicoId,
                    'kasir_id'   => $kasirId,
                    'cb_id'      => $this->cbId,
                ];

                if ($this->editNo) {
                    DB::table('sktxn_tucashouts')
                        ->where('co_no', $this->editNo)
                        ->update($payload);
                } else {
                    $nextNo = (int) DB::table('sktxn_tucashouts')->max('co_no') + 1;
                    DB::table('sktxn_tucashouts')->insert(array_merge(['co_no' => $nextNo], $payload));
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Transaksi pengeluaran kas berhasil disimpan.');
            $this->closeModal();
            $this->dispatch('pengeluaran-kas.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ── Delete ── */
    #[On('pengeluaran-kas.requestDelete')]
    public function deleteFromGrid(string $coNo): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transaksi.');
            return;
        }

        try {
            $deleted = DB::table('sktxn_tucashouts')->where('co_no', $coNo)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dihapus.');
            $this->dispatch('pengeluaran-kas.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ── Close Modal ── */
    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pengeluaran-kas-tu-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['editNo', 'tucicoId', 'cbId', 'coDate', 'coDesc', 'coNominal']);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="pengeluaran-kas-tu-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $editNo]) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $editNo ? "Edit Pengeluaran Kas #{$editNo}" : 'Tambah Pengeluaran Kas Baru' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Catat pengeluaran kas (Cash-Out) di luar transaksi pelayanan klinik.
                        </p>
                        <div class="mt-3">
                            <x-badge :variant="$editNo ? 'warning' : 'success'">
                                {{ $editNo ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl"
                    x-data
                    x-on:focus-co-date.window="$nextTick(() => setTimeout(() => $refs.inputCoDate?.focus(), 150))"
                    x-on:focus-co-desc.window="$nextTick(() => setTimeout(() => $refs.inputCoDesc?.focus(), 150))"
                    x-on:focus-cb-tu-co.window="$nextTick(() => setTimeout(() => $refs.lovCbWrapper?.querySelector('input:not([disabled])')?.focus(), 150))"
                    x-on:focus-btn-save-co.window="$nextTick(() => setTimeout(() => $refs.btnSaveCo?.focus(), 150))">
                    <x-border-form title="Data Pengeluaran Kas (Cash-Out)">
                        <div class="space-y-5">

                            {{-- Tanggal --}}
                            <div>
                                <x-input-label value="Tanggal" :required="true" />
                                <x-text-input type="text" wire:model="coDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                    x-ref="inputCoDate"
                                    x-on:keydown.enter.prevent="$refs.inputCoDesc?.focus()"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('coDate')" class="mt-1" />
                            </div>

                            {{-- Keterangan --}}
                            <div>
                                <x-input-label value="Keterangan" :required="true" />
                                <x-text-input type="text" wire:model="coDesc" placeholder="Keterangan pengeluaran kas"
                                    x-ref="inputCoDesc"
                                    x-on:keydown.enter.prevent="$refs.inputCoNominal?.focus()"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('coDesc')" class="mt-1" />
                            </div>

                            {{-- Nominal (Rp) --}}
                            <div>
                                <x-input-label value="Nominal (Rp)" :required="true" />
                                <x-text-input-number wire:model="coNominal" x-ref="inputCoNominal" />
                                <x-input-error :messages="$errors->get('coNominal')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Kategori TUCICO (CO) --}}
                                <div>
                                    <livewire:lov.tucico.lov-tucico
                                        target="tucico-co-tu"
                                        label="Kategori Pengeluaran (CO)"
                                        placeholder="Cari kategori CO..."
                                        filterStatus="CO"
                                        :initialTucicoId="$tucicoId"
                                        wire:key="lov-tucico-co-{{ $editNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('tucicoId')" class="mt-1" />
                                </div>

                                {{-- Cara Bayar --}}
                                <div x-ref="lovCbWrapper">
                                    <livewire:lov.cara-bayar.lov-cara-bayar
                                        target="cb-co-tu"
                                        label="Cara Bayar"
                                        :initialCbId="$cbId"
                                        wire:key="lov-cb-co-{{ $editNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('cbId')" class="mt-1" />
                                </div>
                            </div>

                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Status transaksi otomatis <strong>Posted (L)</strong> saat disimpan.
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" x-ref="btnSaveCo">
                            <span wire:loading.remove>Simpan & Posting</span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
