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
        'tucico_id'     => '',
        'tucico_desc'   => '',
        'tucico_status' => 'CI',     // CI = Cash In (penerimaan), CO = Cash Out (pengeluaran)
        'active_status' => '1',
        'acc_id'        => '',
    ];

    public function mount(): void { $this->registerAreas(['modal']); }

    #[On('master.tucico.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-tucico-actions');
    }

    #[On('master.tucico.openEdit')]
    public function openEdit(string $tucicoId): void
    {
        $row = DB::table('tkacc_tucicos')->where('tucico_id', $tucicoId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode = 'edit';
        $this->originalId = $tucicoId;
        $this->form = [
            'tucico_id'     => (string) $row->tucico_id,
            'tucico_desc'   => (string) ($row->tucico_desc ?? ''),
            'tucico_status' => (string) ($row->tucico_status ?? 'CI'),
            'active_status' => (string) ($row->active_status ?? '1'),
            'acc_id'        => (string) ($row->acc_id ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-tucico-actions');
    }

    #[On('lov.selected.tucico-acc')]
    public function onAccSelected(string $target, ?array $payload): void
    {
        $this->form['acc_id'] = (string) ($payload['acc_id'] ?? '');
    }

    #[On('master.tucico.requestDelete')]
    public function deleteTucico(string $tucicoId): void
    {
        try {
            $deleted = DB::table('tkacc_tucicos')->where('tucico_id', $tucicoId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'TUCICO tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'TUCICO berhasil dihapus.');
            $this->dispatch('master.tucico.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error',
                    message: 'TUCICO masih dipakai pada transaksi. Non-aktifkan saja.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.tucico_id'     => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkacc_tucicos,tucico_id'
                : 'required|string',
            'form.tucico_desc'   => 'required|string|max:100',
            'form.tucico_status' => 'required|in:CI,CO',
            'form.active_status' => 'required|in:0,1',
            'form.acc_id'        => 'required|string|max:25|exists:tkacc_accountses,acc_id',
        ];

        $messages = [
            'form.tucico_id.required'  => 'ID TUCICO wajib diisi.',
            'form.tucico_id.unique'    => 'ID TUCICO sudah digunakan.',
            'form.acc_id.required'     => 'Akun wajib dipilih.',
            'form.acc_id.exists'       => 'Akun tidak valid.',
        ];

        $attributes = [
            'form.tucico_id'   => 'ID TUCICO',
            'form.tucico_desc' => 'Deskripsi',
            'form.acc_id'      => 'Akun',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'tucico_desc'   => mb_strtoupper($this->form['tucico_desc']),
            'tucico_status' => $this->form['tucico_status'],
            'active_status' => $this->form['active_status'],
            'acc_id'        => $this->form['acc_id'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkacc_tucicos')->insert([
                'tucico_id' => mb_strtoupper($this->form['tucico_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkacc_tucicos')->where('tucico_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'TUCICO berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.tucico.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-tucico-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'tucico_id' => '', 'tucico_desc' => '', 'tucico_status' => 'CI',
            'active_status' => '1', 'acc_id' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-tucico-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-tucico-actions"
            event="master.tucico.saved"
            label="TUCICO"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah TUCICO' : 'Tambah TUCICO' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Pos kas transit untuk pemindahan kas non-transaksi.
                        </p>
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

            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <x-border-form title="Data TUCICO">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID" :required="true" />
                                <x-text-input wire:model.live="form.tucico_id"
                                    maxlength="25" :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.tucico_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.tucico_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" :required="true" />
                                <x-text-input wire:model.live="form.tucico_desc"
                                    maxlength="100"
                                    :error="$errors->has('form.tucico_desc')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.tucico_desc')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <livewire:lov.akun.lov-akun
                                target="tucico-acc"
                                label="Akun (mapping)"
                                placeholder="Cari akun..."
                                :initialAccId="$form['acc_id'] ?? null"
                                wire:key="lov-akun-tucico-{{ $originalId ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('form.acc_id')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Kategori (CI / CO)" :required="true" />
                                <x-select-input wire:model.live="form.tucico_status" class="w-full mt-1">
                                    <option value="CI">CI — Cash In (Penerimaan)</option>
                                    <option value="CO">CO — Cash Out (Pengeluaran)</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Dipakai TKTXN_TUCASHINS (CI) atau TKTXN_TUCASHOUTS (CO).
                                </p>
                                <x-input-error :messages="$errors->get('form.tucico_status')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Status Aktif" :required="true" />
                                <div class="mt-2">
                                    <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0">
                                        {{ ($form['active_status'] ?? '0') === '1' ? 'Aktif' : 'Non-aktif' }}
                                    </x-toggle>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Saving...</span>
                    </x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
