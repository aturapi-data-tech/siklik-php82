<x-border-form :title="__('Rekonsiliasi Obat')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="space-y-4">

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Daftar obat yang sedang dikonsumsi pasien sebelum kunjungan ini (rutin / dari faskes lain).
            Penting untuk skrining interaksi obat saat dokter meresepkan.
        </p>

        {{-- INPUT ROW --}}
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
            <div class="sm:col-span-5">
                <x-input-label value="Nama Obat" :required="true" />
                <x-text-input wire:model="rekonsiliasiObatInput.namaObat"
                    placeholder="Contoh: Metformin 500mg"
                    :disabled="$isFormLocked" class="w-full mt-1" />
            </div>
            <div class="sm:col-span-3">
                <x-input-label value="Dosis" />
                <x-text-input wire:model="rekonsiliasiObatInput.dosis"
                    placeholder="Contoh: 1x sehari"
                    :disabled="$isFormLocked" class="w-full mt-1" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label value="Rute" />
                <x-text-input wire:model="rekonsiliasiObatInput.rute"
                    placeholder="Contoh: Oral"
                    :disabled="$isFormLocked" class="w-full mt-1" />
            </div>
            <div class="flex items-end sm:col-span-2">
                <x-primary-button type="button" wire:click="addRekonsiliasiObat"
                    wire:loading.attr="disabled" wire:target="addRekonsiliasiObat"
                    :disabled="$isFormLocked" class="w-full">
                    <span wire:loading.remove wire:target="addRekonsiliasiObat">+ Tambah</span>
                    <span wire:loading wire:target="addRekonsiliasiObat">...</span>
                </x-primary-button>
            </div>
        </div>

        {{-- LIST --}}
        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg dark:border-gray-700 dark:bg-gray-900">
            <table class="ds-table ds-table-entri">
                <thead>
                    <tr>
                        <th>Nama Obat</th>
                        <th>Dosis</th>
                        <th>Rute</th>
                        <th class="w-24 ds-c">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dataDaftarPoliRJ['anamnesa']['rekonsiliasiObat'] ?? [] as $i => $obat)
                        <tr wire:key="rekon-obat-{{ $i }}-{{ $obat['namaObat'] }}">
                            <td class="ds-td-strong">{{ $obat['namaObat'] ?? '-' }}</td>
                            <td>{{ $obat['dosis'] ?? '-' }}</td>
                            <td>{{ $obat['rute'] ?? '-' }}</td>
                            <td class="ds-c">
                                <x-confirm-button variant="danger"
                                    :action="'removeRekonsiliasiObat(\'' . addslashes($obat['namaObat']) . '\')'"
                                    title="Hapus Obat"
                                    message="Yakin hapus '{{ $obat['namaObat'] }}'?"
                                    confirmText="Ya, hapus" cancelText="Batal"
                                    class="px-2 py-1 text-xs"
                                    :disabled="$isFormLocked">
                                    Hapus
                                </x-confirm-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                                Belum ada obat yang dicatat. Tambahkan di atas kalau pasien sedang konsumsi obat rutin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-border-form>
