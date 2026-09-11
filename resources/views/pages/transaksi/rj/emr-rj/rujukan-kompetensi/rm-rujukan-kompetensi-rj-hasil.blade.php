{{-- Rujukan sudah terbit — isian terkunci, tinggal nomor & cetak.

    Partial ini di-@include dari ⚡rm-rujukan-kompetensi-rj-actions.blade.php dan
    TIDAK mewarisi blok `use` komponennya.
--}}

@php
    $hasilRujukan = $formRujukan['hasil'] ?? [];
    $kandidatTerpilih = $this->kandidatBaris($formRujukan['kandidatIdx'] ?? null);
@endphp

<div class="p-4 space-y-3 text-sm border border-green-200 rounded-lg bg-green-50 dark:bg-green-950 dark:border-green-900">
    <p class="text-base font-semibold text-green-800 dark:text-green-200">Rujukan sudah terkirim</p>

    <div class="overflow-x-auto">
        <table class="text-gray-700 dark:text-gray-200">
            <tbody class="align-top">
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">No. Rujukan PCare</td>
                    <td class="py-0.5 font-mono font-semibold">{{ $hasilRujukan['noRujukanPcare'] ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">No. Rujukan SATUSEHAT</td>
                    <td class="py-0.5 font-mono font-semibold">{{ $hasilRujukan['noRujukanSatuSehat'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">No. Kunjungan PCare</td>
                    <td class="py-0.5 font-mono">{{ $hasilRujukan['noKunjunganPcare'] ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">Faskes Tujuan</td>
                    <td class="py-0.5">
                        {{ $hasilRujukan['tujuanNama'] ?? '-' }}
                        <span class="text-muted dark:text-gray-400">
                            (PPK {{ $hasilRujukan['tujuanPpk'] ?? '-' }} &middot; Org ID {{ $hasilRujukan['tujuanSatuSehat'] ?? '-' }})
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">Diagnosa</td>
                    <td class="py-0.5">
                        <span class="font-mono">{{ $formRujukan['kodeDiagnosa'] ?: '-' }}</span>
                        {{ $formRujukan['diagnosaDesc'] }}
                    </td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">Subspesialis</td>
                    <td class="py-0.5">
                        {{ $formRujukan['kodeSubSpesialis'] ?: '-' }} {{ $formRujukan['namaSubSpesialis'] }}
                        @if (filled($formRujukan['namaSarana'] ?? ''))
                            &middot; sarana {{ $formRujukan['namaSarana'] }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">Estimasi Tgl. Rujuk</td>
                    <td class="py-0.5">{{ $formRujukan['estimasiRujuk'] ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-4 whitespace-nowrap">Dikirim</td>
                    <td class="py-0.5">
                        {{ $hasilRujukan['dikirimPada'] ?? '-' }} oleh {{ $hasilRujukan['dikirimOleh'] ?? '-' }}
                    </td>
                </tr>
                @if (filled($hasilRujukan['serviceRequestId'] ?? ''))
                    <tr>
                        <td class="py-0.5 pr-4 whitespace-nowrap">ServiceRequest</td>
                        <td class="py-0.5 font-mono break-all">{{ $hasilRujukan['serviceRequestId'] }}</td>
                    </tr>
                @endif
                @if (filled($hasilRujukan['traceId'] ?? ''))
                    <tr>
                        <td class="py-0.5 pr-4 whitespace-nowrap">Trace ID</td>
                        <td class="py-0.5 font-mono break-all">{{ $hasilRujukan['traceId'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <x-rujukan-kompetensi.identitas-kiriman :encounterId="$this->encounterUuid()"
        :kodeFaskes="$this->kodeFaskesSatuSehat()" />

    {{-- Surat Pengantar Rujukan — komponen cetaknya headless di halaman EMR. --}}
    <div class="pt-1">
        <x-cetak-button wire:click="cetakSuratRujukan" label="Cetak Surat Rujukan" />
    </div>

    <p class="text-xs text-muted dark:text-gray-400">
        Isian terkunci karena nomor rujukan sudah terbit. Perlu mengubah tujuan? Batalkan dulu lewat tombol di
        footer — perlu diingat pembatalan <strong>ikut menghapus pendaftaran PCare</strong> pasien ini.
    </p>
</div>

@if ($kandidatTerpilih)
    {{-- Daftar kandidat disimpan apa adanya supaya alasan pemilihan tetap bisa
         ditelusuri saat rujukan dipertanyakan. --}}
    <details class="text-sm border rounded-lg bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700">
        <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
            Kandidat yang tampil saat rujukan dibuat ({{ count($formRujukan['kandidatList'] ?? []) }})
        </summary>
        <div class="px-3 pb-3">
            <x-rujukan-kompetensi.kandidat-tabel :rows="$formRujukan['kandidatList'] ?? []"
                :selectedIndex="$formRujukan['kandidatIdx'] ?? null" :disabled="true" />
        </div>
    </details>
@endif
