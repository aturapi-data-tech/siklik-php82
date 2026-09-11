{{-- Partial: modal-footer — dipakai ⚡rm-general-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- FOOTER --}}
<div
    class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-wrap items-center justify-end gap-3">
        <x-secondary-button wire:click="closeModal">
            Tutup
        </x-secondary-button>

        @if ($rjNo)
            {{-- Cetak = komponen baku x-cetak-button (40px, spinner otomatis) --}}
            <x-cetak-button wire:click="cetak" label="Cetak General Consent" />

            {{-- Footer cukup Simpan Draft; pengunci ada di TTD petugas (aturan #1) --}}
            @if (!$this->formReadOnly())
                <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                    wire:target="save" class="gap-2 min-w-[160px] justify-center">
                    <span wire:loading.remove wire:target="save">Simpan Draft</span>
                    <span wire:loading wire:target="save"><x-loading class="w-4 h-4" />
                        Menyimpan...</span>
                </x-primary-button>
            @endif
        @endif
    </div>
</div>
