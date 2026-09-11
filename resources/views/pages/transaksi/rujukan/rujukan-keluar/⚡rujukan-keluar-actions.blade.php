<?php
// resources/views/pages/transaksi/rujukan/rujukan-keluar/rujukan-keluar-actions.blade.php
//
// Modal RINCIAN satu rujukan keluar. Dibuka dari daftar:
//     $this->dispatch('rujukan-keluar.detail.open', rjNo: $rjNo);
//
// Isinya seluruh isian node `rujukanKompetensi` apa adanya — kriteria yang
// ditawarkan pusat beserta yang dipilih, jejaring wilayah, spesialis/subspesialis/
// sarana, estimasi tanggal, daftar kandidat faskes, hasil (nomor rujukan) dan
// keterangan pembatalan — ditambah respons mentah supaya petugas punya bahan
// lampiran saat melapor kendala ke BPJS/SATUSEHAT.
//
// Komponen ini HANYA MEMBACA. Pengiriman & pembatalan rujukan dilakukan dari
// panel EMR Tindak Lanjut (yang memegang gate rujukan.kirim / rujukan.batal).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    public string $rjNo = '';
    public array $dataRJ = [];
    public array $node = [];

    #[On('rujukan-keluar.detail.open')]
    public function bukaRincian(string $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->dataRJ = $this->findDataRJ($rjNo);
        $this->node = $this->dataRJ['rujukanKompetensi'] ?? [];

        if ($this->node === []) {
            $this->dispatch('toast', type: 'error', message: 'Data rujukan tidak ditemukan pada kunjungan ini.');
            return;
        }

        $this->dispatch('open-modal', name: 'rujukan-keluar-detail');
    }

    public function closeModal(): void
    {
        $this->reset(['rjNo', 'dataRJ', 'node']);
        $this->dispatch('close-modal', name: 'rujukan-keluar-detail');
    }

    public function cetakSurat(): void
    {
        $this->dispatch('cetak-surat-rujukan-rj.open', rjNo: $this->rjNo);
    }

    #[Computed]
    public function hasil(): array
    {
        return $this->node['hasil'] ?? [];
    }

    #[Computed]
    public function dibatalkan(): array
    {
        $dibatalkan = $this->node['dibatalkan'] ?? null;

        return is_array($dibatalkan) ? $dibatalkan : [];
    }

    #[Computed]
    public function sudahTerbit(): bool
    {
        return trim((string) ($this->hasil['noRujukanSatuSehat'] ?? '')) !== '';
    }

    /** Kriteria yang benar-benar dikirim (TEPAT SATU item dari kriteriaList). */
    #[Computed]
    public function kriteriaTerpilih(): string
    {
        $terpilih = collect($this->node['kriteriaList'] ?? [])
            ->firstWhere('linkId', $this->node['kriteriaPilih'] ?? null);

        $teks = trim((string) ($terpilih['text'] ?? ''));
        if ($teks === '') {
            return '';
        }

        $icd9 = trim((string) ($this->node['kriteriaIcd9'] ?? ''));
        $icd9Desc = trim((string) ($this->node['kriteriaIcd9Desc'] ?? ''));

        return $icd9 === '' ? $teks : $teks . ' — ' . trim($icd9 . ' ' . $icd9Desc);
    }

    /** Baris kandidat yang dipilih petugas (index disimpan panel EMR). */
    #[Computed]
    public function kandidatTerpilih(): array
    {
        $indeks = $this->node['kandidatIdx'] ?? null;
        if ($indeks === null) {
            return [];
        }

        $kandidat = $this->node['kandidatList'][$indeks] ?? [];

        return is_array($kandidat) ? $kandidat : [];
    }

    /**
     * Respons mentah yang disimpan panel. Kunci bisa berbeda antar versi panel,
     * jadi dicoba beberapa; kalau tidak ada, seluruh node ditampilkan sebagai
     * gantinya supaya modal tetap berguna untuk melapor kendala.
     */
    #[Computed]
    public function responMentah(): string
    {
        foreach (['responMentah', 'raw'] as $kunci) {
            $nilai = $this->node[$kunci] ?? ($this->hasil[$kunci] ?? null);
            if (is_string($nilai) && trim($nilai) !== '') {
                return $this->rapikan($nilai);
            }
            if (is_array($nilai) && $nilai !== []) {
                return json_encode($nilai, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return '';
    }

    #[Computed]
    public function nodeJson(): string
    {
        return json_encode($this->node, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /** JSON string mentah dari BPJS dirapikan bila memang JSON; selain itu apa adanya. */
    private function rapikan(string $teks): string
    {
        $decoded = json_decode($teks, true);

        return is_array($decoded)
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $teks;
    }

    public function nilai(string $kunci, string $bawaan = '-'): string
    {
        $nilai = trim((string) ($this->node[$kunci] ?? ''));

        return $nilai === '' ? $bawaan : $nilai;
    }
};
?>

<div>
    <x-modal name="rujukan-keluar-detail" size="4xl" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- ══════════ HEADER ══════════ --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Rincian Rujukan Keluar
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            No. RJ <span class="font-mono">{{ $rjNo }}</span> &middot;
                            {{ strtoupper($dataRJ['regName'] ?? '-') }} (RM {{ $dataRJ['regNo'] ?? '-' }}) &middot;
                            {{ $dataRJ['poliDesc'] ?? '-' }} &middot; {{ $dataRJ['rjDate'] ?? '-' }}
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" title="Tutup">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ══════════ ISI ══════════ --}}
            <div class="flex-1 min-h-0 px-6 py-4 overflow-y-auto">

                @if ($node === [])
                    <p class="py-16 text-sm text-center text-gray-500 dark:text-gray-400">
                        Tidak ada data rujukan pada kunjungan ini.
                    </p>
                @else
                    {{-- Status --}}
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        @if ($this->dibatalkan !== [])
                            <x-badge variant="danger">Dibatalkan</x-badge>
                        @elseif ($this->sudahTerbit)
                            <x-badge variant="success">Terkirim</x-badge>
                        @else
                            <x-badge variant="warning">Draft — belum terkirim</x-badge>
                        @endif

                        @if ($this->sudahTerbit)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Dikirim oleh {{ $this->hasil['dikirimOleh'] ?? '-' }}
                                pada {{ $this->hasil['dikirimPada'] ?? '-' }}
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                        {{-- ── ISIAN RUJUKAN ── --}}
                        <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                Isian Rujukan</div>
                            <dl class="space-y-1.5 text-sm">
                                @foreach ([
        'Diagnosa (ICD-10)' => trim(($node['kodeDiagnosa'] ?? '') . ' ' . ($node['diagnosaDesc'] ?? '')),
        'Encounter SATUSEHAT' => $node['encounterId'] ?? '',
        'Kriteria Terpilih' => $this->kriteriaTerpilih,
        'Provinsi' => trim(($node['kodePropinsi'] ?? '') . ' ' . ($node['namaPropinsi'] ?? '')),
        'Kabupaten/Kota' => trim(($node['kodeKabupaten'] ?? '') . ' ' . ($node['namaKabupaten'] ?? '')),
        'Spesialis' => trim(($node['kodeSpesialis'] ?? '') . ' ' . ($node['namaSpesialis'] ?? '')),
        'Sub Spesialis' => trim(($node['kodeSubSpesialis'] ?? '') . ' ' . ($node['namaSubSpesialis'] ?? '')),
        'Sarana' => trim(($node['kodeSarana'] ?? '') . ' ' . ($node['namaSarana'] ?? '')),
        'Estimasi Rujuk' => $node['estimasiRujuk'] ?? '',
        'Catatan' => $node['catatan'] ?? '',
    ] as $label => $isi)
                                    <div class="flex gap-2">
                                        <dt class="w-40 text-gray-500 shrink-0 dark:text-gray-400">{{ $label }}</dt>
                                        <dd class="text-gray-800 break-words dark:text-gray-200">
                                            {{ trim((string) $isi) !== '' ? $isi : '-' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        {{-- ── HASIL ── --}}
                        <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                Hasil Pengiriman</div>
                            <dl class="space-y-1.5 text-sm">
                                @foreach ([
        'No. Rujukan PCare' => $this->hasil['noRujukanPcare'] ?? '',
        'No. Rujukan SATUSEHAT' => $this->hasil['noRujukanSatuSehat'] ?? '',
        'ServiceRequest ID' => $this->hasil['serviceRequestId'] ?? '',
        'Trace ID' => $this->hasil['traceId'] ?? '',
        'No. Kunjungan PCare' => $this->hasil['noKunjunganPcare'] ?? '',
        'Tgl. Rujukan' => $this->hasil['tglRujukan'] ?? '',
        'Faskes Tujuan' => $this->hasil['tujuanNama'] ?? '',
        'Kode PPK Tujuan' => $this->hasil['tujuanPpk'] ?? '',
        'Org. SATUSEHAT Tujuan' => $this->hasil['tujuanSatuSehat'] ?? '',
    ] as $label => $isi)
                                    <div class="flex gap-2">
                                        <dt class="w-40 text-gray-500 shrink-0 dark:text-gray-400">{{ $label }}</dt>
                                        <dd class="font-mono text-gray-800 break-all dark:text-gray-200">
                                            {{ trim((string) $isi) !== '' ? $isi : '-' }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if ($this->dibatalkan !== [])
                                <div
                                    class="px-3 py-2 mt-3 text-xs text-red-700 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-900/50 dark:text-red-300">
                                    Dibatalkan oleh {{ $this->dibatalkan['oleh'] ?? '-' }}
                                    pada {{ $this->dibatalkan['pada'] ?? '-' }}.
                                    Alasan: {{ $this->dibatalkan['alasan'] ?? '-' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ── KANDIDAT FASKES ── --}}
                    <div class="p-4 mt-4 border border-gray-200 rounded-xl dark:border-gray-700">
                        <div class="mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                            Kandidat Faskes ({{ count($node['kandidatList'] ?? []) }})
                        </div>

                        @if (empty($node['kandidatList']))
                            <p class="text-sm text-gray-500 dark:text-gray-400">Daftar kandidat tidak tersimpan pada
                                node ini.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="ds-table ds-table-rapat">
                                    <thead>
                                        <tr class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                                            <th class="ds-c">#</th>
                                            <th>Nama Faskes</th>
                                            <th>kdppk</th>
                                            <th>Strata</th>
                                            <th>Kelas</th>
                                            <th>Jarak</th>
                                            <th>Jadwal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($node['kandidatList'] as $indeks => $kandidat)
                                            <tr
                                                class="{{ ($node['kandidatIdx'] ?? null) === $indeks ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}">
                                                <td class="ds-c">
                                                    @if (($node['kandidatIdx'] ?? null) === $indeks)
                                                        <x-badge variant="success">✓</x-badge>
                                                    @else
                                                        {{ $indeks + 1 }}
                                                    @endif
                                                </td>
                                                <td class="text-xs">{{ $kandidat['nmppk'] ?? '-' }}</td>
                                                <td class="font-mono text-xs">{{ $kandidat['kdppk'] ?? '-' }}</td>
                                                <td class="text-xs">{{ $kandidat['strataSatuSehat'] ?? '-' }}</td>
                                                <td class="text-xs">{{ $kandidat['kelas'] ?? '-' }}</td>
                                                <td class="text-xs">{{ $kandidat['distance'] ?? '-' }}</td>
                                                <td class="text-xs">{{ $kandidat['jadwal'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- ── RESPONS MENTAH & NODE ── --}}
                    <div class="p-4 mt-4 border border-gray-200 rounded-xl dark:border-gray-700"
                        x-data="{ buka: false }">
                        <button type="button" x-on:click="buka = !buka"
                            class="flex items-center w-full gap-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                            <span>Respons Mentah &amp; Node JSON</span>
                            <span class="ml-auto" x-text="buka ? '−' : '+'"></span>
                        </button>

                        <div x-show="buka" x-cloak class="mt-3 space-y-3">
                            @if ($this->responMentah !== '')
                                <div>
                                    <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">Respons mentah tersimpan
                                    </div>
                                    <pre
                                        class="p-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{{ $this->responMentah }}</pre>
                                </div>
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Panel tidak menyimpan salinan respons mentah pada node ini. Payload &amp; response
                                    lengkap tiap panggilan tetap terekam di tabel <code>web_log_status</code>
                                    (menu Sistem &rarr; Log BPJS API).
                                </p>
                            @endif

                            <div>
                                <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">Node
                                    <code>rujukanKompetensi</code></div>
                                <pre
                                    class="p-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{{ $this->nodeJson }}</pre>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ══════════ FOOTER ══════════ --}}
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                @if ($this->sudahTerbit)
                    <x-cetak-button wire:click="cetakSurat" label="Cetak Surat Rujukan" />
                @endif
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
