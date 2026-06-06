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
    public string $originalId = "";
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'kota_id'   => '',
        'kota_name' => '',
        'prov_id'  => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /** List provinsi-toko untuk parent select */
    #[Computed]
    public function parents()
    {
        return DB::table('tkmst_provs')
            ->select('prov_id', 'prov_name')
            ->orderBy('prov_name')
            ->get();
    }

    #[On('master.kota-toko.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = "";
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kota-toko-actions');
        $this->dispatch('focus-kota-id');
    }

    #[On('master.kota-toko.openEdit')]
    public function openEdit(string $kotaId): void
    {
        $row = DB::table('tkmst_kotas')->where('kota_id', $kotaId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $kotaId;
        $this->form = [
            'kota_id'   => (string) $row->kota_id,
            'kota_name' => (string) ($row->kota_name ?? ''),
            'prov_id'  => (string) $row->prov_id,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kota-toko-actions');
        $this->dispatch('focus-kota-name');
    }

    #[On('master.kota-toko.requestDelete')]
    public function deleteKotaToko(string $kotaId): void
    {
        try {
            $isUsed = DB::table('tkmst_customers')->where('kota_id', $kotaId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Kota Toko tidak bisa dihapus karena masih punya kecamatan turunannya.');
                return;
            }

            $deleted = DB::table('tkmst_kotas')->where('kota_id', $kotaId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kota-toko tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kota Toko berhasil dihapus.');
            $this->dispatch('master.kota-toko.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kota Toko tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.kota_id'   => $this->formMode === 'create'
                ? 'required|string|max:15|regex:/^[A-Z0-9_-]+$/|unique:tkmst_kotas,kota_id'
                : 'required|string',
            'form.kota_name' => 'required|string|max:100',
            'form.prov_id'  => 'required|string|exists:tkmst_provs,prov_id',
        ];

        $messages = [
            'form.kota_id.required'  => 'ID Kota Toko wajib diisi.',
            'form.kota_id.unique'    => 'ID Kota Toko sudah digunakan.',
            'form.kota_name.required'=> 'Nama Kota Toko wajib diisi.',
            'form.prov_id.required' => 'Provinsi Toko wajib dipilih.',
            'form.prov_id.exists'   => 'Provinsi Toko tidak valid.',
        ];

        $attributes = [
            'form.kota_id'   => 'ID Kota Toko',
            'form.kota_name' => 'Nama Kota Toko',
            'form.prov_id'  => 'Provinsi Toko',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'kota_name' => mb_strtoupper($this->form['kota_name']),
            'prov_id'  => (string) $this->form['prov_id'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkmst_kotas')->insert(['kota_id' => (string) $this->form['kota_id'], ...$payload]);
        } else {
            DB::table('tkmst_kotas')->where('kota_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kota-toko berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kota-toko.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-kota-toko-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['kota_id' => '', 'kota_name' => '', 'prov_id' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kota-toko-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-kota-toko-actions"
            event="master.kota-toko.saved"
            label="Kota Toko"
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
                                    {{ $formMode === 'edit' ? 'Ubah Kota Toko' : 'Tambah Kota Toko' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kota Toko/Kota — anak dari Provinsi Toko.
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
                 x-on:focus-kota-id.window="$nextTick(() => setTimeout(() => $refs.inputKotaId?.focus(), 150))"
                 x-on:focus-kota-name.window="$nextTick(() => setTimeout(() => $refs.inputKotaName?.focus(), 150))">

                <x-border-form title="Data Kota Toko">
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Provinsi Toko (Parent)" />
                            <x-select-input wire:model.live="form.prov_id"
                                :error="$errors->has('form.prov_id')"
                                class="w-full mt-1">
                                <option value="">— Pilih Provinsi Toko —</option>
                                @foreach ($this->parents as $p)
                                    <option value="{{ $p->prov_id }}">{{ $p->prov_name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.prov_id')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Kota Toko" />
                                <x-text-input wire:model.live="form.kota_id" x-ref="inputKotaId"
                                    maxlength="15"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.kota_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputKotaName?.focus()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Kode kota 1-15 char (huruf besar/angka/_/-)
                                </p>
                                <x-input-error :messages="$errors->get('form.kota_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Kota Toko/Kota" />
                                <x-text-input wire:model.live="form.kota_name" x-ref="inputKotaName"
                                    maxlength="50"
                                    :error="$errors->has('form.kota_name')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.kota_name')" class="mt-1" />
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
