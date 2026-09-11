{{-- resources/views/components/rujukan-kompetensi/kandidat-tabel.blade.php

    Tabel kandidat faskes tujuan hasil Sisrute/GetFaskesRujukan.

    Semua keterangan faskes (kdppk, Org ID SATUSEHAT, strata, kelas, jarak,
    estimasi waktu, jadwal, beban rujukan) DILEBUR ke sel "Faskes Tujuan" —
    bukan kolom sendiri. Alasannya: panel ini hidup di dalam modal EMR yang
    sering tampil sempit, dan kolom "Pilih" harus tetap terlihat tanpa
    menggulung tabel ke samping.

    Perataan baris dipusatkan di App\Support\Rujukan\RujukanKompetensiTampil —
    di sanalah jarak/waktu mustahil (1.79E+308) disaring dan prefix
    "Organization/" dibuang dari Org ID.

    Prop:
      :rows          kandidatList apa adanya (baris mentah response GetFaskesRujukan)
      :selectedIndex kandidatIdx — indeks baris terpilih, null bila belum memilih
      :disabled      formulir terkunci / rujukan sudah terbit
      action         nama method Livewire yang dipanggil; MENERIMA INDEKS (angka)
--}}

@props([
    'rows' => [],
    'selectedIndex' => null,
    'disabled' => false,
    'action' => 'pilihKandidat',
])

@use('App\Support\Rujukan\RujukanKompetensiTampil')

@if (!empty($rows))
    <div class="mt-2 overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
        <table class="ds-table ds-table-rapat">
            <thead>
                <tr>
                    <th class="w-8 ds-c">No</th>
                    <th>Faskes Tujuan</th>
                    <th class="w-20 ds-c">Pilih</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $indexKandidat => $kandidatMentah)
                    @php
                        $kandidat = RujukanKompetensiTampil::kandidatBaris((array) $kandidatMentah);
                        $terpilih = $selectedIndex !== null && (int) $selectedIndex === (int) $indexKandidat;
                        // Kandidat tanpa kode PPK BPJS tidak sah jadi tujuan rujukan JKN.
                        $tanpaBpjs = $kandidat['kdppk'] === '';
                    @endphp
                    <tr wire:key="kandidat-rujukan-{{ $indexKandidat }}"
                        class="{{ $terpilih ? 'bg-brand-green/5 dark:bg-brand-lime/5' : '' }}">
                        <td class="ds-c ds-td-meta">{{ $indexKandidat + 1 }}</td>
                        <td class="break-words">
                            <span class="ds-td-strong">{{ $kandidat['nama'] }}</span>
                            @if (filled($kandidat['alamat']))
                                <span class="block text-xs text-muted-soft">{{ $kandidat['alamat'] }}</span>
                            @endif

                            {{-- Dua kode dari DUA SISTEM, dikirim bersama dan WAJIB milik faskes
                                 yang sama — tertukar berarti rujukan nyasar. --}}
                            <span class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1 text-xs text-muted dark:text-gray-400">
                                @if ($tanpaBpjs)
                                    <x-badge variant="gray">non-BPJS</x-badge>
                                @else
                                    <span>Kode PPK
                                        <span class="font-mono text-ink dark:text-gray-200">{{ $kandidat['kdppk'] }}</span>
                                    </span>
                                @endif
                                <span title="Kode faskes di SATUSEHAT (Organization ID)">&middot; Org ID
                                    <span class="font-mono text-ink dark:text-gray-200">{{ $kandidat['orgId'] ?: '—' }}</span>
                                </span>
                                @if (filled($kandidat['strata']))
                                    <x-badge variant="info" title="Strata kompetensi faskes menurut SATUSEHAT">Strata {{ $kandidat['strata'] }}</x-badge>
                                @endif
                                @if (filled($kandidat['kelas']))
                                    <span>&middot; Kelas {{ $kandidat['kelas'] }}</span>
                                @endif
                                <span class="tabular-nums">&middot; {{ $kandidat['jarak'] }}</span>
                                @if ($kandidat['waktu'] !== '—')
                                    <span class="tabular-nums">&middot; {{ $kandidat['waktu'] }}</span>
                                @endif
                                @if (filled($kandidat['jadwal']))
                                    <span title="Jadwal praktik subspesialis di faskes tujuan">&middot; jadwal {{ $kandidat['jadwal'] }}</span>
                                @endif
                                @if (filled($kandidat['beban']))
                                    <span class="tabular-nums" title="Rujukan masuk / kapasitas — pernah dilaporkan tidak ter-update, jangan dipakai sebagai angka keras">
                                        &middot; beban {{ $kandidat['beban'] }}
                                    </span>
                                @endif
                            </span>
                        </td>

                        {{-- wireClick dikirim INDEKS angka: nama faskes ber-& akan ter-escape
                             ganda dan aksinya gagal diam-diam. --}}
                        <td class="ds-c ds-toggle-tumpuk">
                            <x-toggle :current="$terpilih ? 'Ya' : 'Tidak'" trueValue="Ya" falseValue="Tidak"
                                :disabled="$disabled || $tanpaBpjs"
                                wireClick="{{ $action }}({{ $indexKandidat }})"
                                :label="$terpilih ? 'Dipilih' : ($tanpaBpjs ? 'Tak bisa' : 'Pilih')" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
