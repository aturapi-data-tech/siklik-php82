{{-- resources/views/components/rujukan-kompetensi/identitas-kiriman.blade.php

    Identitas kunjungan yang IKUT DIKIRIM saat rujukan diterbitkan.

    Jalur FKTP (PCare) TIDAK memakai SEP — nomor kunjungan PCare dibentuk sendiri
    dari nomor RJ ("RJ-<rjNo>"), dan yang menyeberang ke SATUSEHAT hanyalah UUID
    Encounter. Karena itu komponen ini cuma menampilkan dua hal: nomor kunjungan
    PCare dan Encounter.

    Encounter sengaja selalu tampil (bukan hanya saat kosong): saat pengiriman
    gagal, petugas perlu memastikan Encounter MANA yang dipakai — bukan menebak
    dari ketiadaan peringatan.

    Prop:
      :encounterId    UUID Encounter SATUSEHAT kunjungan ini
      :noKunjungan    nomor kunjungan PCare (boleh null = barisnya tak tampil)
      :kodeFaskes     kodeFaskesSatuSehat milik klinik (Organization ID)
--}}

@props(['encounterId' => '', 'noKunjungan' => null, 'kodeFaskes' => null])

@php
    $encounterId = trim((string) $encounterId);
    $noKunjungan = $noKunjungan === null ? null : trim((string) $noKunjungan);
    $kodeFaskes = $kodeFaskes === null ? null : trim((string) $kodeFaskes);
@endphp

<div class="space-y-1 text-xs">
    @if ($noKunjungan !== null)
        <p class="text-muted-soft">No. Kunjungan PCare:
            @if ($noKunjungan === '')
                <span class="font-semibold text-red-700 dark:text-red-300">belum terbentuk</span>
            @else
                <span class="font-mono text-ink dark:text-gray-200">{{ $noKunjungan }}</span>
            @endif
        </p>
    @endif

    <p class="text-muted-soft">Encounter SATUSEHAT:
        @if ($encounterId === '')
            <span class="font-semibold text-red-700 dark:text-red-300">belum terkirim</span>
            <span class="text-muted-soft">— kirim lewat Daftar kunjungan &rarr; menu Satu Sehat &rarr; Encounter.</span>
        @else
            {{-- break-all: UUID 36 karakter tanpa spasi; tanpa ini kolom sempit melebar. --}}
            <span class="font-mono break-all text-ink dark:text-gray-200">{{ $encounterId }}</span>
        @endif
    </p>

    @if ($kodeFaskes !== null)
        <p class="text-muted-soft">Kode Faskes SATUSEHAT (klinik ini):
            @if ($kodeFaskes === '')
                <span class="font-semibold text-red-700 dark:text-red-300">belum diset di server</span>
            @else
                <span class="font-mono break-all text-ink dark:text-gray-200">{{ $kodeFaskes }}</span>
            @endif
        </p>
    @endif
</div>
