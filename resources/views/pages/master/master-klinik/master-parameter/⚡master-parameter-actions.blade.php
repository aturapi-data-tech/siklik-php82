<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'par_id'    => '',
        'par_desc'  => '',
        'par_value' => '0',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.parameter.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-parameter-actions');
        $this->dispatch('focus-par-id');
    }

    #[On('master.parameter.openEdit')]
    public function openEdit(int $parId): void
    {
        $row = DB::table('rsmst_parameters')->where('par_id', $parId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $parId;
        $this->form = [
            'par_id'    => (string) $row->par_id,
            'par_desc'  => (string) ($row->par_desc ?? ''),
            'par_value' => (string) ($row->par_value ?? '0'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-parameter-actions');
        $this->dispatch('focus-par-desc');
    }

    #[On('master.parameter.requestDelete')]
    public function deleteParameter(int $parId): void
    {
        try {
            $deleted = DB::table('rsmst_parameters')->where('par_id', $parId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data parameter tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Parameter berhasil dihapus.');
            $this->dispatch('master.parameter.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Parameter tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.par_id'    => $this->formMode === 'create'
                ? 'required|integer|min:1|max:99999|unique:rsmst_parameters,par_id'
                : 'required|integer',
            'form.par_desc'  => 'required|string|max:100',
            'form.par_value' => 'required|numeric|min:0|max:999999999',
        ];

        $messages = [
            'form.par_id.required' => 'ID Parameter wajib diisi.',
            'form.par_id.integer'  => 'ID Parameter harus berupa angka.',
            'form.par_id.unique'   => 'ID Parameter sudah digunakan.',
            'form.par_desc.required' => 'Deskripsi wajib diisi.',
            'form.par_desc.max'      => 'Deskripsi maksimal 100 karakter.',
            'form.par_value.required' => 'Nilai wajib diisi.',
            'form.par_value.numeric'  => 'Nilai harus berupa angka.',
        ];

        $attributes = [
            'form.par_id'    => 'ID Parameter',
            'form.par_desc'  => 'Deskripsi',
            'form.par_value' => 'Nilai',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'par_desc'  => mb_strtoupper($this->form['par_desc']),
            'par_value' => (float) $this->form['par_value'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_parameters')->insert(['par_id' => (int) $this->form['par_id'], ...$payload]);
        } else {
            DB::table('rsmst_parameters')->where('par_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data parameter berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.parameter.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-parameter-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['par_id' => '', 'par_desc' => '', 'par_value' => '0'];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-parameter-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-parameter-actions"
            event="master.parameter.saved"
            label="Parameter"
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
                                    {{ $formMode === 'edit' ? 'Ubah Parameter' : 'Tambah Parameter' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Parameter sistem (PAR_ID + nilai untuk konfigurasi).
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
                 x-on:focus-par-id.window="$nextTick(() => setTimeout(() => $refs.inputParId?.focus(), 150))"
                 x-on:focus-par-desc.window="$nextTick(() => setTimeout(() => $refs.inputParDesc?.focus(), 150))">

                <x-border-form title="Data Parameter">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Parameter" />
                                <x-text-input wire:model.live="form.par_id" x-ref="inputParId"
                                    type="number" min="1" max="99999"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.par_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputParDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.par_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.par_desc" x-ref="inputParDesc"
                                    maxlength="100"
                                    :error="$errors->has('form.par_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputParValue?.focus()" />
                                <x-input-error :messages="$errors->get('form.par_desc')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Nilai" />
                                <x-text-input wire:model.live="form.par_value" x-ref="inputParValue"
                                    type="number" min="0" step="1"
                                    :error="$errors->has('form.par_value')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Format: angka. Contoh: 7 (untuk hari kontrol), 30000 (untuk tarif default)
                                </p>
                                <x-input-error :messages="$errors->get('form.par_value')" class="mt-1" />
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
