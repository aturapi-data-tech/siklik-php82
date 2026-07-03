<?php
// resources/views/pages/master/master-klinik/master-identitas/master-identitas.blade.php
// Master Identitas Klinik — edit 1 baris dimst_identitases (kop cetak: nama/alamat/kota/telp/fax).

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public array $form = [
        'int_name' => '',
        'int_address' => '',
        'int_city' => '',
        'int_phone1' => '',
        'int_phone2' => '',
        'int_fax' => '',
        'app_desc' => '',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403, 'Hanya Admin yang dapat mengelola Identitas Klinik.');

        $row = (array) (DB::table('dimst_identitases')->first() ?? []);
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = (string) ($row[$k] ?? '');
        }
    }

    protected function rules(): array
    {
        return [
            'form.int_name' => 'required|string|max:240',
            'form.int_address' => 'nullable|string|max:240',
            'form.int_city' => 'nullable|string|max:240',
            'form.int_phone1' => 'nullable|string|max:240',
            'form.int_phone2' => 'nullable|string|max:240',
            'form.int_fax' => 'nullable|string|max:240',
            'form.app_desc' => 'nullable|string|max:240',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.int_name.required' => 'Nama klinik wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.int_name' => 'Nama klinik',
            'form.int_address' => 'Alamat',
            'form.int_city' => 'Kota',
            'form.int_phone1' => 'Telepon 1',
            'form.int_phone2' => 'Telepon 2',
            'form.int_fax' => 'Fax',
            'form.app_desc' => 'Deskripsi aplikasi',
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $this->validate();

        // dimst_identitases = tabel config 1-baris; update seluruh field kop.
        DB::table('dimst_identitases')->update([
            'int_name' => trim($this->form['int_name']),
            'int_address' => trim($this->form['int_address']),
            'int_city' => trim($this->form['int_city']),
            'int_phone1' => trim($this->form['int_phone1']),
            'int_phone2' => trim($this->form['int_phone2']),
            'int_fax' => trim($this->form['int_fax']),
            'app_desc' => trim($this->form['app_desc']),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Identitas klinik berhasil disimpan.');
    }
};
?>

<div>
    <x-page-title title="Identitas Klinik"
        subtitle="Kop identitas klinik untuk header cetakan (nama, alamat, telepon)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6 overflow-y-auto">

            <div class="max-w-3xl mx-auto w-full mt-4">
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                    <form wire:submit="save" class="space-y-5">
                        {{-- Nama --}}
                        <div>
                            <x-input-label for="int_name" value="Nama Klinik *" />
                            <x-text-input id="int_name" type="text" class="block w-full mt-1" maxlength="240"
                                wire:model="form.int_name" />
                            <x-input-error :messages="$errors->get('form.int_name')" class="mt-1" />
                        </div>

                        {{-- Alamat --}}
                        <div>
                            <x-input-label for="int_address" value="Alamat" />
                            <x-textarea id="int_address" class="block w-full mt-1" rows="2" maxlength="240"
                                wire:model="form.int_address" />
                            <x-input-error :messages="$errors->get('form.int_address')" class="mt-1" />
                        </div>

                        {{-- Kota --}}
                        <div>
                            <x-input-label for="int_city" value="Kota" />
                            <x-text-input id="int_city" type="text" class="block w-full mt-1" maxlength="240"
                                wire:model="form.int_city" />
                            <x-input-error :messages="$errors->get('form.int_city')" class="mt-1" />
                        </div>

                        {{-- Telp 1 & 2 --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="int_phone1" value="Telepon 1" />
                                <x-text-input id="int_phone1" type="text" class="block w-full mt-1" maxlength="240"
                                    wire:model="form.int_phone1" />
                                <x-input-error :messages="$errors->get('form.int_phone1')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="int_phone2" value="Telepon 2" />
                                <x-text-input id="int_phone2" type="text" class="block w-full mt-1" maxlength="240"
                                    wire:model="form.int_phone2" />
                                <x-input-error :messages="$errors->get('form.int_phone2')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Fax & App desc --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="int_fax" value="Fax" />
                                <x-text-input id="int_fax" type="text" class="block w-full mt-1" maxlength="240"
                                    wire:model="form.int_fax" />
                                <x-input-error :messages="$errors->get('form.int_fax')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="app_desc" value="Deskripsi Aplikasi" />
                                <x-text-input id="app_desc" type="text" class="block w-full mt-1" maxlength="240"
                                    wire:model="form.app_desc" />
                                <x-input-error :messages="$errors->get('form.app_desc')" class="mt-1" />
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save">Simpan Identitas</span>
                                <span wire:loading wire:target="save" class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
