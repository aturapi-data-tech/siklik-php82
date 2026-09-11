{{-- Partial: modal-footer — dipakai ⚡rm-inform-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- FOOTER --}}
<div
    class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-wrap items-center justify-end gap-3">
        @if ($this->diForm())
            <x-secondary-button type="button" wire:click="kembaliKeDaftar">
                Kembali ke Daftar
            </x-secondary-button>
            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                wire:target="saveDraft" class="gap-2 min-w-[180px] justify-center">
                <span wire:loading.remove wire:target="saveDraft">{{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}</span>
                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" />
                    Menyimpan...</span>
            </x-primary-button>
        @else
            <x-secondary-button wire:click="closeModal">
                Tutup
            </x-secondary-button>
            @if ($rjNo && !$isFormLocked)
                <x-primary-button type="button" wire:click="tambahEntri" wire:loading.attr="disabled"
                    wire:target="tambahEntri" class="gap-2 min-w-[180px] justify-center">
                    Isi Formulir Baru
                </x-primary-button>
            @endif
        @endif
    </div>
</div>
