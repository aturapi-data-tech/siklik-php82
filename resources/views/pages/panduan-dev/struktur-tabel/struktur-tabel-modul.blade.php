{{-- Partial: Modul & Relasi Antar Tabel. Relasi FK dari user_constraints + relasi implisit (kolom = PK tabel lain tanpa FK). --}}
<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">03 — Struktur</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Modul & Relasi Antar Tabel</h1>
<p class="max-w-3xl mb-2 text-sm">
    Tiap modul memuat daftar tabel/view, relasi <b>FK terdeklarasi</b> (Oracle menjaga integritasnya, hapus induk
    yang masih dipakai → <code>ORA-02292</code>), dan relasi <b>implisit</b>: kolom yang namanya sama dengan PK tabel
    lain tetapi tanpa FK. Join implisit sah secara semantik, hanya tidak dijaga Oracle, jadi kode harus memvalidasi sendiri.
</p>
<p class="max-w-3xl mb-6 text-sm">
    Tombol <b>Mermaid</b> menyalin teks <code>erDiagram</code> modul itu, tempel di
    <a href="https://mermaid.live" target="_blank" rel="noopener" class="text-indigo-600 hover:underline dark:text-indigo-400">mermaid.live</a>
    untuk melihat diagramnya.
</p>

@php
    $relasi = $this->relasiPerModul;
    $implisit = $this->implisitPerModul;
    $keterangan = $this->keteranganModul;
@endphp

<div class="space-y-6">
    @foreach ($this->perModul as $modul => $daftar)
        <div wire:key="modul-{{ Str::slug($modul) }}" class="border border-gray-200 rounded-xl dark:border-gray-700">
            <div class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $modul }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $keterangan[$modul] ?? '' }}</div>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ count($daftar) }} objek · {{ count($relasi[$modul] ?? []) }} FK · {{ count($implisit[$modul] ?? []) }} implisit</span>
                    @if (! empty($relasi[$modul]))
                        <button type="button" class="px-2 py-1 border border-gray-300 rounded-md dark:border-gray-600 hover:bg-white dark:hover:bg-gray-800"
                            x-on:click="salin(@js($this->mermaid($modul)), $el)">Mermaid</button>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5 px-4 py-3">
                @foreach ($daftar as $t)
                    <button type="button" wire:click="pilihTabel('{{ $t['nama'] }}')" x-on:click="go('tabel')"
                        title="{{ $t['kolom'] }} kolom{{ $t['baris'] !== null ? ', ±'.number_format($t['baris'], 0, ',', '.').' baris (statistik)' : '' }}"
                        class="px-2 py-0.5 font-mono text-xs rounded-md border {{ $t['jenis'] === 'VIEW' ? 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-900/30 dark:text-sky-200' : 'border-gray-200 bg-white text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100' }} hover:ring-2 hover:ring-indigo-300">
                        {{ $t['nama'] }}
                    </button>
                @endforeach
            </div>

            @if (! empty($relasi[$modul]) || ! empty($implisit[$modul]))
                <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-xs">
                        <thead class="text-left text-gray-500 bg-gray-50/60 dark:bg-gray-900/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-1.5 font-semibold">Tabel anak . kolom</th>
                                <th class="px-2 py-1.5"></th>
                                <th class="px-4 py-1.5 font-semibold">Tabel induk . kolom</th>
                                <th class="px-4 py-1.5 font-semibold">Constraint</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($relasi[$modul] ?? [] as $r)
                                <tr>
                                    <td class="px-4 py-1 font-mono whitespace-nowrap">{{ $r['anak'] }}<span class="text-gray-400">.{{ $r['kolomAnak'] }}</span></td>
                                    <td class="px-2 py-1 text-gray-400">→</td>
                                    <td class="px-4 py-1 font-mono whitespace-nowrap">{{ $r['induk'] }}<span class="text-gray-400">.{{ $r['kolomInduk'] }}</span></td>
                                    <td class="px-4 py-1 font-mono text-gray-500 dark:text-gray-400">{{ $r['constraint'] }}</td>
                                </tr>
                            @endforeach
                            @foreach ($implisit[$modul] ?? [] as $r)
                                <tr class="bg-amber-50/50 dark:bg-amber-900/10">
                                    <td class="px-4 py-1 font-mono whitespace-nowrap">{{ $r['anak'] }}<span class="text-gray-400">.{{ $r['kolom'] }}</span></td>
                                    <td class="px-2 py-1 text-amber-500">⇢</td>
                                    <td class="px-4 py-1 font-mono whitespace-nowrap">{{ $r['induk'] }}</td>
                                    <td class="px-4 py-1 text-amber-700 dark:text-amber-300">implisit, tanpa FK</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</div>
