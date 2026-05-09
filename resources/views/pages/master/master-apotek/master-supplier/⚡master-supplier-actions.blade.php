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
        'supp_id'       => '',
        'supp_name'     => '',
        'supp_email'    => '',
        'supp_phone1'   => '',
        'supp_phone2'   => '',
        'supp_address'  => '',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.supplier.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-supplier-actions');
        $this->dispatch('focus-supp-id');
    }

    #[On('master.supplier.openEdit')]
    public function openEdit(string $suppId): void
    {
        $row = DB::table('tkmst_suppliers')->where('supp_id', $suppId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $suppId;
        $this->form = [
            'supp_id'       => (string) $row->supp_id,
            'supp_name'     => (string) ($row->supp_name ?? ''),
            'supp_email'    => (string) ($row->supp_email ?? ''),
            'supp_phone1'   => (string) ($row->supp_phone1 ?? ''),
            'supp_phone2'   => (string) ($row->supp_phone2 ?? ''),
            'supp_address'  => (string) ($row->supp_address ?? ''),
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-supplier-actions');
        $this->dispatch('focus-supp-name');
    }

    #[On('master.supplier.toggleActive')]
    public function toggleActive(string $suppId): void
    {
        $cur = (string) DB::table('tkmst_suppliers')->where('supp_id', $suppId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('tkmst_suppliers')->where('supp_id', $suppId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status supplier → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.supplier.saved');
    }

    #[On('master.supplier.requestDelete')]
    public function deleteSupplier(string $suppId): void
    {
        try {
            $isUsed = DB::table('tkmst_products')->where('supp_id', $suppId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Supplier tidak bisa dihapus karena masih dipakai pada data produk.');
                return;
            }

            $deleted = DB::table('tkmst_suppliers')->where('supp_id', $suppId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data supplier tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Supplier berhasil dihapus.');
            $this->dispatch('master.supplier.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Supplier tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.supp_id'       => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkmst_suppliers,supp_id'
                : 'required|string',
            'form.supp_name'     => 'required|string|max:100',
            'form.supp_email'    => 'nullable|email|max:100',
            'form.supp_phone1'   => 'nullable|string|max:15',
            'form.supp_phone2'   => 'nullable|string|max:15',
            'form.supp_address'  => 'nullable|string|max:250',
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.supp_id.required' => 'ID Supplier wajib diisi.',
            'form.supp_id.max'      => 'ID Supplier maksimal 25 karakter.',
            'form.supp_id.regex'    => 'ID Supplier hanya huruf besar/angka/"_"/"-".',
            'form.supp_id.unique'   => 'ID Supplier sudah digunakan.',
            'form.supp_name.required' => 'Nama wajib diisi.',
            'form.supp_email.email'   => 'Email tidak valid.',
        ];

        $attributes = [
            'form.supp_id'      => 'ID Supplier',
            'form.supp_name'    => 'Nama Supplier',
            'form.supp_email'   => 'Email',
            'form.supp_phone1'  => 'Telepon 1',
            'form.supp_phone2'  => 'Telepon 2',
            'form.supp_address' => 'Alamat',
            'form.active_status'=> 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'supp_name'     => mb_strtoupper($this->form['supp_name']),
            'supp_email'    => $this->form['supp_email'] ?: null,
            'supp_phone1'   => $this->form['supp_phone1'] ?: null,
            'supp_phone2'   => $this->form['supp_phone2'] ?: null,
            'supp_address'  => $this->form['supp_address'] ?: null,
            'active_status' => $this->form['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkmst_suppliers')->insert([
                'supp_id' => mb_strtoupper($this->form['supp_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkmst_suppliers')->where('supp_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data supplier berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.supplier.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-supplier-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'supp_id' => '', 'supp_name' => '', 'supp_email' => '',
            'supp_phone1' => '', 'supp_phone2' => '', 'supp_address' => '',
            'active_status' => '1',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-supplier-actions" size="full" height="full" focusable>
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
                                    {{ $formMode === 'edit' ? 'Ubah Supplier' : 'Tambah Supplier' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Supplier obat &amp; alkes untuk modul apotek.
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
                 x-on:focus-supp-id.window="$nextTick(() => setTimeout(() => $refs.inputSuppId?.focus(), 150))"
                 x-on:focus-supp-name.window="$nextTick(() => setTimeout(() => $refs.inputSuppName?.focus(), 150))">

                <x-border-form title="Data Supplier">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Supplier" />
                                <x-text-input wire:model.live="form.supp_id" x-ref="inputSuppId"
                                    maxlength="25"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.supp_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.supp_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Supplier" />
                                <x-text-input wire:model.live="form.supp_name" x-ref="inputSuppName"
                                    maxlength="100"
                                    :error="$errors->has('form.supp_name')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.supp_name')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Telepon 1" />
                                <x-text-input wire:model.live="form.supp_phone1"
                                    maxlength="15"
                                    :error="$errors->has('form.supp_phone1')"
                                    class="w-full mt-1" placeholder="Contoh: 0274-1234567" />
                                <x-input-error :messages="$errors->get('form.supp_phone1')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Telepon 2 (opsional)" />
                                <x-text-input wire:model.live="form.supp_phone2"
                                    maxlength="15"
                                    :error="$errors->has('form.supp_phone2')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.supp_phone2')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Email (opsional)" />
                            <x-text-input wire:model.live="form.supp_email"
                                type="email" maxlength="100"
                                :error="$errors->has('form.supp_email')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.supp_email')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Alamat" />
                            <x-textarea wire:model.live="form.supp_address"
                                maxlength="250" rows="2"
                                :error="$errors->has('form.supp_address')"
                                class="w-full mt-1 uppercase" />
                            <x-input-error :messages="$errors->get('form.supp_address')" class="mt-1" />
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
                        Pastikan data sudah benar sebelum menyimpan.
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
