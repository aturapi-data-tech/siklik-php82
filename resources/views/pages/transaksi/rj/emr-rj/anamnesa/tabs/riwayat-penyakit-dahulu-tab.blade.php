<x-border-form :title="__('Riwayat & Alergi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- Riwayat Penyakit Dahulu --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                value="Riwayat Penyakit Dahulu" :required="true" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                wire:model.live="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" :error="$errors->has('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" :disabled="$isFormLocked" :rows="3"
                class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
        </div>

        {{-- Ada Alergi? — "punya alergi atau tidak?" dan "alergi terhadap apa?" itu DUA
             pertanyaan. Jawaban "Tidak" memasang SNOMED 716186003 di server (bukan lewat
             LOV zat): 716186003 itu konsep *situation*, bukan zat, jadi ditolak valueset
             substance-code yang dipakai LOV. Lihat App\Support\Terminologi\AlergiSnomed. --}}
        <div>
            <x-input-label value="Ada Alergi?" :required="false" />
            <div class="flex gap-4 mt-2">
                @foreach (['Ya', 'Tidak'] as $opsiAlergi)
                    <x-radio-button :label="$opsiAlergi" :value="$opsiAlergi" name="adaAlergi"
                        wire:model.live="dataDaftarPoliRJ.anamnesa.alergi.adaAlergi" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        @php $adaAlergi = ($dataDaftarPoliRJ['anamnesa']['alergi']['adaAlergi'] ?? 'Tidak') === 'Ya'; @endphp

        @if ($adaAlergi)
            {{-- Alergi (teks) — hanya saat "Ya" --}}
            <div>
                <x-input-label for="dataDaftarPoliRJ.anamnesa.alergi.alergi" value="Alergi" :required="false" />

                <x-textarea id="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                    placeholder="Jenis Alergi — Makanan / Obat / Udara" :error="$errors->has('dataDaftarPoliRJ.anamnesa.alergi.alergi')" :disabled="$isFormLocked"
                    :rows="3" class="w-full mt-1" />

                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.alergi.alergi')" class="mt-1" />
            </div>

            {{-- SNOMED CT — ZAT penyebab alergi (untuk Satu Sehat). Hanya relevan saat "Ya". --}}
            <div>
                <livewire:lov.snomed.lov-snomed
                    target="alergiSnomed"
                    label="Kode SNOMED Zat Penyebab Alergi (Satu Sehat)"
                    placeholder="Ketik nama zat / obat penyebab..."
                    valueSet="substance-code"
                    :initialSnomedCode="$dataDaftarPoliRJ['anamnesa']['alergi']['snomedCode'] ?? null"
                    :disabled="$isFormLocked"
                    wire:key="lov-snomed-alergi-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-rj'] ?? 0 }}"
                />
            </div>
        @else
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Terekam sebagai <span class="font-semibold text-gray-800 dark:text-gray-100">Tidak ada alergi</span>
                <span class="font-mono text-[10px] text-gray-400">716186003</span> untuk Satu Sehat.
            </div>
        @endif

        {{-- ============================================================
             Alergi BPJS PCare — Makanan / Udara / Obat (klinik pratama)

             Kodifikasi BPJS yang BERDIRI SENDIRI, bukan turunan radio "Ada Alergi?" di
             atas (itu SNOMED/Satu Sehat). Sengaja TIDAK ikut disembunyikan saat "Tidak":
             nilainya wajib ikut payload PCare, dan menyembunyikannya bisa meninggalkan
             pilihan lama yang tak terlihat. Default '00' = Tidak Ada sudah selaras.
             ============================================================ --}}
        @php
            $alergi = $dataDaftarPoliRJ['anamnesa']['alergi'] ?? [];
            $alergiBlocks = [
                ['title' => 'Alergi Makanan (BPJS)', 'jenis' => '01', 'method' => 'loadAlergiMakanan',
                 'options' => $alergiMakananOptions, 'kd' => 'alergiMakan'],
                ['title' => 'Alergi Udara (BPJS)',   'jenis' => '02', 'method' => 'loadAlergiUdara',
                 'options' => $alergiUdaraOptions,   'kd' => 'alergiUdara'],
                ['title' => 'Alergi Obat (BPJS)',    'jenis' => '03', 'method' => 'loadAlergiObat',
                 'options' => $alergiObatOptions,    'kd' => 'alergiObat'],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-4 pt-2">
            @foreach ($alergiBlocks as $blk)
                <div>
                    <x-input-label :value="$blk['title']" />
                    <div class="flex gap-1 mt-1">
                        <x-select-input
                            :value="$alergi[$blk['kd']] ?? '00'"
                            wire:change="changeAlergi('{{ $blk['jenis'] }}', $event.target.value)"
                            :disabled="$isFormLocked"
                            class="flex-1">
                            @if (empty($blk['options']))
                                <option value="{{ $alergi[$blk['kd']] ?? '00' }}">
                                    {{ ($alergi[$blk['kd']] ?? '00') }} — {{ $alergi[$blk['kd'] . 'Desc'] ?? 'Tidak Ada' }}
                                </option>
                            @else
                                @foreach ($blk['options'] as $opt)
                                    <option value="{{ $opt['kdAlergi'] }}">
                                        {{ $opt['kdAlergi'] }} — {{ $opt['nmAlergi'] }}
                                    </option>
                                @endforeach
                            @endif
                        </x-select-input>
                        <x-secondary-button type="button"
                            wire:click="{{ $blk['method'] }}" wire:loading.attr="disabled"
                            wire:target="{{ $blk['method'] }}" :disabled="$isFormLocked"
                            class="px-2 whitespace-nowrap"
                            title="Refresh dari BPJS PCare (cache lokal)">
                            <span wire:loading.remove wire:target="{{ $blk['method'] }}">⟳</span>
                            <span wire:loading wire:target="{{ $blk['method'] }}">...</span>
                        </x-secondary-button>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</x-border-form>
