@props([
    // ══ Data TTD ══ (nama prop mengikuti key JSON EMR: 'petugasPemeriksa' / '…Code' / '…Date')
    // Nama penanda-tangan. Kosong = belum TTD → tombol stempel muncul; terisi = kartu TTD tampil.
    'ttd' => '',
    // Waktu/jam TTD; string ditampilkan apa adanya (mis. "07/07/2026 09:00:00").
    'date' => '',
    // Kode user penanda-tangan (users.myuser_code). Bila diisi: tampil "Kode: xxx",
    // gambar TTD user (myuser_ttd_image) ditampilkan di layar & dipakai me-resolve
    // gambar saat cetak lewat App\Support\TtdUser.
    'code' => '',

    // ══ Kontrol kemunculan tombol ══
    // true → form terkunci / read-only: tombol TTD & Ganti-Hapus disembunyikan (lempar $isFormLocked).
    'locked' => false,
    // false → tombol disembunyikan walau form TIDAK terkunci (mis. role tak berwenang);
    //         field readonly tetap terlihat.
    'canSign' => true,
    // false → sekali TTD tak bisa diubah (tombol Ganti/Hapus disembunyikan).
    'allowClear' => true,

    // ══ Method Livewire di komponen induk (dipanggil via wire:click) ══
    // Nama method untuk men-stempel TTD (nama + kode + tanggal user login).
    'sign' => 'ttdSaya',
    // Nama method untuk menghapus/mengganti TTD (hanya relevan saat allowClear=true).
    'clear' => 'hapusTtd',

    // ══ Tata letak ══
    // true  → dibungkus kartu bingkai bertajuk $title, kolom di tengah.
    // false → tanpa bingkai, rata kiri; cocok di grid-cell kolom TTD.
    'framed' => true,
    // Judul kartu bingkai — hanya dipakai saat framed=true.
    'title' => 'Tanda Tangan',
    // Judul kecil di atas baris field; kosongkan bila judul kolom sudah ada di luar komponen.
    'label' => '',

    // ══ Teks label field & tombol (gaya EMR: 2 field readonly ditumpuk) ══
    'nameLabel' => 'Petugas',
    'dateLabel' => 'Waktu TTD',
    'signLabel' => 'TTD Saya',
    'clearLabel' => 'Ganti / Hapus TTD',
    // Hint saat terkunci & belum TTD.
    'emptyText' => 'Belum ditandatangani.',
])

@php $signed = !empty($ttd); @endphp

{{-- Stempel TTD petugas baku modul dokumen — gaya EMR (gambar TTD user + field
     Petugas & Waktu readonly + tombol "TTD Saya" yang menstempel nama user login
     + kode + tanggal). SATU berkas.

     Bingkai (framed=true) sengaja pakai <div> biasa (BUKAN tag komponen
     x-border-form) supaya bisa dibungkus per-@if tanpa memecah tag komponen —
     tag komponen yang dibelah antar @if kehilangan isinya. --}}
@if ($framed)
    <div class="border shadow-sm border-hairline rounded-2xl bg-canvas dark:border-gray-700 dark:bg-gray-900">
        <div class="p-4">
            <div class="mb-4 text-center ds-caption-up">{{ $title }}</div>
@endif

            <div class="{{ $framed ? 'max-w-xl mx-auto' : '' }}">
                @if ($label)
                    <div class="mb-2 text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400 {{ $framed ? 'text-center' : 'text-left' }}">
                        {{ $label }}
                    </div>
                @endif

                <div class="space-y-2">
                    {{-- Gambar TTD penanda-tangan (bila user punya myuser_ttd_image) --}}
                    @if ($signed)
                        <x-signature.ttd-gambar :code="$code" :name="$ttd" class="mb-1" />
                    @endif

                    {{-- Nama penanda-tangan: label + kotak input (readonly) --}}
                    <div>
                        <x-input-label :value="$nameLabel" />
                        <x-text-input value="{{ $signed ? $ttd : '-' }}" class="mt-1" :disabled="true" readonly />
                    </div>

                    {{-- Kode & Waktu sebagai teks (label muted + nilai tebal); Kode di atas --}}
                    <div class="space-y-0.5 text-sm">
                        @if (!empty($code))
                            <p><span class="text-muted">Kode:</span>
                                <span class="font-semibold text-ink dark:text-gray-200">{{ $code }}</span></p>
                        @endif
                        <p><span class="text-muted">{{ $dateLabel }}:</span>
                            <span class="font-semibold text-ink dark:text-gray-200">{{ $date ?: '-' }}</span></p>
                    </div>

                    {{-- Tombol stempel / ganti (tersembunyi saat terkunci atau tak berwenang) --}}
                    @unless ($locked || !$canSign)
                        @if (!$signed)
                            <div class="pt-1">
                                <x-primary-button type="button" wire:click="{{ $sign }}" wire:loading.attr="disabled"
                                    wire:target="{{ $sign }}" class="justify-center w-full gap-1.5">
                                    <span wire:loading.remove wire:target="{{ $sign }}" class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                        </svg>
                                        {{ $signLabel }}
                                    </span>
                                    <span wire:loading wire:target="{{ $sign }}">Menyimpan...</span>
                                </x-primary-button>
                            </div>
                        @elseif ($allowClear)
                            <div class="pt-1">
                                <x-secondary-button type="button" wire:click="{{ $clear }}" wire:loading.attr="disabled"
                                    wire:target="{{ $clear }}" class="justify-center w-full gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    {{ $clearLabel }}
                                </x-secondary-button>
                            </div>
                        @endif
                    @endunless
                </div>

                @if ($locked && !$signed && $emptyText)
                    <p class="mt-2 text-sm italic text-muted-soft">{{ $emptyText }}</p>
                @endif
            </div>

@if ($framed)
        </div>
    </div>
@endif
