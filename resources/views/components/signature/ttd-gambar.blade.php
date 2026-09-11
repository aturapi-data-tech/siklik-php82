@props([
    // Kode user penanda-tangan (users.myuser_code). Gambar diambil dari
    // users.myuser_ttd_image lewat App\Support\TtdUser (dua format kolom).
    // Tidak merender apa pun bila kode kosong, user tak punya TTD, atau berkasnya hilang.
    // Catatan: USERS siklik tidak punya kolom emp_id, jadi hanya jalur kode ini.
    'code' => '',
    // Nama penanda-tangan — hanya untuk alt text.
    'name' => '',
])

@php
    $ttdImageUrl = \App\Support\TtdUser::urlDariKode($code);
@endphp

{{-- Gambar TTD user untuk stempel petugas di layar (pasangan x-signature.ttd-petugas).
     Kotak putih dibuat SAMA dengan hasil signature-pad pasien/saksi
     (x-signature.signature-result): lebar penuh + proporsi kanvas pad 460x180
     (inline style — token aspect-[460/180] tidak ada di build Tailwind), sehingga
     kolom TTD pasien/saksi/petugas sejajar tingginya. --}}
@if ($ttdImageUrl)
    <div {{ $attributes->merge(['class' => 'w-full overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-700']) }}>
        <img src="{{ $ttdImageUrl }}" alt="Tanda tangan {{ $name }}"
            class="w-full object-contain p-2 mx-auto max-h-40" style="aspect-ratio: 460 / 180;" />
    </div>
@endif
