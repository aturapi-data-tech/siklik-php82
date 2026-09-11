{{-- Partial: form-persetujuan — dipakai ⚡rm-general-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- ══ ISI PERSETUJUAN ══ --}}
<section>
    {{-- Isi Persetujuan — entry pihak akses dirender via slot,
         langsung di bawah paragraf izin akses info medis --}}
    <x-consent.general-consent-body context="rj" :showReleaseInfo="true"
        :pihakInfoList="$pihakInfoMedis">

        {{-- Tabel entry bergaris tipis — selaras tabel di cetakan (No/Nama/Hubungan/No. HP) --}}
        <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
            <div class="grid grid-cols-12 gap-2 px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 bg-gray-50 border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                <span class="col-span-1 text-center">#</span>
                <span class="col-span-4">Nama</span>
                <span class="col-span-4">Hubungan</span>
                <span class="col-span-2">No. HP</span>
                <span class="col-span-1"></span>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($pihakInfoMedis as $i => $row)
                <div wire:key="pihak-info-rj-{{ $i }}"
                    class="grid grid-cols-12 gap-2 items-start px-2 py-1.5">
                    <span class="col-span-1 pt-2 text-sm text-center text-gray-500">
                        {{ $i + 1 }}
                    </span>
                    {{-- Enter-chain ala e-resep ($refs antar field; baris baru via getElementById
                         karena elemennya belum dirender saat Enter ditekan) --}}
                    <x-text-input id="pihak-nama-rj-{{ $i }}"
                        wire:model.live.debounce.500ms="pihakInfoMedis.{{ $i }}.nama"
                        placeholder="Nama" :disabled="$formReadOnly"
                        x-on:keydown.enter.prevent="$refs.pihakHub{{ $i }}.focus()"
                        class="col-span-4 text-sm" />
                    <x-text-input x-ref="pihakHub{{ $i }}"
                        wire:model.live.debounce.500ms="pihakInfoMedis.{{ $i }}.hubungan"
                        placeholder="Hubungan (cth: anak, istri)" :disabled="$formReadOnly"
                        x-on:keydown.enter.prevent="$refs.pihakHp{{ $i }}.focus()"
                        class="col-span-4 text-sm" />
                    <x-text-input x-ref="pihakHp{{ $i }}"
                        wire:model.live.debounce.500ms="pihakInfoMedis.{{ $i }}.noHp"
                        placeholder="No. HP" :disabled="$formReadOnly"
                        x-on:keydown.enter.prevent="$el.blur(); $wire.addPihakInfo().then(() => setTimeout(() => document.getElementById('pihak-nama-rj-{{ $i + 1 }}')?.focus(), 100))"
                        class="col-span-2 text-sm" />
                    @if (!$formReadOnly)
                        <div class="col-span-1">
                            <x-hapus-button :action="'removePihakInfo(' . $i . ')'"
                                title="Hapus Pihak Info Medis" message="Hapus item ini?" />
                        </div>
                    @endif
                </div>
            @endforeach
            </div>
        </div>

        @if (!$formReadOnly)
            <div class="flex justify-end">
                <x-primary-button type="button" wire:click="addPihakInfo"
                    class="text-sm py-1 px-2">
                    + Tambah
                </x-primary-button>
            </div>
        @endif
    </x-consent.general-consent-body>
</section>

{{-- ══ DATA PERSETUJUAN ══ --}}
<section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
        Data Persetujuan
    </h3>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
            <x-text-input wire:model.live="wali"
                placeholder="Nama lengkap pasien atau wali..." :error="$errors->has('wali')"
                :disabled="$formReadOnly" class="w-full" />
            <x-input-error :messages="$errors->get('wali')" class="mt-1" />
        </div>

        <div>
            <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
            <x-select-input wire:model.live="waliHubungan"
                :error="$errors->has('waliHubungan')" :disabled="$formReadOnly" class="w-full">
                <option value="">— Pilih hubungan —</option>
                @foreach ($waliHubunganOptions as $opt)
                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('waliHubungan')" class="mt-1" />
        </div>

        <div>
            <x-input-label value="Persetujuan Pelayanan *" class="mb-1" />
            <x-select-input wire:model.live="agreement" :error="$errors->has('agreement')"
                :disabled="$formReadOnly" class="w-full">
                @foreach ($agreementOptions as $opt)
                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('agreement')" class="mt-1" />
        </div>

    </div>

    @if (($agreement ?? '1') === '1')
        <div
            class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold">Pasien MENYETUJUI General Consent</p>
                <p class="mt-0.5">
                    Persetujuan umum atas pelayanan rawat jalan, hak &amp; tanggung jawab, serta
                    perlindungan data. Tindakan medis spesifik tetap memerlukan
                    <strong>Inform Consent</strong> tersendiri.
                </p>
            </div>
        </div>
    @endif
</section>

{{-- ══ TANDA TANGAN ══ --}}
<section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
        Tanda Tangan
    </h3>

    <x-input-error :messages="$errors->get('signature')" />

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        {{-- Pasien / Wali --}}
        <div class="flex flex-col">
            <div
                class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                Pasien / Wali
            </div>
            @if (!empty($consent['signature']))
                <x-signature.signature-result :signature="$consent['signature']"
                    :date="$consent['signatureDate'] ?? ''" :disabled="$formReadOnly"
                    wireMethod="clearSignature" />
            @elseif (!$formReadOnly)
                <x-signature.signature-pad wireMethod="setSignature" />
            @else
                <p class="py-8 text-base italic text-center text-gray-400">Belum
                    ditandatangani.</p>
            @endif
        </div>

        {{-- Petugas Pemberi Penjelasan — stempel baku x-signature.ttd-petugas.
             Kartu stempel bespoke (nama/Kode/tanggal rata tengah) DILARANG:
             komponen ini menampilkan gambar TTD user (myuser_ttd_image) di kotak
             putih selebar kolom, sejajar dengan kolom TTD pasien. --}}
        <div class="flex flex-col">
            <div
                class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                Petugas Pemberi Penjelasan
            </div>

            <x-signature.ttd-petugas :framed="false" :locked="$isFormLocked || $this->entriFinal()"
                :allowClear="false" :ttd="$consent['petugasPemeriksa'] ?? ''"
                :code="$consent['petugasPemeriksaCode'] ?? ''"
                :date="$consent['petugasPemeriksaDate'] ?? ''" sign="setPetugasPemeriksa"
                nameLabel="Petugas Pemberi Penjelasan" dateLabel="Waktu TTD"
                signLabel="TTD sebagai Petugas & Kunci" />

            {{-- Buka kunci = cabut TTD petugas. Kuning (aksi koreksi), bukan merah,
                 dan hanya dirender untuk role berwenang (gate dokumen.bukaKunci). --}}
            @if ($this->entriFinal() && !$isFormLocked)
                @can('dokumen.bukaKunci')
                    <div class="pt-3">
                        <x-confirm-button variant="warning-soft" action="cabutTtdPetugas()"
                            title="Buka Kunci General Consent"
                            message="Tanda tangan petugas akan dicabut supaya dokumen bisa diubah lagi. Tanda tangan pasien/wali tetap dipertahankan. Lanjutkan?"
                            confirmText="Ya, buka kunci" cancelText="Batal"
                            class="justify-center w-full">
                            Buka Kunci
                        </x-confirm-button>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</section>
