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
        'medik_no'   => '',
        'medik_name' => '',
        'condition'  => '',
        'kapasiti'   => '',
        'jml'        => '0',
        'age'        => '0',
        'bln'        => '0',
        'sertifikat' => '',
        'izin'       => '',
        'tgl_buy'    => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.medik.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-medik-actions');
        $this->dispatch('focus-medik-no');
    }

    #[On('master.medik.openEdit')]
    public function openEdit(string $medikNo): void
    {
        $row = DB::table('rsmst_medik')->where('medik_no', $medikNo)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $medikNo;
        $this->form = [
            'medik_no'   => (string) $row->medik_no,
            'medik_name' => (string) ($row->medik_name ?? ''),
            'condition'  => (string) ($row->condition ?? ''),
            'kapasiti'   => (string) ($row->kapasiti ?? ''),
            'jml'        => (string) ($row->jml ?? '0'),
            'age'        => (string) ($row->age ?? '0'),
            'bln'        => (string) ($row->bln ?? '0'),
            'sertifikat' => (string) ($row->sertifikat ?? ''),
            'izin'       => (string) ($row->izin ?? ''),
            'tgl_buy'    => $row->tgl_buy
                ? \Carbon\Carbon::parse($row->tgl_buy)->format('Y-m-d')
                : '',
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-medik-actions');
        $this->dispatch('focus-medik-name');
    }

    #[On('master.medik.requestDelete')]
    public function deleteMedik(string $medikNo): void
    {
        try {
            $deleted = DB::table('rsmst_medik')->where('medik_no', $medikNo)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data alat medis tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Alat medis berhasil dihapus.');
            $this->dispatch('master.medik.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Alat medis tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.medik_no'   => $this->formMode === 'create'
                ? 'required|string|max:20|unique:rsmst_medik,medik_no'
                : 'required|string',
            'form.medik_name' => 'required|string|max:200',
            'form.condition'  => 'nullable|string|max:20',
            'form.kapasiti'   => 'nullable|string|max:20',
            'form.jml'        => 'nullable|integer|min:0',
            'form.age'        => 'nullable|integer|min:0|max:99',
            'form.bln'        => 'nullable|integer|min:0|max:11',
            'form.sertifikat' => 'nullable|string|max:20',
            'form.izin'       => 'nullable|string|max:20',
            'form.tgl_buy'    => 'nullable|date',
        ];

        $messages = [
            'form.medik_no.required'   => 'No Alat Medis wajib diisi.',
            'form.medik_no.unique'     => 'No Alat Medis sudah digunakan.',
            'form.medik_name.required' => 'Nama Alat wajib diisi.',
            'form.tgl_buy.date'        => 'Tanggal beli tidak valid.',
        ];

        $attributes = [
            'form.medik_no'   => 'No Alat',
            'form.medik_name' => 'Nama Alat',
            'form.condition'  => 'Kondisi',
            'form.kapasiti'   => 'Kapasitas',
            'form.jml'        => 'Jumlah',
            'form.age'        => 'Umur (tahun)',
            'form.bln'        => 'Umur (bulan)',
            'form.sertifikat' => 'Sertifikat',
            'form.izin'       => 'Izin',
            'form.tgl_buy'    => 'Tgl Beli',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'medik_name' => mb_strtoupper($this->form['medik_name']),
            'condition'  => $this->form['condition'] ? mb_strtoupper($this->form['condition']) : null,
            'kapasiti'   => $this->form['kapasiti'] ? mb_strtoupper($this->form['kapasiti']) : null,
            'jml'        => (int) ($this->form['jml'] ?: 0),
            'age'        => (int) ($this->form['age'] ?: 0),
            'bln'        => (int) ($this->form['bln'] ?: 0),
            'sertifikat' => $this->form['sertifikat'] ? mb_strtoupper($this->form['sertifikat']) : null,
            'izin'       => $this->form['izin'] ? mb_strtoupper($this->form['izin']) : null,
            'tgl_buy'    => $this->form['tgl_buy'] ?: null,
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_medik')->insert([
                'medik_no' => mb_strtoupper($this->form['medik_no']),
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_medik')->where('medik_no', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data alat medis berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.medik.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-medik-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'medik_no' => '', 'medik_name' => '', 'condition' => '', 'kapasiti' => '',
            'jml' => '0', 'age' => '0', 'bln' => '0',
            'sertifikat' => '', 'izin' => '', 'tgl_buy' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-medik-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-medik-actions"
            event="master.medik.saved"
            label="Alat Medis"
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
                                    {{ $formMode === 'edit' ? 'Ubah Alat Medis' : 'Tambah Alat Medis' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Tracking alat medis klinik (kondisi, sertifikat, izin).
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

            <div class="flex-1 px-4 py-4 space-y-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-medik-no.window="$nextTick(() => setTimeout(() => $refs.inputMedikNo?.focus(), 150))"
                 x-on:focus-medik-name.window="$nextTick(() => setTimeout(() => $refs.inputMedikName?.focus(), 150))">

                <x-border-form title="Identitas Alat">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="No Alat" />
                                <x-text-input wire:model.live="form.medik_no" x-ref="inputMedikNo"
                                    maxlength="20"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.medik_no')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.medik_no')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Alat" />
                                <x-text-input wire:model.live="form.medik_name" x-ref="inputMedikName"
                                    maxlength="200"
                                    :error="$errors->has('form.medik_name')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.medik_name')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Kondisi" />
                                <x-select-input wire:model.live="form.condition" class="w-full mt-1">
                                    <option value="">— Pilih —</option>
                                    <option value="BAIK">Baik</option>
                                    <option value="RUSAK_RINGAN">Rusak Ringan</option>
                                    <option value="RUSAK_BERAT">Rusak Berat</option>
                                    <option value="KALIBRASI">Perlu Kalibrasi</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.condition')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kapasitas (opsional)" />
                                <x-text-input wire:model.live="form.kapasiti"
                                    maxlength="20"
                                    :error="$errors->has('form.kapasiti')"
                                    class="w-full mt-1 uppercase" placeholder="50 KG / 220V" />
                                <x-input-error :messages="$errors->get('form.kapasiti')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Jumlah Unit" />
                                <x-text-input wire:model.live="form.jml"
                                    type="number" min="0"
                                    :error="$errors->has('form.jml')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.jml')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Umur & Pembelian">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Umur (tahun)" />
                            <x-text-input wire:model.live="form.age"
                                type="number" min="0" max="99"
                                :error="$errors->has('form.age')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.age')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Umur (bulan)" />
                            <x-text-input wire:model.live="form.bln"
                                type="number" min="0" max="11"
                                :error="$errors->has('form.bln')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.bln')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Tgl Beli" />
                            <x-text-input wire:model.live="form.tgl_buy"
                                type="date"
                                :error="$errors->has('form.tgl_buy')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.tgl_buy')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Legal (Sertifikat & Izin)">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="No Sertifikat" />
                            <x-text-input wire:model.live="form.sertifikat"
                                maxlength="20"
                                :error="$errors->has('form.sertifikat')"
                                class="w-full mt-1 uppercase" />
                            <x-input-error :messages="$errors->get('form.sertifikat')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="No Izin" />
                            <x-text-input wire:model.live="form.izin"
                                maxlength="20"
                                :error="$errors->has('form.izin')"
                                class="w-full mt-1 uppercase" />
                            <x-input-error :messages="$errors->get('form.izin')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pastikan no sertifikat &amp; izin masih berlaku.
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
