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
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'cm_id'         => '',
        'cm_name'       => '',
        'cm_phone1'     => '',
        'cm_phone2'     => '',
        'cm_email'      => '',
        'cm_address'    => '',
        'kota_id'       => '',
        'prov_id'       => '',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /** Daftar provinsi (toko) untuk select */
    #[Computed]
    public function provinces()
    {
        return DB::table('tkmst_provs')
            ->select('prov_id', 'prov_name')
            ->orderBy('prov_name')
            ->get();
    }

    /** Daftar kota — auto-filter by selected province */
    #[Computed]
    public function cities()
    {
        $q = DB::table('tkmst_kotas')
            ->select('kota_id', 'kota_name', 'prov_id')
            ->orderBy('kota_name');

        if (!empty($this->form['prov_id'])) {
            $q->where('prov_id', $this->form['prov_id']);
        }

        return $q->get();
    }

    /** Saat provinsi berubah, reset pilihan kota */
    public function updatedFormProvId(): void
    {
        $this->form['kota_id'] = '';
    }

    #[On('master.customer.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-customer-actions');
        $this->dispatch('focus-cm-id');
    }

    #[On('master.customer.openEdit')]
    public function openEdit(string $cmId): void
    {
        $row = DB::table('tkmst_customers')->where('cm_id', $cmId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $cmId;
        $this->form = [
            'cm_id'         => (string) $row->cm_id,
            'cm_name'       => (string) ($row->cm_name ?? ''),
            'cm_phone1'     => (string) ($row->cm_phone1 ?? ''),
            'cm_phone2'     => (string) ($row->cm_phone2 ?? ''),
            'cm_email'      => (string) ($row->cm_email ?? ''),
            'cm_address'    => (string) ($row->cm_address ?? ''),
            'kota_id'       => (string) ($row->kota_id ?? ''),
            'prov_id'       => (string) ($row->prov_id ?? ''),
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-customer-actions');
        $this->dispatch('focus-cm-name');
    }

    #[On('master.customer.toggleActive')]
    public function toggleActive(string $cmId): void
    {
        $cur = (string) DB::table('tkmst_customers')->where('cm_id', $cmId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('tkmst_customers')->where('cm_id', $cmId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status customer → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.customer.saved');
    }

    #[On('master.customer.requestDelete')]
    public function deleteCustomer(string $cmId): void
    {
        try {
            $isUsedSls = DB::table('tktxn_slshdrs')->where('cm_id', $cmId)->exists();
            if ($isUsedSls) {
                $this->dispatch('toast', type: 'error', message: 'Customer tidak bisa dihapus karena masih dipakai pada transaksi penjualan.');
                return;
            }

            $deleted = DB::table('tkmst_customers')->where('cm_id', $cmId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data customer tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Customer berhasil dihapus.');
            $this->dispatch('master.customer.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Customer tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.cm_id'         => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkmst_customers,cm_id'
                : 'required|string',
            'form.cm_name'       => 'required|string|max:100',
            'form.cm_phone1'     => 'nullable|string|max:100',
            'form.cm_phone2'     => 'nullable|string|max:100',
            'form.cm_email'      => 'nullable|email|max:100',
            'form.cm_address'    => 'nullable|string|max:100',
            'form.prov_id'       => 'required|string|exists:tkmst_provs,prov_id',
            'form.kota_id'       => 'required|string|exists:tkmst_kotas,kota_id',
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.cm_id.required' => 'ID Customer wajib diisi.',
            'form.cm_id.unique'   => 'ID Customer sudah digunakan.',
            'form.cm_name.required' => 'Nama wajib diisi.',
            'form.prov_id.required' => 'Provinsi wajib dipilih.',
            'form.kota_id.required' => 'Kota wajib dipilih.',
            'form.cm_email.email'   => 'Email tidak valid.',
        ];

        $attributes = [
            'form.cm_id'      => 'ID Customer',
            'form.cm_name'    => 'Nama Customer',
            'form.cm_phone1'  => 'Telepon 1',
            'form.cm_phone2'  => 'Telepon 2',
            'form.cm_email'   => 'Email',
            'form.cm_address' => 'Alamat',
            'form.prov_id'    => 'Provinsi',
            'form.kota_id'    => 'Kota',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'cm_name'       => mb_strtoupper($this->form['cm_name']),
            'cm_phone1'     => $this->form['cm_phone1'] ?: null,
            'cm_phone2'     => $this->form['cm_phone2'] ?: null,
            'cm_email'      => $this->form['cm_email'] ?: null,
            'cm_address'    => $this->form['cm_address'] ?: null,
            'kota_id'       => $this->form['kota_id'],
            'prov_id'       => $this->form['prov_id'],
            'active_status' => $this->form['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkmst_customers')->insert([
                'cm_id' => mb_strtoupper($this->form['cm_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkmst_customers')->where('cm_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data customer berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.customer.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-customer-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'cm_id' => '', 'cm_name' => '', 'cm_phone1' => '', 'cm_phone2' => '',
            'cm_email' => '', 'cm_address' => '',
            'kota_id' => '', 'prov_id' => '', 'active_status' => '1',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-customer-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-customer-actions"
            event="master.customer.saved"
            label="Customer"
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
                                    {{ $formMode === 'edit' ? 'Ubah Customer' : 'Tambah Customer' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Customer untuk modul penjualan toko/apotek.
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
                 x-on:focus-cm-id.window="$nextTick(() => setTimeout(() => $refs.inputCmId?.focus(), 150))"
                 x-on:focus-cm-name.window="$nextTick(() => setTimeout(() => $refs.inputCmName?.focus(), 150))">

                <x-border-form title="Data Customer">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Customer" />
                                <x-text-input wire:model.live="form.cm_id" x-ref="inputCmId"
                                    maxlength="25"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.cm_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.cm_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Customer" />
                                <x-text-input wire:model.live="form.cm_name" x-ref="inputCmName"
                                    maxlength="100"
                                    :error="$errors->has('form.cm_name')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.cm_name')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Wilayah: Provinsi → Kota cascade --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Provinsi" />
                                <x-select-input wire:model.live="form.prov_id"
                                    :error="$errors->has('form.prov_id')"
                                    class="w-full mt-1">
                                    <option value="">— Pilih Provinsi —</option>
                                    @foreach ($this->provinces as $p)
                                        <option value="{{ $p->prov_id }}">{{ $p->prov_name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.prov_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kota" />
                                <x-select-input wire:model.live="form.kota_id"
                                    :error="$errors->has('form.kota_id')"
                                    :disabled="empty($form['prov_id'])"
                                    class="w-full mt-1">
                                    <option value="">
                                        {{ empty($form['prov_id']) ? '— Pilih provinsi dulu —' : '— Pilih Kota —' }}
                                    </option>
                                    @foreach ($this->cities as $kt)
                                        <option value="{{ $kt->kota_id }}">{{ $kt->kota_name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.kota_id')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Telepon 1" />
                                <x-text-input wire:model.live="form.cm_phone1"
                                    maxlength="100"
                                    :error="$errors->has('form.cm_phone1')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.cm_phone1')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Telepon 2 (opsional)" />
                                <x-text-input wire:model.live="form.cm_phone2"
                                    maxlength="100"
                                    :error="$errors->has('form.cm_phone2')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.cm_phone2')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Email (opsional)" />
                            <x-text-input wire:model.live="form.cm_email"
                                type="email" maxlength="100"
                                :error="$errors->has('form.cm_email')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.cm_email')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Alamat" />
                            <x-textarea wire:model.live="form.cm_address"
                                maxlength="100" rows="2"
                                :error="$errors->has('form.cm_address')"
                                class="w-full mt-1 uppercase" />
                            <x-input-error :messages="$errors->get('form.cm_address')" class="mt-1" />
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
                        Pilih provinsi dulu, lalu kota akan auto-filter.
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
