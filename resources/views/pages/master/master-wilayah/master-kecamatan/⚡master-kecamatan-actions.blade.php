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
        'kec_id'   => '',
        'kec_name' => '',
        'kab_id'  => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /** List kabupaten untuk parent select */
    #[Computed]
    public function parents()
    {
        return DB::table('rsmst_kecamatans')
            ->select('kab_id', 'kab_name')
            ->orderBy('kab_name')
            ->get();
    }

    #[On('master.kecamatan.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kecamatan-actions');
        $this->dispatch('focus-kec-id');
    }

    #[On('master.kecamatan.openEdit')]
    public function openEdit(int $kecId): void
    {
        $row = DB::table('rsmst_kecamatans')->where('kec_id', $kecId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $kecId;
        $this->form = [
            'kec_id'   => (string) $row->kec_id,
            'kec_name' => (string) ($row->kec_name ?? ''),
            'kab_id'  => (string) $row->kab_id,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kecamatan-actions');
        $this->dispatch('focus-kec-name');
    }

    #[On('master.kecamatan.requestDelete')]
    public function deleteKecamatan(int $kecId): void
    {
        try {
            $isUsed = DB::table('rsmst_desas')->where('kec_id', $kecId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Kecamatan tidak bisa dihapus karena masih punya desa turunannya.');
                return;
            }

            $deleted = DB::table('rsmst_kecamatans')->where('kec_id', $kecId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kecamatan tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kecamatan berhasil dihapus.');
            $this->dispatch('master.kecamatan.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kecamatan tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.kec_id'   => $this->formMode === 'create'
                ? 'required|integer|min:1|max:9999999|unique:rsmst_kecamatans,kec_id'
                : 'required|integer',
            'form.kec_name' => 'required|string|max:50',
            'form.kab_id'  => 'required|integer|exists:rsmst_kecamatans,kab_id',
        ];

        $messages = [
            'form.kec_id.required'  => 'ID Kecamatan wajib diisi.',
            'form.kec_id.unique'    => 'ID Kecamatan sudah digunakan.',
            'form.kec_name.required'=> 'Nama Kecamatan wajib diisi.',
            'form.kab_id.required' => 'Kabupaten wajib dipilih.',
            'form.kab_id.exists'   => 'Kabupaten tidak valid.',
        ];

        $attributes = [
            'form.kec_id'   => 'ID Kecamatan',
            'form.kec_name' => 'Nama Kecamatan',
            'form.kab_id'  => 'Kabupaten',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'kec_name' => mb_strtoupper($this->form['kec_name']),
            'kab_id'  => (int) $this->form['kab_id'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_kecamatans')->insert(['kec_id' => (int) $this->form['kec_id'], ...$payload]);
        } else {
            DB::table('rsmst_kecamatans')->where('kec_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kecamatan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kecamatan.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-kecamatan-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['kec_id' => '', 'kec_name' => '', 'kab_id' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kecamatan-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-kecamatan-actions"
            event="master.kecamatan.saved"
            label="Kecamatan"
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
                                    {{ $formMode === 'edit' ? 'Ubah Kecamatan' : 'Tambah Kecamatan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kecamatan/Kota — anak dari Kabupaten.
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
                 x-on:focus-kec-id.window="$nextTick(() => setTimeout(() => $refs.inputKecId?.focus(), 150))"
                 x-on:focus-kec-name.window="$nextTick(() => setTimeout(() => $refs.inputKecName?.focus(), 150))">

                <x-border-form title="Data Kecamatan">
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Kabupaten (Parent)" />
                            <x-select-input wire:model.live="form.kab_id"
                                :error="$errors->has('form.kab_id')"
                                class="w-full mt-1">
                                <option value="">— Pilih Kabupaten —</option>
                                @foreach ($this->parents as $p)
                                    <option value="{{ $p->kab_id }}">{{ $p->kab_name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.kab_id')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Kecamatan" />
                                <x-text-input wire:model.live="form.kec_id" x-ref="inputKecId"
                                    type="number" min="1" max="9999"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.kec_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputKecName?.focus()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Kode BPS 7 digit (e.g., 3471050=Gondokusuman)
                                </p>
                                <x-input-error :messages="$errors->get('form.kec_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Kecamatan/Kota" />
                                <x-text-input wire:model.live="form.kec_name" x-ref="inputKecName"
                                    maxlength="50"
                                    :error="$errors->has('form.kec_name')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.kec_name')" class="mt-1" />
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
