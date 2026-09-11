{{-- Footer aksi modal Rujukan Kompetensi — urutan kiri→kanan mengikuti
    penanda langkah: Ambil Kriteria → Cari Kandidat → Kirim Rujukan, lalu
    Batalkan (hanya bila rujukan sudah terbit) dan Tutup.

    Tombol Kirim & Batalkan dibungkus @can; method servernya tetap memeriksa
    ->can() sendiri sebagai statement pertama, karena wire:click memanggil
    method publik yang bisa ditembus tanpa lewat blade.

    Partial ini di-@include dari ⚡rm-rujukan-kompetensi-rj-actions.blade.php.
--}}

{{-- FOOTER — aksi berurutan, menempel di bawah --}}
<div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted dark:text-gray-400">
            Tiap langkah yang berhasil tersimpan otomatis ke kunjungan ini — aman ditutup lalu dilanjutkan.
        </p>

        <div class="flex flex-wrap items-center justify-end gap-2 ml-auto">
            @if (!$sudahTerkirim)
                <x-secondary-button type="button" wire:click="ambilKriteria" wire:loading.attr="disabled"
                    wire:target="ambilKriteria" :disabled="$isFormLocked"
                    title="Langkah 1 — tarik daftar kriteria sesuai diagnosa">
                    <span wire:loading.remove wire:target="ambilKriteria">
                        {{ ($formRujukan['kriteriaSumber'] ?? '') === 'server' ? 'Muat Ulang Kriteria' : 'Ambil Kriteria' }}
                    </span>
                    <span wire:loading wire:target="ambilKriteria" class="inline-flex items-center gap-1">
                        <x-loading /> Memuat kriteria...
                    </span>
                </x-secondary-button>

                <x-secondary-button type="button" wire:click="cariKandidat" wire:loading.attr="disabled"
                    wire:target="cariKandidat" :disabled="$isFormLocked"
                    title="Langkah 2 — cari faskes tujuan yang mampu menangani">
                    <span wire:loading.remove wire:target="cariKandidat">Cari Kandidat</span>
                    <span wire:loading wire:target="cariKandidat" class="inline-flex items-center gap-1">
                        <x-loading /> Mencari...
                    </span>
                </x-secondary-button>

                @can('rujukan.kirim')
                    <x-primary-button type="button" wire:click="kirimRujukan" wire:loading.attr="disabled"
                        wire:target="kirimRujukan" :disabled="$isFormLocked"
                        title="Langkah 3 — kirim kunjungan + rujukan ke BPJS, diteruskan ke SATUSEHAT">
                        <span wire:loading.remove wire:target="kirimRujukan" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Kirim Rujukan
                        </span>
                        <span wire:loading wire:target="kirimRujukan" class="inline-flex items-center gap-1">
                            <x-loading /> Mengirim rujukan...
                        </span>
                    </x-primary-button>
                @endcan
            @else
                @can('rujukan.batal')
                    <x-confirm-button variant="danger-soft" action="batalkanRujukan"
                        title="Batalkan rujukan — pendaftaran PCare IKUT TERHAPUS"
                        message="Pembatalan ini menghapus rujukan BERIKUT PENDAFTARAN PCare pasien di BPJS, bukan hanya rujukannya. Tidak ada cara membatalkan rujukan saja. Sesudahnya pasien harus didaftarkan ulang dari Daftar kunjungan, dan nomor rujukan lama tidak bisa dipulihkan. Lanjut membatalkan?"
                        confirmText="Ya, batalkan rujukan" cancelText="Tidak jadi">
                        Batalkan Rujukan
                    </x-confirm-button>
                @endcan
            @endif

            <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
        </div>
    </div>
</div>
