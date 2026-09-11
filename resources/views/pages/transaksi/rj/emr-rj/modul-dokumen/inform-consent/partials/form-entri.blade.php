{{-- Partial: form-entri — dipakai ⚡rm-inform-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- Dua layar: formulir hanya dirender di layar 'form' (diForm()), daftar entri di
     layar 'daftar'. Guard dipasang TEPAT di sini, bukan di header modal, supaya
     badge + display pasien tetap tampil di layar daftar. --}}
@if ($this->diForm())
<div class="flex flex-wrap items-center gap-2">
    @if ($editingKey)
        <x-badge variant="warning">Melanjutkan draft {{ $editingKey }}</x-badge>
    @else
        <x-badge variant="info">Entri baru</x-badge>
    @endif
    <span class="text-sm text-muted">Simpan Draft kapan saja; TTD pemberi informasi = validasi penuh + kunci.</span>
</div>

{{-- ══ INFORMASI TINDAKAN ══ --}}
<section class="space-y-4">
    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
        Informasi Tindakan
    </h3>

    <div>
        <x-input-label value="PPA — Profesional Pemberi Asuhan *" class="mb-1" />
        @if (!$isFormLocked)
            <div class="flex items-start gap-2"
                wire:key="ppa-ic-rj-{{ $rjNo ?? 'init' }}-{{ $renderVersions['modal-inform-consent-rj'] ?? 0 }}">
                <div class="flex-1">
                    <x-ppa-combobox wireModel="newConsent.petugasPemeriksa"
                        :disabled="$isFormLocked"
                        inputId="ppa-ic-rj-{{ $rjNo ?? 'init' }}" />
                </div>
                <x-secondary-button type="button" wire:click="isiPpaSebagaiSaya"
                    class="!py-2 shrink-0" title="Isi PPA sebagai akun saya (login)">
                    Saya
                </x-secondary-button>
            </div>
            @if (!empty($newConsent['petugasPemeriksa']))
                <p class="mt-1 text-sm text-gray-500">
                    Dipilih: {{ $newConsent['petugasPemeriksa'] }}@if (!empty($newConsent['petugasPemeriksaCode'])) (ID: {{ $newConsent['petugasPemeriksaCode'] }})@endif
                </p>
            @endif
        @elseif (!empty($newConsent['petugasPemeriksa']))
            <div
                class="p-3 border border-gray-200 bg-gray-50 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                <div class="font-semibold text-gray-800 dark:text-gray-200">
                    {{ $newConsent['petugasPemeriksa'] }}
                </div>
                @if (!empty($newConsent['petugasPemeriksaCode']))
                    <div class="text-sm text-gray-500 mt-0.5">
                        ID: {{ $newConsent['petugasPemeriksaCode'] }}
                    </div>
                @endif
                <div class="mt-1 text-sm text-gray-500">
                    {{ $newConsent['petugasPemeriksaDate'] ?? '-' }}
                </div>
            </div>
        @else
            <p class="text-base italic text-gray-400">Belum dipilih.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <x-input-label value="Diagnosa" class="mb-1" />
            <x-text-input wire:model.live="newConsent.diagnosa"
                placeholder="Diagnosa kerja / penyakit..." :disabled="$isFormLocked"
                class="w-full" />
            <x-input-error :messages="$errors->get('newConsent.diagnosa')" class="mt-1" />
        </div>
        <div>
            <x-input-label value="Komplikasi" class="mb-1" />
            <x-text-input wire:model.live="newConsent.komplikasi"
                placeholder="Kemungkinan komplikasi..." :disabled="$isFormLocked"
                class="w-full" />
            <x-input-error :messages="$errors->get('newConsent.komplikasi')" class="mt-1" />
        </div>
    </div>

    <div>
        <x-input-label value="Nama Tindakan / Prosedur *" class="mb-1" />
        <x-text-input wire:model.live="newConsent.tindakan"
            placeholder="Contoh: Injeksi IM, Hecting Ringan, Nebulizer..."
            :disabled="$isFormLocked" class="w-full" />
        <x-input-error :messages="$errors->get('newConsent.tindakan')" class="mt-1" />
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <x-input-label value="Tujuan Tindakan / Terapi" class="mb-1" />
            <x-textarea wire:model.live="newConsent.tujuan" rows="3"
                placeholder="Uraian singkat mengenai tujuan tindakan..."
                :disabled="$isFormLocked" />
        </div>

        <div>
            <x-input-label value="Risiko Tindakan / Terapi" class="mb-1" />
            <x-textarea wire:model.live="newConsent.resiko" rows="3"
                placeholder="Kemungkinan risiko / efek samping..."
                :disabled="$isFormLocked" />
        </div>

        <div>
            <x-input-label value="Alternatif Tindakan / Terapi" class="mb-1" />
            <x-textarea wire:model.live="newConsent.alternatif" rows="3"
                placeholder="Alternatif lain yang dapat dilakukan..."
                :disabled="$isFormLocked" />
        </div>
    </div>

    <div class="md:max-w-xs">
        <x-input-label value="Persetujuan *" class="mb-1" />
        <x-select-input wire:model.live="newConsent.agreement" :disabled="$isFormLocked"
            class="w-full">
            @foreach ($agreementOptions as $opt)
                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('newConsent.agreement')" class="mt-1" />
    </div>

    @if (($newConsent['agreement'] ?? '1') === '1')
        <div
            class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold">Pasien MENYETUJUI tindakan</p>
                <p class="mt-0.5">
                    Setelah ditandatangani, dokumen dicetak sebagai
                    <strong>Persetujuan Tindakan Medis (Inform Consent)</strong> dan tindakan
                    dapat dilakukan.
                </p>
            </div>
        </div>
    @else
        <div
            class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-200">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <p class="font-semibold">Pasien MENOLAK tindakan</p>
                <p class="mt-0.5">
                    Dokumen akan tercatat sebagai
                    <strong>Penolakan Tindakan Medis</strong>. Pasien/wali memahami risiko medis
                    atas penolakan tersebut dan bersedia menandatangani sebagai bukti penolakan.
                    Tindakan tidak akan dilakukan.
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

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Pasien / Wali --}}
        <div class="flex flex-col">
            <div
                class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                Pasien / Wali
            </div>
            <x-input-error :messages="$errors->get('signature')" class="mb-2" />
            @if (!empty($signature))
                <x-signature.signature-result :signature="$signature" :date="''"
                    :disabled="$isFormLocked" wireMethod="clearSignature" />
            @elseif (!$isFormLocked)
                <x-signature.signature-pad wireMethod="setSignature" />
            @else
                <p class="py-8 text-base italic text-center text-gray-400">Belum
                    ditandatangani.</p>
            @endif

            <div class="mt-3">
                <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                <x-text-input wire:model.live="newConsent.wali"
                    placeholder="Nama lengkap pasien atau wali..." :disabled="$isFormLocked"
                    class="w-full" />
                <x-input-error :messages="$errors->get('newConsent.wali')" class="mt-1" />
            </div>

            <div class="mt-2">
                <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                <x-select-input wire:model.live="newConsent.waliHubungan"
                    :disabled="$isFormLocked" class="w-full">
                    <option value="">— Pilih hubungan —</option>
                    @foreach ($waliHubunganOptions as $opt)
                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('newConsent.waliHubungan')"
                    class="mt-1" />
            </div>
        </div>

        {{-- Saksi --}}
        <div class="flex flex-col">
            <div
                class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                Saksi
            </div>
            <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />
            @if (!empty($signatureSaksi))
                <x-signature.signature-result :signature="$signatureSaksi" :date="''"
                    :disabled="$isFormLocked" wireMethod="clearSignatureSaksi" />
            @elseif (!$isFormLocked)
                <x-signature.signature-pad wireMethod="setSignatureSaksi" />
            @else
                <p class="py-8 text-base italic text-center text-gray-400">Belum
                    ditandatangani.</p>
            @endif

            <div class="mt-3">
                <x-input-label value="Nama Saksi *" class="mb-1" />
                <x-text-input wire:model.live="newConsent.saksi" placeholder="Nama saksi..."
                    :disabled="$isFormLocked" class="w-full" />
                <x-input-error :messages="$errors->get('newConsent.saksi')" class="mt-1" />
            </div>
        </div>

        {{-- Pemberi Informasi — stempel baku x-signature.ttd-petugas.
             Kartu stempel bespoke (nama/Kode/tanggal rata tengah) DILARANG:
             komponen ini menampilkan gambar TTD user (myuser_ttd_image) di kotak
             putih selebar kolom, sejajar kolom TTD pasien & saksi. --}}
        <div class="flex flex-col">
            <div
                class="mb-2 text-sm font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                Pemberi Informasi
            </div>
            <x-input-error :messages="$errors->get('newConsent.dokter')" class="mb-2" />

            <x-signature.ttd-petugas :framed="false" :locked="$isFormLocked"
                :allowClear="false" :ttd="$newConsent['dokter'] ?? ''"
                :code="$newConsent['dokterCode'] ?? ''" :date="$newConsent['dokterDate'] ?? ''"
                sign="setDokterPenjelas" nameLabel="Pemberi Informasi" dateLabel="Waktu TTD"
                signLabel="TTD Pemberi Informasi & Kunci" />
        </div>

    </div>
</section>
@endif
