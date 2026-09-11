{{-- Partial: modal-header — dipakai ⚡rm-general-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- HEADER --}}
<div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
    <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
        style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
    </div>

    <div class="relative flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                    <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        General Consent
                    </h2>
                    <p class="mt-0.5 text-base text-gray-500 dark:text-gray-400">
                        Persetujuan umum pasien rawat jalan — tampilan ini dapat diputar ke arah pasien
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-3">
                <x-badge variant="success">Rawat Jalan</x-badge>
                @if ($this->entriFinal())
                    <x-badge variant="info">Terkunci</x-badge>
                @else
                    <x-badge variant="warning">Draft</x-badge>
                @endif
                @if ($isFormLocked)
                    <x-badge variant="danger">Read Only</x-badge>
                @endif
            </div>
        </div>

        <x-icon-button color="gray" type="button" wire:click="closeModal">
            <span class="sr-only">Close</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </x-icon-button>
    </div>
</div>
