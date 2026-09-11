{{-- Formulir Rujukan Kompetensi — kelompok isian Langkah 1 & 2.

    Partial ini di-@include dari ⚡rm-rujukan-kompetensi-rj-actions.blade.php dan
    TIDAK mewarisi blok `use` komponennya — nama kelas ditulis lengkap.

    Tombol aksinya TIDAK di sini: semua ada di footer sticky modal, supaya
    urutannya (Ambil Kriteria → Cari Kandidat → Kirim) selalu terlihat.
--}}

@php
    $kriteriaTerpilih = $this->kriteriaTerpilih();
    $butuhIcd9 = $this->kriteriaButuhIcd9($kriteriaTerpilih);
    $kabupatenOpsi = $this->kabupatenPropinsiIni();
@endphp

<div class="grid items-start grid-cols-1 gap-4 xl:grid-cols-2">

    {{-- ══ LANGKAH 1 — DIAGNOSA & KRITERIA ══ --}}
    <div class="p-3 space-y-3 border rounded-lg bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700">
        <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200">
            <x-step-number :n="1" /><span class="ml-0.5">Diagnosa &amp; Kriteria Rujukan</span>
        </p>

        {{-- Diagnosa: bawaannya diagnosa utama EMR, boleh diganti lewat LOV. --}}
        <div class="max-w-md">
            <livewire:lov.diagnosa.lov-diagnosa label="Diagnosa Rujukan (ICD-10)"
                target="rujukanKompetensiDiagnosaRJ"
                :initialDiagnosaId="$formRujukan['kodeDiagnosa'] ?: null"
                :initialDiagnosaDesc="$formRujukan['diagnosaDesc'] ?: null"
                :disabled="$isFormLocked"
                wire:key="lov-diagnosa-rujukan-kompetensi-{{ $rjNo }}" />
            <p class="mt-1 text-xs text-muted-soft">
                Bawaannya diagnosa utama EMR. Pilih kode paling rinci — diagnosa terlalu umum sering tidak punya
                kriteria (A02 kosong, A02.9 jalan).
                @if (filled($formRujukan['kodeDiagnosa'] ?? ''))
                    <span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['kodeDiagnosa'] }}</span>
                @endif
            </p>
        </div>

        @if ($infoKriteria !== '')
            <p class="text-sm {{ str_starts_with($infoKriteria, '✓') ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                {{ $infoKriteria }}
            </p>
        @endif

        {{-- Radio kriteria — TEPAT SATU. linkId dinamis per diagnosa, jadi daftar
             ini selalu datang dari server (tombol Ambil Kriteria di footer). --}}
        @if (!empty($formRujukan['kriteriaList']))
            <div class="space-y-2">
                <x-input-label value="Kriteria Rujukan" :required="true" class="mb-1" />
                <p class="text-xs text-muted-soft">{{ \App\Support\Rujukan\RujukanKompetensiOptions::PETUNJUK_UMUM }}</p>

                <div class="grid grid-cols-1 gap-2">
                    @foreach ($formRujukan['kriteriaList'] as $kriteria)
                        @php
                            $jenisKriteria = \App\Support\Rujukan\RujukanKompetensiOptions::jenisKriteria($kriteria['text']);
                        @endphp
                        <div wire:key="kriteria-{{ $rjNo }}-{{ md5($kriteria['linkId']) }}">
                            <x-radio-button :label="$kriteria['text']" :value="$kriteria['linkId']"
                                name="kriteriaPilih-{{ $rjNo }}" :disabled="$isFormLocked"
                                wire:model.live="formRujukan.kriteriaPilih" />
                            @if ($jenisKriteria !== '')
                                <p class="ml-6 text-xs text-muted-soft">
                                    {{ \App\Support\Rujukan\RujukanKompetensiOptions::PETUNJUK_KRITERIA[$jenisKriteria] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- ICD-9-CM wajib untuk Tindakan Medis: kodenya ikut menentukan
                     kandidat, jadi dipilih lewat LOV master — bukan diketik bebas. --}}
                @if ($butuhIcd9)
                    <div class="max-w-md space-y-2">
                        <livewire:lov.procedure.lov-procedure label="Tindakan Rujukan (ICD-9-CM)"
                            target="rujukanKompetensiIcd9RJ"
                            :initialProcedureId="$formRujukan['kriteriaIcd9'] ?: null"
                            :disabled="$isFormLocked"
                            wire:key="lov-procedure-rujukan-kompetensi-{{ $rjNo }}" />
                        <p class="text-xs text-muted-soft">
                            Wajib &amp; harus sesuai diagnosa — salah kode menghasilkan daftar faskes yang keliru
                            tanpa pesan error.
                            @if (filled($formRujukan['kriteriaIcd9'] ?? ''))
                                <span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['kriteriaIcd9'] }}</span>
                                {{ $formRujukan['kriteriaIcd9Desc'] }}
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        @else
            <p class="text-sm text-muted-soft">
                Kriteria belum dimuat. Tekan <strong>Ambil Kriteria</strong> di bawah setelah diagnosa dipilih.
            </p>
        @endif
    </div>

    {{-- ══ LANGKAH 2 — TUJUAN & KANDIDAT ══ --}}
    <div class="p-3 space-y-3 border rounded-lg bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700">
        <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200">
            <x-step-number :n="2" /><span class="ml-0.5">Tujuan Rujukan &amp; Kandidat Faskes</span>
        </p>

        <x-rujukan-kompetensi.identitas-kiriman :encounterId="$this->encounterUuid()"
            :noKunjungan="'RJ-' . $rjNo" :kodeFaskes="$this->kodeFaskesSatuSehat()" />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {{-- Wilayah: pilihannya datang dari server bersama kriteria, bukan dari
                 master wilayah kita — kodenya harus persis yang dikenal gateway. --}}
            <div>
                <x-input-label value="Provinsi Tujuan" :required="true" class="mb-1" />
                <x-select-input wire:change="pilihPropinsi($event.target.value)"
                    :disabled="$isFormLocked || empty($formRujukan['wilayahList'])" class="w-full">
                    <option value="">— Pilih Provinsi —</option>
                    @foreach ($formRujukan['wilayahList'] ?? [] as $propinsi)
                        <option value="{{ $propinsi['kode'] }}" @selected(($formRujukan['kodePropinsi'] ?? '') === $propinsi['kode'])>
                            {{ $propinsi['kode'] }} — {{ $propinsi['nama'] }}
                        </option>
                    @endforeach
                </x-select-input>
                @if (empty($formRujukan['wilayahList']))
                    <p class="mt-1 text-xs text-muted-soft">Terisi setelah Ambil Kriteria dijalankan.</p>
                @endif
            </div>

            <div>
                <x-input-label value="Kabupaten/Kota Tujuan" class="mb-1" />
                <x-select-input wire:change="pilihKabupaten($event.target.value)"
                    :disabled="$isFormLocked || empty($kabupatenOpsi)" class="w-full">
                    <option value="">— Seluruh provinsi —</option>
                    @foreach ($kabupatenOpsi as $kabupaten)
                        <option value="{{ $kabupaten['kode'] }}" @selected(($formRujukan['kodeKabupaten'] ?? '') === $kabupaten['kode'])>
                            {{ $kabupaten['kode'] }} — {{ $kabupaten['nama'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <p class="mt-1 text-xs text-muted-soft">Boleh dikosongkan — pencarian melebar ke seluruh provinsi.</p>
            </div>

            <div>
                <x-input-label value="Spesialis" :required="true" class="mb-1" />
                <x-select-input wire:change="pilihSpesialis($event.target.value)"
                    :disabled="$isFormLocked" class="w-full">
                    <option value="">— Pilih Spesialis —</option>
                    @foreach ($spesialisList as $spesialis)
                        <option value="{{ $spesialis['kd'] }}" @selected(($formRujukan['kodeSpesialis'] ?? '') === $spesialis['kd'])>
                            {{ $spesialis['kd'] }} — {{ $spesialis['nm'] }}
                        </option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label value="Subspesialis" :required="true" class="mb-1" />
                <x-select-input wire:change="pilihSubSpesialis($event.target.value)"
                    :disabled="$isFormLocked || empty($subSpesialisList)" class="w-full">
                    <option value="">— Pilih Subspesialis —</option>
                    @foreach ($subSpesialisList as $subSpesialis)
                        <option value="{{ $subSpesialis['kd'] }}" @selected(($formRujukan['kodeSubSpesialis'] ?? '') === $subSpesialis['kd'])>
                            {{ $subSpesialis['kd'] }} — {{ $subSpesialis['nm'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <p class="mt-1 text-xs text-muted-soft">Kandidat dicari berdasarkan subspesialis, bukan spesialis.</p>
            </div>

            <div>
                <x-input-label value="Sarana" class="mb-1" />
                <x-select-input wire:change="pilihSarana($event.target.value)"
                    :disabled="$isFormLocked" class="w-full">
                    <option value="">— Tanpa sarana khusus —</option>
                    @foreach ($saranaList as $sarana)
                        <option value="{{ $sarana['kd'] }}" @selected(($formRujukan['kodeSarana'] ?? '') === $sarana['kd'])>
                            {{ $sarana['kd'] }} — {{ $sarana['nm'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <p class="mt-1 text-xs text-muted-soft">Isi hanya bila pasien butuh sarana tertentu.</p>
            </div>

            <div>
                <x-input-label value="Estimasi Tgl. Rujuk" :required="true" class="mb-1" />
                <x-text-input wire:model.blur="formRujukan.estimasiRujuk" placeholder="dd/mm/yyyy"
                    :disabled="$isFormLocked" class="w-full" />
                <p class="mt-1 text-xs text-muted-soft">Kapan pasien direncanakan dilayani di faskes tujuan; boleh hari ini.</p>
            </div>
        </div>

        <div>
            <x-input-label value="Catatan Rujukan" class="mb-1" />
            <x-text-input wire:model.blur="formRujukan.catatan" placeholder="Catatan untuk faskes tujuan"
                :disabled="$isFormLocked" class="w-full" />
        </div>

        @if ($infoKandidat !== '')
            <p class="text-sm {{ str_starts_with($infoKandidat, '✓') || str_starts_with($infoKandidat, 'Tujuan:') ? 'text-green-700 dark:text-green-300' : 'text-muted-soft' }}">
                {{ $infoKandidat }}
            </p>
        @endif

        <x-rujukan-kompetensi.kandidat-tabel :rows="$formRujukan['kandidatList'] ?? []"
            :selectedIndex="$formRujukan['kandidatIdx'] ?? null" :disabled="$isFormLocked" />
    </div>

</div>
