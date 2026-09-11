{{--
    Ikon untuk tombol aksi kartu Kirim Satu Sehat.

    Dipakai bersama semua tombol kirim RJ supaya bentuknya seragam. Ikonnya
    MENGIKUTI KEADAAN, bukan sekadar hiasan: sebelum terkirim berupa pesawat
    kertas (aksi yang akan berangkat), sesudahnya centang (sudah tiba). Tanpa
    perbedaan itu, tombol hijau bertuliskan "Terkirim" tetap terlihat seperti
    tombol yang menunggu ditekan.

    Warna sengaja TIDAK diatur di sini — tombol induk yang menentukan (teal saat
    siap kirim, emerald saat sudah), dan currentColor mengikutinya.

    @param bool $selesai  keadaan langkah: sudah terkirim / sudah difinish
    @param string $jenis  'kirim' (pesawat kertas) | 'finish' (bendera) | 'hentikan' (kotak stop)
--}}
@props(['selesai' => false, 'jenis' => 'kirim'])

@if ($jenis === 'hentikan')
    {{-- Kotak stop: menghentikan rantai yang berjalan. Sengaja BUKAN tanda silang —
         silang berarti menutup/membatalkan, padahal langkah yang sudah berangkat
         tidak ikut dibatalkan; yang berhenti cuma sisanya. --}}
    <svg {{ $attributes->merge(['class' => 'w-4 h-4 shrink-0']) }} fill="none" stroke="currentColor"
        viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
        <rect x="6" y="6" width="12" height="12" rx="2" stroke-linejoin="round" />
    </svg>
@elseif ($selesai)
    {{-- Centang: langkah sudah tiba di SATUSEHAT --}}
    <svg {{ $attributes->merge(['class' => 'w-4 h-4 shrink-0']) }} fill="none" stroke="currentColor"
        viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
    </svg>
@elseif ($jenis === 'finish')
    {{-- Bendera: menutup kunjungan, bukan mengirim resource baru --}}
    <svg {{ $attributes->merge(['class' => 'w-4 h-4 shrink-0']) }} fill="none" stroke="currentColor"
        viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2z" />
    </svg>
@else
    {{-- Pesawat kertas: resource akan diberangkatkan --}}
    <svg {{ $attributes->merge(['class' => 'w-4 h-4 shrink-0']) }} fill="none" stroke="currentColor"
        viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
    </svg>
@endif
