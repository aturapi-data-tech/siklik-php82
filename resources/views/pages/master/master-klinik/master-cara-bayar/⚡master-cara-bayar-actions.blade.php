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
        'cb_id'         => '',
        'cb_desc'       => '',
        'active_status' => '1',
        'acc_id'        => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.cara-bayar.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-cara-bayar-actions');
        $this->dispatch('focus-cb-id');
    }

    #[On('master.cara-bayar.openEdit')]
    public function openEdit(string $cbId): void
    {
        $row = DB::table('tkacc_carabayars')->where('cb_id', $cbId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $cbId;
        $this->form = [
            'cb_id'         => (string) $row->cb_id,
            'cb_desc'       => (string) ($row->cb_desc ?? ''),
            'active_status' => (string) ($row->active_status ?? '1'),
            'acc_id'        => (string) ($row->acc_id ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-cara-bayar-actions');
        $this->dispatch('focus-cb-desc');
    }

    #[On('master.cara-bayar.requestDelete')]
    public function deleteCaraBayar(string $cbId): void
    {
        try {
            $deleted = DB::table('tkacc_carabayars')->where('cb_id', $cbId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data cara bayar tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Cara bayar berhasil dihapus.');
            $this->dispatch('master.cara-bayar.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error',
                    message: 'Cara bayar tidak bisa dihapus karena masih dipakai pada transaksi. Non-aktifkan saja.');
                return;
            }
            throw $e;
        }
    }

    /* ── LOV akun (cash-in) — cara bayar dipetakan ke 1 akun ── */
    #[On('lov.selected.cb-acc')]
    public function onAccSelected(string $target, ?array $payload): void
    {
        $this->form['acc_id'] = (string) ($payload['acc_id'] ?? '');
    }

    public function save(): void
    {
        $rules = [
            'form.cb_id'         => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkacc_carabayars,cb_id'
                : 'required|string',
            'form.cb_desc'       => 'required|string|max:100',
            'form.active_status' => 'required|in:0,1',
            'form.acc_id'        => 'nullable|string|max:25|exists:tkacc_accountses,acc_id',
        ];

        $messages = [
            'form.cb_id.required'      => 'ID Cara Bayar wajib diisi.',
            'form.cb_id.max'           => 'ID Cara Bayar maksimal 25 karakter.',
            'form.cb_id.regex'         => 'ID Cara Bayar hanya boleh huruf besar/angka/underscore/dash.',
            'form.cb_id.unique'        => 'ID Cara Bayar sudah digunakan.',
            'form.cb_desc.required'    => 'Deskripsi wajib diisi.',
            'form.cb_desc.max'         => 'Deskripsi maksimal 100 karakter.',
            'form.active_status.in'    => 'Status hanya 0 (Non-aktif) atau 1 (Aktif).',
            'form.acc_id.exists'       => 'Akun tidak ditemukan di master akun.',
            'form.acc_id.max'          => 'Akun maksimal 25 karakter.',
        ];

        $attributes = [
            'form.cb_id'         => 'ID Cara Bayar',
            'form.cb_desc'       => 'Deskripsi',
            'form.active_status' => 'Status Aktif',
            'form.acc_id'        => 'Akun',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'cb_desc'       => mb_strtoupper($this->form['cb_desc']),
            'active_status' => $this->form['active_status'],
            'acc_id'        => $this->form['acc_id'] ?: null,
        ];

        if ($this->formMode === 'create') {
            DB::table('tkacc_carabayars')->insert([
                'cb_id' => mb_strtoupper($this->form['cb_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkacc_carabayars')->where('cb_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data cara bayar berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.cara-bayar.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-cara-bayar-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['cb_id' => '', 'cb_desc' => '', 'active_status' => '1', 'acc_id' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-cara-bayar-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-cara-bayar-actions"
            event="master.cara-bayar.saved"
            label="Cara Bayar"
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
                                    {{ $formMode === 'edit' ? 'Ubah Cara Bayar' : 'Tambah Cara Bayar' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Metode pembayaran yang dipakai di kasir.
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
                 x-on:focus-cb-id.window="$nextTick(() => setTimeout(() => $refs.inputCbId?.focus(), 150))"
                 x-on:focus-cb-desc.window="$nextTick(() => setTimeout(() => $refs.inputCbDesc?.focus(), 150))">

                <x-border-form title="Data Cara Bayar">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID" :required="true" />
                                <x-text-input wire:model.live="form.cb_id" x-ref="inputCbId"
                                    maxlength="25"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.cb_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputCbDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.cb_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" :required="true" />
                                <x-text-input wire:model.live="form.cb_desc" x-ref="inputCbDesc"
                                    maxlength="100"
                                    :error="$errors->has('form.cb_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.cb_desc')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Status" :required="true" />
                                <x-select-input wire:model.live="form.active_status" class="w-full mt-1">
                                    <option value="1">Aktif</option>
                                    <option value="0">Non-aktif</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
                            </div>
                            <div>
                                {{-- LOV akun (kas only) — cara bayar dipetakan ke akun kas
                                     (Tunai → kas, Transfer → bank rekening, BPJS → receivable). --}}
                                <livewire:lov.akun.lov-akun
                                    target="cb-acc"
                                    label="Akun (mapping)"
                                    placeholder="Cari akun kas..."
                                    :kasOnly="true"
                                    :initialAccId="$form['acc_id'] ?? null"
                                    wire:key="lov-akun-cb-{{ $originalId ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('form.acc_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Akun penerimaan saat cara bayar ini dipakai (Tunai → kas, Transfer → bank).
                                </p>
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
