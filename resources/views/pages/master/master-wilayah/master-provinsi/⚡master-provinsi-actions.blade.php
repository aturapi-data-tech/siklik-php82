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
        'prop_id'   => '',
        'prop_name' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // ─── Open Create ──────────────────────────────────────────────────────────
    #[On('master.provinsi.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-provinsi-actions');
        $this->dispatch('focus-prop-id');
    }

    // ─── Open Edit ────────────────────────────────────────────────────────────
    #[On('master.provinsi.openEdit')]
    public function openEdit(int $propId): void
    {
        $row = DB::table('rsmst_propinsis')->where('prop_id', $propId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $propId;
        $this->form = [
            'prop_id'   => (string) $row->prop_id,
            'prop_name' => (string) ($row->prop_name ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-provinsi-actions');
        $this->dispatch('focus-prop-name');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────
    #[On('master.provinsi.requestDelete')]
    public function deleteProvinsi(int $propId): void
    {
        try {
            $isUsed = DB::table('rsmst_kabupatens')->where('prop_id', $propId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Provinsi tidak bisa dihapus karena masih dipakai pada data kabupaten.');
                return;
            }

            $deleted = DB::table('rsmst_propinsis')->where('prop_id', $propId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data provinsi tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Provinsi berhasil dihapus.');
            $this->dispatch('master.provinsi.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Provinsi tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    // ─── Save ─────────────────────────────────────────────────────────────────
    public function save(): void
    {
        $rules = [
            'form.prop_id'   => $this->formMode === 'create'
                ? 'required|integer|min:1|max:99|unique:rsmst_propinsis,prop_id'
                : 'required|integer',
            'form.prop_name' => 'required|string|max:50',
        ];

        $messages = [
            'form.prop_id.required' => 'ID Provinsi wajib diisi.',
            'form.prop_id.integer'  => 'ID Provinsi harus berupa angka.',
            'form.prop_id.min'      => 'ID Provinsi minimal 1.',
            'form.prop_id.max'      => 'ID Provinsi maksimal 99.',
            'form.prop_id.unique'   => 'ID Provinsi sudah digunakan.',
            'form.prop_name.required' => 'Nama Provinsi wajib diisi.',
            'form.prop_name.max'      => 'Nama Provinsi maksimal 50 karakter.',
        ];

        $attributes = [
            'form.prop_id'   => 'ID Provinsi',
            'form.prop_name' => 'Nama Provinsi',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = ['prop_name' => mb_strtoupper($this->form['prop_name'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_propinsis')->insert(['prop_id' => (int) $this->form['prop_id'], ...$payload]);
        } else {
            DB::table('rsmst_propinsis')->where('prop_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data provinsi berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.provinsi.saved');
    }

    // ─── Close ────────────────────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-provinsi-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['prop_id' => '', 'prop_name' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-provinsi-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-provinsi-actions"
            event="master.provinsi.saved"
            label="Provinsi"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
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
                                    {{ $formMode === 'edit' ? 'Ubah Data Provinsi' : 'Tambah Data Provinsi' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Master provinsi (RS) — referensi wilayah pasien.
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

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-prop-id.window="$nextTick(() => setTimeout(() => $refs.inputPropId?.focus(), 150))"
                 x-on:focus-prop-name.window="$nextTick(() => setTimeout(() => $refs.inputPropName?.focus(), 150))">

                <x-border-form title="Data Provinsi">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Provinsi" />
                                <x-text-input wire:model.live="form.prop_id" x-ref="inputPropId"
                                    type="number" min="1" max="99"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.prop_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputPropName?.focus()" />
                                <x-input-error :messages="$errors->get('form.prop_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Provinsi" />
                                <x-text-input wire:model.live="form.prop_name" x-ref="inputPropName"
                                    maxlength="50"
                                    :error="$errors->has('form.prop_name')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.prop_name')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">di field terakhir untuk simpan</span>
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
