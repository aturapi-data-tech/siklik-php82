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
        'kasir_id'        => '',
        'kasir_name'      => '',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.kasir.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kasir-actions');
        $this->dispatch('focus-kasir-id');
    }

    #[On('master.kasir.openEdit')]
    public function openEdit(string $kasirId): void
    {
        $row = DB::table('tkmst_kasirs')->where('kasir_id', $kasirId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $kasirId;
        $this->form = [
            'kasir_id'        => (string) $row->kasir_id,
            'kasir_name'      => (string) ($row->kasir_name ?? ''),
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kasir-actions');
        $this->dispatch('focus-kasir-name');
    }

    #[On('master.kasir.toggleActive')]
    public function toggleActive(string $kasirId): void
    {
        $cur = (string) DB::table('tkmst_kasirs')->where('kasir_id', $kasirId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('tkmst_kasirs')->where('kasir_id', $kasirId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status kasir → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.kasir.saved');
    }

    #[On('master.kasir.requestDelete')]
    public function deleteKasir(string $kasirId): void
    {
        try {
            $isUsed = DB::table('tktxn_slshdrs')->where('kasir_id', $kasirId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Kasir tidak bisa dihapus karena masih dipakai pada transaksi penjualan.');
                return;
            }

            $deleted = DB::table('tkmst_kasirs')->where('kasir_id', $kasirId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kasir tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kasir berhasil dihapus.');
            $this->dispatch('master.kasir.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kasir tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.kasir_id'        => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkmst_kasirs,kasir_id'
                : 'required|string',
            'form.kasir_name'      => 'required|string|max:100',
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.kasir_id.required' => 'ID Kasir wajib diisi.',
            'form.kasir_id.max'      => 'ID Kasir maksimal 25 karakter.',
            'form.kasir_id.regex'    => 'ID Kasir hanya huruf besar / angka / "_" / "-".',
            'form.kasir_id.unique'   => 'ID Kasir sudah digunakan.',
            'form.kasir_name.required' => 'Nama Kasir wajib diisi.',
            'form.kasir_name.max'      => 'Nama Kasir maksimal 100 karakter.',
            'form.active_status.required' => 'Status wajib dipilih.',
        ];

        $attributes = [
            'form.kasir_id'        => 'ID Kasir',
            'form.kasir_name'      => 'Nama Kasir',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'kasir_name'      => mb_strtoupper($this->form['kasir_name']),
            'active_status' => $this->form['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkmst_kasirs')->insert([
                'kasir_id' => mb_strtoupper($this->form['kasir_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkmst_kasirs')->where('kasir_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kasir berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kasir.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-kasir-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['kasir_id' => '', 'kasir_name' => '', 'active_status' => '1'];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kasir-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$formMode, $originalId]) }}">

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
                                    {{ $formMode === 'edit' ? 'Ubah Kasir' : 'Tambah Kasir' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Data kasir transaksi penjualan.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-kasir-id.window="$nextTick(() => setTimeout(() => $refs.inputKasirId?.focus(), 150))"
                 x-on:focus-kasir-name.window="$nextTick(() => setTimeout(() => $refs.inputKasirName?.focus(), 150))">

                <x-border-form title="Data Kasir">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Kasir" />
                                <x-text-input wire:model.live="form.kasir_id" x-ref="inputKasirId"
                                    maxlength="25"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.kasir_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputKasirName?.focus()" />
                                <x-input-error :messages="$errors->get('form.kasir_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Kasir" />
                                <x-text-input wire:model.live="form.kasir_name" x-ref="inputKasirName"
                                    maxlength="100"
                                    :error="$errors->has('form.kasir_name')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.kasir_name')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
