{{-- Bagian ATAS modal Rujukan Kompetensi: panduan, prasyarat, penanda langkah.

    Partial ini di-@include dari ⚡rm-rujukan-kompetensi-rj-actions.blade.php dan
    TIDAK mewarisi blok `use` komponennya — tulis nama kelas lengkap bila perlu.
--}}

@php
    $prasyaratKurang = $this->prasyaratKurang();
@endphp

{{-- Panduan pemakaian — biru, default tertutup (komponen bersama). --}}
<x-rujukan-kompetensi.panduan-kirim jalur="pcare" />

{{-- Prasyarat & penanda langkah disandingkan: keduanya keterangan KEADAAN, bukan
     isian — menumpuknya ke bawah cuma mendorong formulir menjauh. --}}
<div class="grid items-start grid-cols-1 gap-3 lg:grid-cols-2">
    @if (!empty($prasyaratKurang) && !$this->sudahTerkirim())
        <div class="overflow-hidden text-sm border border-red-200 rounded-lg bg-red-50 dark:bg-red-950 dark:border-red-900">
            <div class="px-3 py-2 font-semibold text-red-800 dark:text-red-200">
                Belum bisa <em>mengirim</em> rujukan — {{ count($prasyaratKurang) }} hal perlu dilengkapi
            </div>
            <ul class="px-3 pb-3 ml-4 text-red-800 list-disc dark:text-red-200">
                @foreach ($prasyaratKurang as $itemKurang)
                    <li>{{ $itemKurang }}</li>
                @endforeach
                <li class="pt-1 -ml-4 text-xs list-none">
                    Langkah 1 (Ambil Kriteria) &amp; 2 (Cari Kandidat) tetap bisa dijalankan sambil melengkapi ini —
                    asalkan Encounter SATUSEHAT sudah ada.
                </li>
            </ul>
        </div>
    @endif

    {{-- Penanda langkah — buka-tutup, default tertutup: yang dibutuhkan sehari-hari
         cuma "sedang di langkah apa", dan itu sudah tertulis di kepalanya. --}}
    <div x-data="{ buka: false }"
        class="overflow-hidden border rounded-lg bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700">
        <button type="button" x-on:click="buka = !buka"
            class="flex items-center justify-between w-full gap-2 px-3 py-2 text-sm font-semibold text-left text-gray-700 dark:text-gray-200">
            <span>Langkah:
                <span class="font-normal text-muted dark:text-gray-400">
                    {{ collect($this->langkahRujukan())->firstWhere('state', 'current')['title'] ?? 'selesai' }}
                </span>
            </span>
            <svg class="w-4 h-4 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="buka" x-cloak class="px-3 pb-3 overflow-x-auto">
            <x-stepper :steps="$this->langkahRujukan()" />
        </div>
    </div>
</div>
