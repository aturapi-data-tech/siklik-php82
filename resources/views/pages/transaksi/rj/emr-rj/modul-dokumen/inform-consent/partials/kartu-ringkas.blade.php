{{-- Partial: kartu-ringkas — dipakai ⚡rm-inform-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- ══ SUMMARY CARD (inline) ══ --}}
@php $icCount = count($consentList ?? []); @endphp

<div
    class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex-1 space-y-3">
            {{-- Baris judul: judul · badge · deskripsi (min-w-0 wajib, kalau tidak truncate tak menggigit) --}}
            <div class="flex items-baseline flex-1 gap-2 min-w-0">
                <h3 class="text-base font-semibold truncate shrink-0 text-gray-800 dark:text-gray-200">
                    Inform Consent
                </h3>
                @if ($icCount > 0)
                    <x-badge variant="success" class="shrink-0 whitespace-nowrap">{{ $icCount }} tindakan</x-badge>
                @else
                    <x-badge variant="warning" class="shrink-0 whitespace-nowrap">Belum ada</x-badge>
                @endif

                <x-deskripsi-ringkas>
                    Persetujuan tindakan medis per-tindakan: diagnosa, tujuan, risiko dan alternatif tindakan,
                    beserta tanda tangan pasien/wali, saksi, dan pemberi informasi. Setiap tindakan berdiri
                    sendiri sebagai satu entri.
                </x-deskripsi-ringkas>
            </div>

            @if ($icCount > 0)
                <ul class="space-y-1 text-base text-gray-600 dark:text-gray-300 list-disc pl-5">
                    @foreach (array_slice($this->daftarEntri(), 0, 3) as $ic)
                        <li>
                            <span
                                class="font-medium">{{ \Illuminate\Support\Str::limit($ic['tindakan'] ?? '-', 60) }}</span>
                            @if (!empty($ic['signatureDate']))
                                <span class="text-sm text-gray-400">— {{ $ic['signatureDate'] }}</span>
                            @endif
                        </li>
                    @endforeach
                    @if ($icCount > 3)
                        <li class="text-sm italic text-gray-400">
                            +{{ $icCount - 3 }} lainnya…
                        </li>
                    @endif
                </ul>
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
                    Buka Inform Consent
                </span>
                <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                    <x-loading class="w-4 h-4" /> Memuat...
                </span>
            </x-primary-button>
        </div>
    </div>
</div>
