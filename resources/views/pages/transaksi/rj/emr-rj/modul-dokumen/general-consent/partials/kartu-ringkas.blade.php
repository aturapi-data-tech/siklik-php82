{{-- Partial: kartu-ringkas — dipakai ⚡rm-general-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- ══ SUMMARY CARD (inline) ══ --}}
@php
    $gc = $dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [];
    $gcSigned = !empty($gc['signature']);
@endphp

<div
    class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex-1 space-y-3">
            {{-- Baris judul: judul · badge · deskripsi (min-w-0 wajib, kalau tidak truncate tak menggigit) --}}
            <div class="flex items-baseline flex-1 gap-2 min-w-0">
                <h3 class="text-base font-semibold truncate shrink-0 text-gray-800 dark:text-gray-200">
                    General Consent
                </h3>
                @if ($this->entriFinal())
                    <x-badge variant="info" class="shrink-0 whitespace-nowrap">Terkunci</x-badge>
                @elseif ($gcSigned)
                    <x-badge variant="success" class="shrink-0 whitespace-nowrap">Sudah ditandatangani</x-badge>
                @else
                    <x-badge variant="warning" class="shrink-0 whitespace-nowrap">Belum ditandatangani</x-badge>
                @endif

                <x-deskripsi-ringkas>
                    Persetujuan umum pasien terhadap pelayanan rawat jalan, hak &amp; tanggung jawab pasien,
                    pihak yang boleh menerima informasi medis, serta perlindungan data pribadi. Dikunci oleh
                    tanda tangan petugas pemberi penjelasan.
                </x-deskripsi-ringkas>
            </div>

            @if ($gcSigned)
                <dl class="grid grid-cols-1 gap-2 text-base sm:grid-cols-4 text-gray-600 dark:text-gray-300">
                    <div>
                        <dt class="text-sm uppercase text-gray-400">Wali</dt>
                        <dd class="font-medium">{{ $gc['wali'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm uppercase text-gray-400">Persetujuan</dt>
                        <dd class="font-medium">
                            {{ ($gc['agreement'] ?? '1') === '1' ? 'Setuju' : 'Tidak Setuju' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm uppercase text-gray-400">Tanggal TTD</dt>
                        <dd class="font-medium">{{ $gc['signatureDate'] ?? '-' }}</dd>
                    </div>
                    <div>
                        {{-- Nama petugas hanya ditampilkan bila entri final (aturan TTD #12d) --}}
                        <dt class="text-sm uppercase text-gray-400">Petugas (TTD)</dt>
                        <dd class="font-medium">
                            @if ($this->entriFinal())
                                {{ $gc['petugasPemeriksa'] }}
                            @else
                                <x-badge variant="danger">Belum TTD</x-badge>
                            @endif
                        </dd>
                    </div>
                </dl>
            @endif
        </div>

        <div class="flex shrink-0">
            <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    Buka General Consent
                </span>
                <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                    <x-loading class="w-4 h-4" /> Memuat...
                </span>
            </x-primary-button>
        </div>
    </div>
</div>
