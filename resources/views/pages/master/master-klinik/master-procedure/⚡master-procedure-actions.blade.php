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
        'proc_id'   => '',
        'proc_desc' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.procedure.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-procedure-actions');
        $this->dispatch('focus-proc-id');
    }

    #[On('master.procedure.openEdit')]
    public function openEdit(string $procId): void
    {
        $row = DB::table('rsmst_mstprocedures')->where('proc_id', $procId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $procId;
        $this->form = [
            'proc_id'   => (string) $row->proc_id,
            'proc_desc' => (string) ($row->proc_desc ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-procedure-actions');
        $this->dispatch('focus-proc-desc');
    }

    #[On('master.procedure.requestDelete')]
    public function deleteProsedur(string $procId): void
    {
        try {
            $deleted = DB::table('rsmst_mstprocedures')->where('proc_id', $procId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data prosedur tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Prosedur berhasil dihapus.');
            $this->dispatch('master.procedure.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Prosedur tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.proc_id'   => $this->formMode === 'create'
                ? 'required|string|max:15|regex:/^[A-Z0-9]+$/|unique:rsmst_mstprocedures,proc_id'
                : 'required|string',
            'form.proc_desc' => 'required|string|max:250',
        ];

        $messages = [
            'form.proc_id.required' => 'ID Prosedur wajib diisi.',
            'form.proc_id.max'      => 'ID Prosedur maksimal 15 karakter.',
            'form.proc_id.regex'    => 'ID Prosedur hanya boleh huruf besar/angka.',
            'form.proc_id.unique'   => 'ID Prosedur sudah digunakan.',
            'form.proc_desc.required' => 'Deskripsi wajib diisi.',
            'form.proc_desc.max'      => 'Deskripsi maksimal 250 karakter.',
        ];

        $attributes = [
            'form.proc_id'   => 'ID Prosedur',
            'form.proc_desc' => 'Deskripsi',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = ['proc_desc' => mb_strtoupper($this->form['proc_desc'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_mstprocedures')->insert([
                'proc_id' => mb_strtoupper($this->form['proc_id']),
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_mstprocedures')->where('proc_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data prosedur berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.procedure.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-procedure-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['proc_id' => '', 'proc_desc' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-procedure-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-procedure-actions"
            event="master.procedure.saved"
            label="Prosedur"
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
                                    {{ $formMode === 'edit' ? 'Ubah Prosedur' : 'Tambah Prosedur' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Prosedur medis (ICD-9).
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
                 x-on:focus-proc-id.window="$nextTick(() => setTimeout(() => $refs.inputProcId?.focus(), 150))"
                 x-on:focus-proc-desc.window="$nextTick(() => setTimeout(() => $refs.inputProcDesc?.focus(), 150))">

                <x-border-form title="Data Prosedur">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID" />
                                <x-text-input wire:model.live="form.proc_id" x-ref="inputProcId"
                                    maxlength="15"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.proc_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputProcDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.proc_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.proc_desc" x-ref="inputProcDesc"
                                    maxlength="250"
                                    :error="$errors->has('form.proc_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.proc_desc')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk simpan</span>
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
