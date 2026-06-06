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
        'accdoc_id'     => '',
        'accdoc_desc'   => '',
        'accdoc_price'  => '0',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.jasa-dokter.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-jasa-dokter-actions');
        $this->dispatch('focus-accdoc-id');
    }

    #[On('master.jasa-dokter.openEdit')]
    public function openEdit(string $accdocId): void
    {
        $row = DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $accdocId;
        $this->form = [
            'accdoc_id'     => (string) $row->accdoc_id,
            'accdoc_desc'   => (string) ($row->accdoc_desc ?? ''),
            'accdoc_price'  => (string) ($row->accdoc_price ?? '0'),
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-jasa-dokter-actions');
        $this->dispatch('focus-accdoc-desc');
    }

    #[On('master.jasa-dokter.toggleActive')]
    public function toggleActive(string $accdocId): void
    {
        $cur = (string) DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status jasa dokter → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.jasa-dokter.saved');
    }

    #[On('master.jasa-dokter.requestDelete')]
    public function deleteJasaDokter(string $accdocId): void
    {
        try {
            $isUsed = DB::table('rstxn_rjaccdocs')->where('accdoc_id', $accdocId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Jasa dokter tidak bisa dihapus karena masih dipakai pada transaksi rawat jalan.');
                return;
            }

            $deleted = DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data jasa dokter tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Jasa dokter berhasil dihapus.');
            $this->dispatch('master.jasa-dokter.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Jasa dokter tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.accdoc_id'     => $this->formMode === 'create'
                ? 'required|string|max:10|regex:/^[A-Z0-9_-]+$/|unique:rsmst_accdocs,accdoc_id'
                : 'required|string',
            'form.accdoc_desc'   => 'required|string|max:50',
            'form.accdoc_price'  => 'required|numeric|min:0|max:999999999',
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.accdoc_id.required' => 'ID Jasa wajib diisi.',
            'form.accdoc_id.max'      => 'ID Jasa maksimal 10 karakter.',
            'form.accdoc_id.regex'    => 'ID Jasa hanya huruf besar / angka / "_" / "-".',
            'form.accdoc_id.unique'   => 'ID Jasa sudah digunakan.',
            'form.accdoc_desc.required' => 'Deskripsi wajib diisi.',
            'form.accdoc_desc.max'      => 'Deskripsi maksimal 50 karakter.',
            'form.accdoc_price.required' => 'Tarif wajib diisi.',
            'form.accdoc_price.numeric'  => 'Tarif harus berupa angka.',
            'form.accdoc_price.min'      => 'Tarif minimal 0.',
            'form.active_status.required' => 'Status wajib dipilih.',
            'form.active_status.in'       => 'Status tidak valid.',
        ];

        $attributes = [
            'form.accdoc_id'     => 'ID Jasa',
            'form.accdoc_desc'   => 'Deskripsi',
            'form.accdoc_price'  => 'Tarif',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'accdoc_desc'   => mb_strtoupper($this->form['accdoc_desc']),
            'accdoc_price'  => (float) $this->form['accdoc_price'],
            'active_status' => $this->form['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_accdocs')->insert([
                'accdoc_id' => mb_strtoupper($this->form['accdoc_id']),
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_accdocs')->where('accdoc_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data jasa dokter berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.jasa-dokter.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-jasa-dokter-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['accdoc_id' => '', 'accdoc_desc' => '', 'accdoc_price' => '0', 'active_status' => '1'];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-jasa-dokter-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-jasa-dokter-actions"
            event="master.jasa-dokter.saved"
            label="Jasa Dokter"
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
                                    {{ $formMode === 'edit' ? 'Ubah Jasa Dokter' : 'Tambah Jasa Dokter' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Item jasa dokter (ACCDOC) untuk billing rawat jalan.
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
                 x-on:focus-accdoc-id.window="$nextTick(() => setTimeout(() => $refs.inputAccdocId?.focus(), 150))"
                 x-on:focus-accdoc-desc.window="$nextTick(() => setTimeout(() => $refs.inputAccdocDesc?.focus(), 150))">

                <x-border-form title="Data Jasa Dokter">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Jasa" />
                                <x-text-input wire:model.live="form.accdoc_id" x-ref="inputAccdocId"
                                    maxlength="10"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.accdoc_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputAccdocDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.accdoc_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.accdoc_desc" x-ref="inputAccdocDesc"
                                    maxlength="50"
                                    :error="$errors->has('form.accdoc_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputAccdocPrice?.focus()" />
                                <x-input-error :messages="$errors->get('form.accdoc_desc')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Tarif (Rp)" />
                                <x-text-input wire:model.live="form.accdoc_price" x-ref="inputAccdocPrice"
                                    type="number" min="0" step="100"
                                    :error="$errors->has('form.accdoc_price')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Format: angka tanpa titik/koma. Contoh: 50000
                                </p>
                                <x-input-error :messages="$errors->get('form.accdoc_price')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Status" />
                                <x-select-input wire:model.live="form.active_status"
                                    :error="$errors->has('form.active_status')"
                                    class="w-full mt-1">
                                    <option value="1">AKTIF</option>
                                    <option value="0">NONAKTIF</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
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
