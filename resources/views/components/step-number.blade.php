{{-- resources/views/components/step-number.blade.php

    Lingkaran angka langkah, dipakai di judul kelompok isian supaya nomornya
    terbaca sama dengan lingkaran pada tombol aksi & penanda langkah (x-stepper).

    Pemakaian:
      <x-step-number :n="1" />
      <x-step-number :n="3" /><x-step-number :n="4" /><x-step-number :n="5" />

    Warnanya sengaja memakai kelas yang SUDAH ada di CSS hasil build —
    public/build gitignored, jadi kelas baru akan tampil tanpa warna sampai
    ada yang menjalankan npm run build.
--}}

@props(['n' => ''])

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center w-5 h-5 text-xs font-bold rounded-full bg-brand-green/15 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime shrink-0']) }}>{{ $n }}</span>
