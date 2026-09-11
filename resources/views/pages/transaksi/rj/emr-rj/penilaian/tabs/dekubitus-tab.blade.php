{{-- pages/transaksi/rj/emr-rj/penilaian/tabs/dekubitus-tab.blade.php --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Penilaian Dekubitus (Skala Braden)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
            <div class="mt-4 space-y-4">

                <div>
                    <x-input-label value="Status Dekubitus" :required="true" />
                    <x-select-input wire:model.live="formEntryDekubitus.dekubitus.dekubitus" class="w-full mt-1">
                        <option value="Tidak">Tidak</option>
                        <option value="Ya">Ya</option>
                    </x-select-input>
                    <x-input-error :messages="$errors->get('formEntryDekubitus.dekubitus.dekubitus')" class="mt-1" />
                </div>

                @if (($formEntryDekubitus['dekubitus']['dekubitus'] ?? '') === 'Ya')
                    <div>
                        <x-input-label value="Tanggal Penilaian" :required="true" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryDekubitus.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryDekubitus.tglPenilaian')" class="w-full" />
                            <x-outline-button wire:click="setTglPenilaianDekubitus" class="whitespace-nowrap">
                                Sekarang
                            </x-outline-button>
                        </div>
                        <x-input-error :messages="$errors->get('formEntryDekubitus.tglPenilaian')" class="mt-1" />
                    </div>
                @endif

                @if (($formEntryDekubitus['dekubitus']['dekubitus'] ?? '') === 'Ya')

                    <x-border-form :title="__('Penilaian Skala Braden')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                    Skor: {{ $formEntryDekubitus['dekubitus']['bradenScore'] ?? 0 }}
                                </span>
                                @if ($formEntryDekubitus['dekubitus']['kategoriResiko'] ?? '')
                                    @php $katForm = $formEntryDekubitus['dekubitus']['kategoriResiko']; @endphp
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ in_array($katForm, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($katForm === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $katForm }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-400">Interpretasi: ≤12 Sangat Tinggi | 13–14 Tinggi |
                                    15–18 Sedang | ≥19 Rendah</span>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($bradenScaleOptions as $key => $options)
                                    <div>
                                        <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                        <x-select-input
                                            wire:model.live="formEntryDekubitus.dekubitus.dataBraden.{{ $key }}"
                                            class="w-full mt-1">
                                            <option value="">-- Pilih --</option>
                                            @foreach ($options as $opt)
                                                <option value="{{ $opt['score'] }}">{{ $opt['description'] }} (Skor:
                                                    {{ $opt['score'] }})</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-border-form>

                    <div>
                        <x-input-label value="Rekomendasi" />
                        <x-textarea wire:model="formEntryDekubitus.dekubitus.rekomendasi" class="w-full mt-1"
                            rows="2" />
                    </div>

                @endif {{-- /if dekubitus = Ya --}}

                @if (($formEntryDekubitus['dekubitus']['dekubitus'] ?? '') === 'Ya')
                    <div class="flex justify-end pt-2">
                        <x-primary-button wire:click="addAssessmentDekubitus" wire:loading.attr="disabled"
                            wire:target="addAssessmentDekubitus">
                            <span wire:loading.remove wire:target="addAssessmentDekubitus">Simpan Penilaian Dekubitus</span>
                            <span wire:loading wire:target="addAssessmentDekubitus">Menyimpan...</span>
                        </x-primary-button>
                    </div>
                @endif
            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarPoliRJ['penilaian']['dekubitus']))
        <x-border-form :title="__('Riwayat Penilaian Dekubitus')" :align="__('start')" :bgcolor="__('bg-white')">
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="ds-table ds-table-entri">
                    <thead>
                        <tr>
                            <th>Tgl Penilaian</th>
                            <th>Petugas</th>
                            <th>Dekubitus</th>
                            <th>Skor Braden</th>
                            <th>Kategori Risiko</th>
                            <th>Rekomendasi</th>
                            @if (!$isFormLocked)
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($dataDaftarPoliRJ['penilaian']['dekubitus'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['dekubitus']['kategoriResiko'] ?? '-';
                                $rowBg = match (true) {
                                    in_array($kat, ['Sangat Tinggi', 'Tinggi'])
                                        => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    $kat === 'Sedang'
                                        => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    $kat === 'Rendah'
                                        => 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20',
                                    default => 'hover:bg-gray-50 dark:hover:bg-gray-800',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td>{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ ($row['dekubitus']['dekubitus'] ?? '') === 'Ya' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['dekubitus']['dekubitus'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="ds-td-strong">{{ $row['dekubitus']['bradenScore'] ?? '-' }}</td>
                                <td>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ in_array($kat, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="text-muted dark:text-gray-400">{{ $row['dekubitus']['rekomendasi'] ?? '-' }}</td>
                                @if (!$isFormLocked)
                                    <td>
                                        <x-hapus-button :action="'removeAssessmentDekubitus(' . $i . ')'"
                                            title="Hapus Penilaian Dekubitus" message="Hapus data dekubitus ini?" />
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian dekubitus.</p>
    @endif

</div>
