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
        'cat_id'        => '',
        'cat_desc'      => '',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.kategori.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kategori-actions');
        $this->dispatch('focus-cat-id');
    }

    #[On('master.kategori.openEdit')]
    public function openEdit(string $catId): void
    {
        $row = DB::table('tkmst_categories')->where('cat_id', $catId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $catId;
        $this->form = [
            'cat_id'        => (string) $row->cat_id,
            'cat_desc'      => (string) ($row->cat_desc ?? ''),
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kategori-actions');
        $this->dispatch('focus-cat-desc');
    }

    #[On('master.kategori.toggleActive')]
    public function toggleActive(string $catId): void
    {
        $cur = (string) DB::table('tkmst_categories')->where('cat_id', $catId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('tkmst_categories')->where('cat_id', $catId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status kategori → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.kategori.saved');
    }

    #[On('master.kategori.requestDelete')]
    public function deleteKategori(string $catId): void
    {
        try {
            $isUsed = DB::table('tkmst_products')->where('cat_id', $catId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih dipakai pada data produk.');
                return;
            }

            $deleted = DB::table('tkmst_categories')->where('cat_id', $catId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kategori tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kategori berhasil dihapus.');
            $this->dispatch('master.kategori.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.cat_id'        => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkmst_categories,cat_id'
                : 'required|string',
            'form.cat_desc'      => 'required|string|max:100',
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.cat_id.required' => 'ID Kategori wajib diisi.',
            'form.cat_id.max'      => 'ID Kategori maksimal 25 karakter.',
            'form.cat_id.regex'    => 'ID Kategori hanya huruf besar / angka / "_" / "-".',
            'form.cat_id.unique'   => 'ID Kategori sudah digunakan.',
            'form.cat_desc.required' => 'Deskripsi wajib diisi.',
            'form.cat_desc.max'      => 'Deskripsi maksimal 100 karakter.',
            'form.active_status.required' => 'Status wajib dipilih.',
        ];

        $attributes = [
            'form.cat_id'        => 'ID Kategori',
            'form.cat_desc'      => 'Deskripsi',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'cat_desc'      => mb_strtoupper($this->form['cat_desc']),
            'active_status' => $this->form['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkmst_categories')->insert([
                'cat_id' => mb_strtoupper($this->form['cat_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkmst_categories')->where('cat_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kategori berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kategori.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-kategori-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['cat_id' => '', 'cat_desc' => '', 'active_status' => '1'];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kategori-actions" size="full" height="full" focusable>
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
                                    {{ $formMode === 'edit' ? 'Ubah Kategori' : 'Tambah Kategori' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kategori produk apotek/toko (CAT).
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
                 x-on:focus-cat-id.window="$nextTick(() => setTimeout(() => $refs.inputCatId?.focus(), 150))"
                 x-on:focus-cat-desc.window="$nextTick(() => setTimeout(() => $refs.inputCatDesc?.focus(), 150))">

                <x-border-form title="Data Kategori">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Kategori" />
                                <x-text-input wire:model.live="form.cat_id" x-ref="inputCatId"
                                    maxlength="25"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.cat_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputCatDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.cat_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.cat_desc" x-ref="inputCatDesc"
                                    maxlength="100"
                                    :error="$errors->has('form.cat_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.cat_desc')" class="mt-1" />
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
