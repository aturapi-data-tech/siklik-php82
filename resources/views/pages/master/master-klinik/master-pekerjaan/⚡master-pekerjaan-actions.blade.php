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
        'job_id'   => '',
        'job_name' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.pekerjaan.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-pekerjaan-actions');
        $this->dispatch('focus-job-id');
    }

    #[On('master.pekerjaan.openEdit')]
    public function openEdit(int $jobId): void
    {
        $row = DB::table('rsmst_jobs')->where('job_id', $jobId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $jobId;
        $this->form = [
            'job_id'   => (string) $row->job_id,
            'job_name' => (string) ($row->job_name ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-pekerjaan-actions');
        $this->dispatch('focus-job-name');
    }

    #[On('master.pekerjaan.requestDelete')]
    public function deletePekerjaan(int $jobId): void
    {
        try {
            $isUsed = DB::table('rsmst_pasiens')->where('job_id', $jobId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Pekerjaan tidak bisa dihapus karena masih dipakai pada data pasien.');
                return;
            }

            $deleted = DB::table('rsmst_jobs')->where('job_id', $jobId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data pekerjaan tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Pekerjaan berhasil dihapus.');
            $this->dispatch('master.pekerjaan.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Pekerjaan tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.job_id'   => $this->formMode === 'create'
                ? 'required|integer|min:1|max:99|unique:rsmst_jobs,job_id'
                : 'required|integer',
            'form.job_name' => 'required|string|max:25',
        ];

        $messages = [
            'form.job_id.required' => 'ID Pekerjaan wajib diisi.',
            'form.job_id.integer'  => 'ID Pekerjaan harus berupa angka.',
            'form.job_id.min'      => 'ID Pekerjaan minimal 1.',
            'form.job_id.max'      => 'ID Pekerjaan maksimal 99.',
            'form.job_id.unique'   => 'ID Pekerjaan sudah digunakan.',
            'form.job_name.required' => 'Nama Pekerjaan wajib diisi.',
            'form.job_name.max'      => 'Nama Pekerjaan maksimal 25 karakter.',
        ];

        $attributes = [
            'form.job_id'   => 'ID Pekerjaan',
            'form.job_name' => 'Nama Pekerjaan',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = ['job_name' => mb_strtoupper($this->form['job_name'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_jobs')->insert(['job_id' => (int) $this->form['job_id'], ...$payload]);
        } else {
            DB::table('rsmst_jobs')->where('job_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data pekerjaan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.pekerjaan.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-pekerjaan-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['job_id' => '', 'job_name' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-pekerjaan-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-pekerjaan-actions"
            event="master.pekerjaan.saved"
            label="Pekerjaan"
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
                                    {{ $formMode === 'edit' ? 'Ubah Data Pekerjaan' : 'Tambah Data Pekerjaan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi pekerjaan pasien.
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
                 x-on:focus-job-id.window="$nextTick(() => setTimeout(() => $refs.inputJobId?.focus(), 150))"
                 x-on:focus-job-name.window="$nextTick(() => setTimeout(() => $refs.inputJobName?.focus(), 150))">

                <x-border-form title="Data Pekerjaan">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Pekerjaan" />
                                <x-text-input wire:model.live="form.job_id" x-ref="inputJobId"
                                    type="number" min="1" max="99"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.job_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputJobName?.focus()" />
                                <x-input-error :messages="$errors->get('form.job_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Pekerjaan" />
                                <x-text-input wire:model.live="form.job_name" x-ref="inputJobName"
                                    maxlength="25"
                                    :error="$errors->has('form.job_name')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.job_name')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

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
