{{-- resources/views/components/stepper.blade.php

    Stepper mendatar untuk alur berurutan (pola angka bulat ala panduan iDRG,
    tapi ikut berubah warna mengikuti keadaan nyata).

    Pemakaian:
      <x-stepper :steps="$this->langkahRujukan()" />

    Bentuk tiap langkah:
      ['n' => 1, 'title' => 'Diagnosa & Kriteria', 'hint' => 'opsional',
       'state' => 'done' | 'current' | 'todo' | 'error']

    'error' dipakai untuk langkah yang GAGAL/ditolak — bukan sekadar belum
    dikerjakan, sehingga petugas tahu harus mundur, bukan lanjut.
--}}

@props(['steps' => []])

@php
    $tokenLingkaran = [
        'done' => 'bg-success-tint text-success-deep border-green-700 dark:bg-green-900/40 dark:text-green-200 dark:border-green-700',
        'current' => 'bg-brand-green text-white border-brand-green dark:bg-brand-lime dark:text-gray-900 dark:border-brand-lime',
        'error' => 'bg-error-tint text-error-deep border-red-700 dark:bg-red-900/40 dark:text-red-200 dark:border-red-700',
        'todo' => 'bg-gray-100 text-gray-500 border-gray-300 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600',
    ];
    $tokenJudul = [
        'done' => 'text-ink dark:text-gray-100',
        'current' => 'text-brand-green dark:text-brand-lime',
        'error' => 'text-error-deep dark:text-red-200',
        'todo' => 'text-muted dark:text-gray-400',
    ];
@endphp

<ol {{ $attributes->merge(['class' => 'flex flex-wrap items-start gap-x-2 gap-y-3']) }}>
    @foreach ($steps as $indexLangkah => $langkah)
        @php
            $keadaan = $langkah['state'] ?? 'todo';
            $lingkaran = $tokenLingkaran[$keadaan] ?? $tokenLingkaran['todo'];
            $judul = $tokenJudul[$keadaan] ?? $tokenJudul['todo'];
        @endphp

        <li class="flex items-start gap-2 min-w-0">
            <span
                class="flex items-center justify-center border rounded-full shrink-0 w-7 h-7 text-sm font-bold {{ $lingkaran }}"
                @if ($keadaan === 'done') aria-label="selesai" @endif>
                @if ($keadaan === 'done')
                    {{-- centang: karakter asli, bukan entity — entity tampil literal di prop komponen --}}
                    ✓
                @elseif ($keadaan === 'error')
                    !
                @else
                    {{ $langkah['n'] ?? $indexLangkah + 1 }}
                @endif
            </span>

            <span class="min-w-0">
                <span class="block text-sm font-semibold leading-tight {{ $judul }}">{{ $langkah['title'] ?? '' }}</span>
                @if (!empty($langkah['hint']))
                    <span class="block text-xs text-muted-soft">{{ $langkah['hint'] }}</span>
                @endif
            </span>
        </li>

        @if (!$loop->last)
            <li aria-hidden="true" class="hidden sm:flex items-center self-center text-muted-soft">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </li>
        @endif
    @endforeach
</ol>
