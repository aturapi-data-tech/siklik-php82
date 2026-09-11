{{-- Partial: tabel-entri — dipakai ⚡rm-inform-consent-rj-actions.blade.php lewat @include; variabel & $this milik komponen induk. --}}
{{-- ══ DAFTAR ENTRI TERSIMPAN ══
     Bentuk baku tabel daftar modul dokumen: tanpa kolom No, kolom
     pertama panah rincian, entri TERBARU DI ATAS, sel Aksi satu baris
     rata kanan dengan kelompok berisiko dipisah garis. --}}
@unless ($this->diForm())
<section class="space-y-3">
    <div class="overflow-x-auto rounded-2xl">
        <table class="ds-table ds-table-entri min-w-full">
            <thead class="sticky top-0 z-10">
                <tr>
                    <th class="w-8"><span class="sr-only">Rincian</span></th>
                    <th class="whitespace-nowrap">Tanggal</th>
                    <th class="whitespace-nowrap">Tindakan</th>
                    <th class="whitespace-nowrap">Persetujuan</th>
                    <th class="whitespace-nowrap">Petugas (TTD)</th>
                    <th class="whitespace-nowrap">Status</th>
                    <th class="whitespace-nowrap ds-c">Aksi</th>
                </tr>
            </thead>

            @forelse ($this->daftarEntri() as $entri)
                @php
                    $entriTgl = $entri['signatureDate'] ?? '';
                    $entriIsFinal = $this->entriFinal($entri);
                    $bolehHapus = auth()->user()?->can('dokumen.hapus');
                    $bolehBukaKunci = auth()->user()?->can('dokumen.bukaKunci');
                @endphp

                {{-- Satu <tbody> per entri: baris ringkas + baris rincian yang
                     mulai TERTUTUP (x-data di tbody, bukan di tr). --}}
                <tbody wire:key="ic-entri-{{ $loop->index }}-{{ $entriTgl }}"
                    x-data="{ open: false }">
                    <tr class="cursor-pointer" @click="open = !open">
                        <td class="ds-c">
                            <svg class="w-4 h-4 transition-transform text-muted"
                                :class="{ 'rotate-90': open }" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </td>
                        <td class="ds-td-token">{{ $entriTgl ?: '-' }}</td>
                        <td class="ds-td-strong">{{ Str::limit($entri['tindakan'] ?? '-', 50) }}</td>
                        <td>
                            @if (($entri['agreement'] ?? '1') === '1')
                                <x-badge variant="success">Menyetujui</x-badge>
                            @else
                                <x-badge variant="danger">Menolak</x-badge>
                            @endif
                        </td>
                        <td>
                            {{-- Nama petugas hanya tampil bila entri final (aturan TTD #12d) --}}
                            @if ($entriIsFinal)
                                {{ $entri['dokter'] }}
                            @else
                                <x-badge variant="danger">Belum TTD</x-badge>
                            @endif
                        </td>
                        <td>
                            @if ($entriIsFinal)
                                <x-badge variant="info">Terkunci</x-badge>
                            @else
                                <x-badge variant="warning">Draft</x-badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap" @click.stop>
                            <div class="flex items-center justify-end gap-2">
                                @if (!$isFormLocked && !$entriIsFinal)
                                    <x-primary-button type="button" wire:click="editEntri('{{ $entriTgl }}')"
                                        wire:loading.attr="disabled" title="Lanjutkan mengisi draft ini">
                                        Lanjutkan Pengisian
                                    </x-primary-button>
                                @endif
                                <x-lihat-button wire:click="lihat('{{ $entriTgl }}')" />
                                <x-cetak-button wire:click="cetak('{{ $entriTgl }}')" />

                                {{-- Kelompok berisiko: dipisah garis, hanya dirender bila
                                     user berhak (supaya tak menyisakan garis kosong). --}}
                                @if (!$isFormLocked && ($bolehHapus || ($entriIsFinal && $bolehBukaKunci)))
                                    <div
                                        class="flex items-center gap-2 pl-3 ml-1 border-l border-hairline dark:border-gray-700">
                                        @if ($entriIsFinal)
                                            @can('dokumen.bukaKunci')
                                                <x-confirm-button variant="warning-soft" action="bukaKunci('{{ $entriTgl }}')"
                                                    title="Buka Kunci Inform Consent"
                                                    message="TTD pemberi informasi akan dicabut & entri kembali menjadi draft untuk dikoreksi. TTD pasien/wali dan saksi tetap dipertahankan. Lanjutkan?"
                                                    confirmText="Ya, buka kunci" cancelText="Batal">
                                                    Buka Kunci
                                                </x-confirm-button>
                                            @endcan
                                        @endif
                                        @can('dokumen.hapus')
                                            <x-hapus-button :action="'hapus(\'' . $entriTgl . '\')'"
                                                title="Hapus Inform Consent"
                                                message="Entri Inform Consent ini akan dihapus beserta tanda tangannya. Lanjutkan?" />
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Baris rincian — ringkasan isian yang tidak muat di kolom --}}
                    <tr x-show="open" x-cloak>
                        <td colspan="7">
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-2">
                                <div>
                                    <dt class="ds-caption-up">Diagnosa</dt>
                                    <dd class="text-muted">{{ $entri['diagnosa'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">Komplikasi</dt>
                                    <dd class="text-muted">{{ $entri['komplikasi'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">Tujuan Tindakan</dt>
                                    <dd class="text-muted">{{ $entri['tujuan'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">Risiko Tindakan</dt>
                                    <dd class="text-muted">{{ $entri['resiko'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">Alternatif Tindakan</dt>
                                    <dd class="text-muted">{{ $entri['alternatif'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">PPA</dt>
                                    <dd class="text-muted">{{ $entri['petugasPemeriksa'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">Pasien / Wali</dt>
                                    <dd class="text-muted">
                                        {{ $entri['wali'] ?: '-' }}
                                        @if (!empty($entri['waliHubungan']))
                                            ({{ $entri['waliHubungan'] }})
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="ds-caption-up">Saksi</dt>
                                    <dd class="text-muted">{{ $entri['saksi'] ?: '-' }}</dd>
                                </div>
                            </dl>
                        </td>
                    </tr>
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="7" class="ds-c text-muted">Belum ada data tersimpan</td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>

    <p class="text-sm text-muted">
        Setiap entri berdiri sendiri — satu baris untuk satu tindakan. <strong>Isi Formulir
        Baru</strong> untuk entri baru, <strong>Lanjutkan Pengisian</strong> untuk melanjutkan draft.
    </p>
</section>
@endunless
