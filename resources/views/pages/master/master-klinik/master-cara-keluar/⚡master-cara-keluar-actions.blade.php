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
        'out_no'   => '',
        'out_desc' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.cara-keluar.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-cara-keluar-actions');
        $this->dispatch('focus-out-no');
    }

    #[On('master.cara-keluar.openEdit')]
    public function openEdit(string $outNo): void
    {
        $row = DB::table('rsmst_outs')->where('out_no', $outNo)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $outNo;
        $this->form = [
            'out_no'   => (string) $row->out_no,
            'out_desc' => (string) ($row->out_desc ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-cara-keluar-actions');
        $this->dispatch('focus-out-desc');
    }

    #[On('master.cara-keluar.requestDelete')]
    public function deleteCaraKeluar(string $outNo): void
    {
        try {
            $deleted = DB::table('rsmst_outs')->where('out_no', $outNo)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data cara keluar tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Cara keluar berhasil dihapus.');
            $this->dispatch('master.cara-keluar.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Cara keluar tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.out_no'   => $this->formMode === 'create'
                ? 'required|string|max:5|regex:/^[A-Z0-9]+$/|unique:rsmst_outs,out_no'
                : 'required|string',
            'form.out_desc' => 'required|string|max:50',
        ];

        $messages = [
            'form.out_no.required' => 'ID Cara Keluar wajib diisi.',
            'form.out_no.max'      => 'ID Cara Keluar maksimal 5 karakter.',
            'form.out_no.regex'    => 'ID Cara Keluar hanya boleh huruf besar/angka.',
            'form.out_no.unique'   => 'ID Cara Keluar sudah digunakan.',
            'form.out_desc.required' => 'Deskripsi wajib diisi.',
            'form.out_desc.max'      => 'Deskripsi maksimal 50 karakter.',
        ];

        $attributes = [
            'form.out_no'   => 'No Cara Keluar',
            'form.out_desc' => 'Deskripsi',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = ['out_desc' => mb_strtoupper($this->form['out_desc'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_outs')->insert([
                'out_no' => mb_strtoupper($this->form['out_no']),
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_outs')->where('out_no', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data cara keluar berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.cara-keluar.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-cara-keluar-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['out_no' => '', 'out_desc' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-cara-keluar-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-cara-keluar-actions"
            event="master.cara-keluar.saved"
            label="Cara Keluar"
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
                                    {{ $formMode === 'edit' ? 'Ubah Cara Keluar' : 'Tambah Cara Keluar' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Alasan keluar/discharge pasien (Sembuh, Rujuk, Pulang Paksa, dll).
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
                 x-on:focus-out-no.window="$nextTick(() => setTimeout(() => $refs.inputOutNo?.focus(), 150))"
                 x-on:focus-out-desc.window="$nextTick(() => setTimeout(() => $refs.inputOutDesc?.focus(), 150))">

                <x-border-form title="Data Cara Keluar">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID" />
                                <x-text-input wire:model.live="form.out_no" x-ref="inputOutNo"
                                    maxlength="5"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.out_no')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputOutDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.out_no')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.out_desc" x-ref="inputOutDesc"
                                    maxlength="50"
                                    :error="$errors->has('form.out_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.out_desc')" class="mt-1" />
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
