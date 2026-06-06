<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'klaim_id'     => '',
        'klaim_desc'   => '',
        'klaim_status' => 'AKTIF',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.klaim.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-klaim-actions');
        $this->dispatch('focus-klaim-id');
    }

    #[On('master.klaim.openEdit')]
    public function openEdit(string $klaimId): void
    {
        $row = DB::table('rsmst_klaimtypes')->where('klaim_id', $klaimId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $klaimId;
        $this->form = [
            'klaim_id'     => (string) $row->klaim_id,
            'klaim_desc'   => (string) ($row->klaim_desc ?? ''),
            'klaim_status' => (string) ($row->klaim_status ?? 'AKTIF'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-klaim-actions');
        $this->dispatch('focus-klaim-desc');
    }

    #[On('master.klaim.requestDelete')]
    public function deleteKlaim(string $klaimId): void
    {
        try {
            $isUsed = DB::table('rstxn_rjhdrs')->where('klaim_id', $klaimId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Tipe klaim tidak bisa dihapus karena masih dipakai pada transaksi rawat jalan.');
                return;
            }

            $deleted = DB::table('rsmst_klaimtypes')->where('klaim_id', $klaimId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data tipe klaim tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Tipe klaim berhasil dihapus.');
            $this->dispatch('master.klaim.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Tipe klaim tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.klaim_id'     => $this->formMode === 'create'
                ? 'required|string|max:5|regex:/^[A-Z0-9]+$/|unique:rsmst_klaimtypes,klaim_id'
                : 'required|string',
            'form.klaim_desc'   => 'required|string|max:50',
            'form.klaim_status' => 'required|string|max:15',
        ];

        $messages = [
            'form.klaim_id.required' => 'ID Klaim wajib diisi.',
            'form.klaim_id.max'      => 'ID Klaim maksimal 5 karakter.',
            'form.klaim_id.regex'    => 'ID Klaim hanya boleh huruf besar/angka.',
            'form.klaim_id.unique'   => 'ID Klaim sudah digunakan.',
            'form.klaim_desc.required' => 'Deskripsi wajib diisi.',
            'form.klaim_desc.max'      => 'Deskripsi maksimal 50 karakter.',
            'form.klaim_status.required' => 'Status wajib dipilih.',
        ];

        $attributes = [
            'form.klaim_id'     => 'ID Klaim',
            'form.klaim_desc'   => 'Deskripsi',
            'form.klaim_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'klaim_desc'   => mb_strtoupper($this->form['klaim_desc']),
            'klaim_status' => mb_strtoupper($this->form['klaim_status']),
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_klaimtypes')->insert([
                'klaim_id' => mb_strtoupper($this->form['klaim_id']),
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_klaimtypes')->where('klaim_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data tipe klaim berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.klaim.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-klaim-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['klaim_id' => '', 'klaim_desc' => '', 'klaim_status' => 'AKTIF'];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-klaim-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-klaim-actions"
            event="master.klaim.saved"
            label="Tipe Klaim"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                     style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Tipe Klaim' : 'Tambah Tipe Klaim' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Tipe klaim untuk pendaftaran pasien (BPJS / Umum / Asuransi).
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-klaim-id.window="$nextTick(() => setTimeout(() => $refs.inputKlaimId?.focus(), 150))"
                 x-on:focus-klaim-desc.window="$nextTick(() => setTimeout(() => $refs.inputKlaimDesc?.focus(), 150))">

                <x-border-form title="Data Tipe Klaim">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Klaim" />
                                <x-text-input wire:model.live="form.klaim_id" x-ref="inputKlaimId"
                                    maxlength="5"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.klaim_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputKlaimDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.klaim_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.klaim_desc" x-ref="inputKlaimDesc"
                                    maxlength="50"
                                    :error="$errors->has('form.klaim_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputKlaimStatus?.focus()" />
                                <x-input-error :messages="$errors->get('form.klaim_desc')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Status" />
                                <x-select-input wire:model.live="form.klaim_status" x-ref="inputKlaimStatus"
                                    :error="$errors->has('form.klaim_status')"
                                    class="w-full mt-1">
                                    <option value="AKTIF">AKTIF</option>
                                    <option value="NONAKTIF">NONAKTIF</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.klaim_status')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk pindah field</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
