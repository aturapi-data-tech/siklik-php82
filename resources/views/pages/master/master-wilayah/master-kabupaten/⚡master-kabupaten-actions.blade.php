<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
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
        'kab_id'   => '',
        'kab_name' => '',
        'prop_id'  => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /** List provinsi untuk parent select */
    #[Computed]
    public function parents()
    {
        return DB::table('rsmst_propinsis')
            ->select('prop_id', 'prop_name')
            ->orderBy('prop_name')
            ->get();
    }

    #[On('master.kabupaten.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kabupaten-actions');
        $this->dispatch('focus-kab-id');
    }

    #[On('master.kabupaten.openEdit')]
    public function openEdit(int $kabId): void
    {
        $row = DB::table('rsmst_kabupatens')->where('kab_id', $kabId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $kabId;
        $this->form = [
            'kab_id'   => (string) $row->kab_id,
            'kab_name' => (string) ($row->kab_name ?? ''),
            'prop_id'  => (string) $row->prop_id,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kabupaten-actions');
        $this->dispatch('focus-kab-name');
    }

    #[On('master.kabupaten.requestDelete')]
    public function deleteKabupaten(int $kabId): void
    {
        try {
            $isUsed = DB::table('rsmst_kecamatans')->where('kab_id', $kabId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Kabupaten tidak bisa dihapus karena masih punya kecamatan turunannya.');
                return;
            }

            $deleted = DB::table('rsmst_kabupatens')->where('kab_id', $kabId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kabupaten tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kabupaten berhasil dihapus.');
            $this->dispatch('master.kabupaten.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kabupaten tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.kab_id'   => $this->formMode === 'create'
                ? 'required|integer|min:1|max:9999|unique:rsmst_kabupatens,kab_id'
                : 'required|integer',
            'form.kab_name' => 'required|string|max:50',
            'form.prop_id'  => 'required|integer|exists:rsmst_propinsis,prop_id',
        ];

        $messages = [
            'form.kab_id.required'  => 'ID Kabupaten wajib diisi.',
            'form.kab_id.unique'    => 'ID Kabupaten sudah digunakan.',
            'form.kab_name.required'=> 'Nama Kabupaten wajib diisi.',
            'form.prop_id.required' => 'Provinsi wajib dipilih.',
            'form.prop_id.exists'   => 'Provinsi tidak valid.',
        ];

        $attributes = [
            'form.kab_id'   => 'ID Kabupaten',
            'form.kab_name' => 'Nama Kabupaten',
            'form.prop_id'  => 'Provinsi',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'kab_name' => mb_strtoupper($this->form['kab_name']),
            'prop_id'  => (int) $this->form['prop_id'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_kabupatens')->insert(['kab_id' => (int) $this->form['kab_id'], ...$payload]);
        } else {
            DB::table('rsmst_kabupatens')->where('kab_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kabupaten berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kabupaten.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-kabupaten-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['kab_id' => '', 'kab_name' => '', 'prop_id' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kabupaten-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-kabupaten-actions"
            event="master.kabupaten.saved"
            label="Kabupaten"
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
                                    {{ $formMode === 'edit' ? 'Ubah Kabupaten' : 'Tambah Kabupaten' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kabupaten/Kota — anak dari Provinsi.
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
                 x-on:focus-kab-id.window="$nextTick(() => setTimeout(() => $refs.inputKabId?.focus(), 150))"
                 x-on:focus-kab-name.window="$nextTick(() => setTimeout(() => $refs.inputKabName?.focus(), 150))">

                <x-border-form title="Data Kabupaten">
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Provinsi (Parent)" />
                            <x-select-input wire:model.live="form.prop_id"
                                :error="$errors->has('form.prop_id')"
                                class="w-full mt-1">
                                <option value="">— Pilih Provinsi —</option>
                                @foreach ($this->parents as $p)
                                    <option value="{{ $p->prop_id }}">{{ $p->prop_name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.prop_id')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Kabupaten" />
                                <x-text-input wire:model.live="form.kab_id" x-ref="inputKabId"
                                    type="number" min="1" max="9999"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.kab_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputKabName?.focus()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Kode BPS 4 digit (e.g., 3471=Kota Yogyakarta)
                                </p>
                                <x-input-error :messages="$errors->get('form.kab_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Kabupaten/Kota" />
                                <x-text-input wire:model.live="form.kab_name" x-ref="inputKabName"
                                    maxlength="50"
                                    :error="$errors->has('form.kab_name')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.kab_name')" class="mt-1" />
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
