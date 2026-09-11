{{-- pages/transaksi/rj/emr-rj/penilaian/tabs/resiko-jatuh-tab.blade.php --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Penilaian Risiko Jatuh')" :align="__('start')" :bgcolor="__('bg-gray-50')">
            <div class="mt-4 space-y-4">

                <div>
                    <x-input-label value="Risiko Jatuh" :required="true" />
                    <x-select-input wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuh"
                        class="w-full mt-1">
                        <option value="Tidak">Tidak</option>
                        <option value="Ya">Ya</option>
                    </x-select-input>
                    <x-input-error :messages="$errors->get('formEntryResikoJatuh.resikoJatuh.resikoJatuh')" class="mt-1" />
                </div>

                @if ($formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya')
                    <div>
                        <x-input-label value="Tanggal Penilaian" :required="true" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryResikoJatuh.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryResikoJatuh.tglPenilaian')" class="w-full" />
                            <x-outline-button wire:click="setTglPenilaianResikoJatuh" class="whitespace-nowrap">
                                Sekarang
                            </x-outline-button>
                        </div>
                        <x-input-error :messages="$errors->get('formEntryResikoJatuh.tglPenilaian')" class="mt-1" />
                    </div>
                @endif

                @if ($formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya')
                    <div>
                        <x-input-label value="Metode" :required="true" />
                        <x-select-input
                            wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode"
                            class="w-full mt-1">
                            <option value="">-- Pilih Metode --</option>
                            <option value="Skala Morse">Skala Morse</option>
                            <option value="Humpty Dumpty">Humpty Dumpty</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode')" class="mt-1" />
                    </div>
                @endif

                @if (
                    $formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya' &&
                        $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] === 'Skala Morse')
                    <x-border-form :title="__('Skala Morse')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                    Skor:
                                    {{ $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] }}
                                </span>
                                @if ($formEntryResikoJatuh['resikoJatuh']['kategoriResiko'])
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ $formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] === 'Tinggi'
                                            ? 'bg-red-100 text-red-700'
                                            : ($formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] === 'Sedang'
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : 'bg-green-100 text-green-700') }}">
                                        {{ $formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-400">Interpretasi: &lt;25 Rendah | 25–44 Sedang | ≥45
                                    Tinggi</span>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($skalaMorseOptions as $key => $options)
                                    <div>
                                        <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                        <x-select-input
                                            wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.dataResikoJatuh.{{ $key }}"
                                            class="w-full mt-1">
                                            <option value="">-- Pilih --</option>
                                            @foreach ($options as $opt)
                                                <option value="{{ $opt[$key] }}">{{ $opt[$key] }} (Skor:
                                                    {{ $opt['score'] }})</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-border-form>
                @endif

                @if (
                    $formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya' &&
                        $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] === 'Humpty Dumpty')
                    <x-border-form :title="__('Humpty Dumpty')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                    Skor:
                                    {{ $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] }}
                                </span>
                                @if ($formEntryResikoJatuh['resikoJatuh']['kategoriResiko'])
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ $formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] === 'Tinggi'
                                            ? 'bg-red-100 text-red-700'
                                            : ($formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] === 'Sedang'
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : 'bg-green-100 text-green-700') }}">
                                        {{ $formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-400">Interpretasi: &lt;12 Rendah | 12–15 Sedang | ≥16
                                    Tinggi</span>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($humptyDumptyOptions as $key => $options)
                                    <div>
                                        <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                        <x-select-input
                                            wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.dataResikoJatuh.{{ $key }}"
                                            class="w-full mt-1">
                                            <option value="">-- Pilih --</option>
                                            @foreach ($options as $opt)
                                                <option value="{{ $opt[$key] }}">{{ $opt[$key] }} (Skor:
                                                    {{ $opt['score'] }})</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-border-form>
                @endif

                @if ($formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya')
                    <div>
                        <x-input-label value="Rekomendasi" />
                        <x-textarea wire:model="formEntryResikoJatuh.resikoJatuh.rekomendasi" class="w-full mt-1"
                            rows="2" />
                    </div>
                @endif

                @if ($formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya')
                    <div class="flex justify-end pt-2">
                        <x-primary-button wire:click="addAssessmentResikoJatuh" wire:loading.attr="disabled"
                            wire:target="addAssessmentResikoJatuh">
                            <span wire:loading.remove wire:target="addAssessmentResikoJatuh">Simpan Penilaian Risiko
                                Jatuh</span>
                            <span wire:loading wire:target="addAssessmentResikoJatuh">Menyimpan...</span>
                        </x-primary-button>
                    </div>
                @endif
            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarPoliRJ['penilaian']['resikoJatuh']))
        <x-border-form :title="__('Riwayat Penilaian Risiko Jatuh')" :align="__('start')" :bgcolor="__('bg-white')">
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="ds-table ds-table-entri">
                    <thead>
                        <tr>
                            <th>Tgl Penilaian</th>
                            <th>Petugas</th>
                            <th>Risiko</th>
                            <th>Metode</th>
                            <th>Skor</th>
                            <th>Kategori</th>
                            <th>Rekomendasi</th>
                            @if (!$isFormLocked)
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($dataDaftarPoliRJ['penilaian']['resikoJatuh'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['resikoJatuh']['kategoriResiko'] ?? '-';
                                $rowBg = match ($kat) {
                                    'Tinggi'
                                        => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    'Sedang'
                                        => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    'Rendah'
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
                                        {{ ($row['resikoJatuh']['resikoJatuh'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['resikoJatuh']['resikoJatuh'] ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    {{ $row['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }}</td>
                                <td class="ds-td-strong">
                                    {{ $row['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }}
                                </td>
                                <td>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $kat === 'Tinggi' ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="text-muted dark:text-gray-400">{{ $row['resikoJatuh']['rekomendasi'] ?? '-' }}
                                </td>
                                @if (!$isFormLocked)
                                    <td>
                                        <x-hapus-button :action="'removeAssessmentResikoJatuh(' . $i . ')'"
                                            title="Hapus Penilaian Risiko Jatuh" message="Hapus data risiko jatuh ini?" />
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian risiko jatuh.</p>
    @endif

</div>
