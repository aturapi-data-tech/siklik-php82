{{-- Partial: Rincian Tabel & Kolom — pilih tabel, lihat kolom (PK/FK/tipe) + relasi keluar/masuk. --}}
<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">04 — Struktur</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Rincian Tabel & Kolom</h1>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[260px_1fr]">
    {{-- daftar tabel --}}
    <div class="border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
            <x-input-label for="cariTabel" value="Cari tabel" class="sr-only" />
            <x-text-input id="cariTabel" type="search" wire:model.live.debounce.300ms="cariTabel" placeholder="nama tabel / view" class="block w-full" />
        </div>
        <div class="max-h-[65vh] overflow-y-auto">
            @forelse ($this->daftarNamaTabel as $nama)
                <button type="button" wire:key="pilih-{{ $nama }}" wire:click="pilihTabel('{{ $nama }}')"
                    class="block w-full px-3 py-1 font-mono text-xs text-left {{ $nama === $tabelDipilih ? 'bg-indigo-50 font-semibold text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/40' }}">
                    {{ $nama }}
                </button>
            @empty
                <div class="px-3 py-6 text-xs text-center text-gray-500">Tidak ada.</div>
            @endforelse
        </div>
    </div>

    {{-- rincian --}}
    <div class="min-w-0">
        @if ($tabelDipilih === '')
            <div class="py-16 text-center text-gray-500">Pilih tabel di kiri.</div>
        @else
            @php $rel = $this->relasiTabel; $imp = $this->implisitTabel; @endphp
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <h2 class="font-mono text-xl font-bold text-gray-900 dark:text-white">{{ $tabelDipilih }}</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->modulTabelDipilih }} · {{ count($this->kolom) }} kolom</span>
            </div>

            <div class="mb-6 overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                <div class="overflow-x-auto max-h-[50vh] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-left text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-2 font-semibold">Kolom</th>
                                <th class="px-4 py-2 font-semibold">Tipe</th>
                                <th class="px-4 py-2 font-semibold">Null</th>
                                <th class="px-4 py-2 font-semibold">Kunci</th>
                                <th class="px-4 py-2 font-semibold">Default</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($this->kolom as $k)
                                <tr wire:key="kol-{{ $tabelDipilih }}-{{ $k['nama'] }}">
                                    <td class="px-4 py-1.5 font-mono {{ $k['pk'] ? 'font-bold' : '' }}">{{ $k['nama'] }}</td>
                                    <td class="px-4 py-1.5 font-mono text-gray-600 dark:text-gray-300">{{ $k['tipe'] }}</td>
                                    <td class="px-4 py-1.5 text-gray-500">{{ $k['nullable'] ? 'ya' : 'tidak' }}</td>
                                    <td class="px-4 py-1.5 whitespace-nowrap">
                                        @if ($k['pk']) <x-badge variant="brand">PK</x-badge> @endif
                                        @if ($k['fk'])
                                            <button type="button" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400" wire:click="pilihTabel('{{ $k['fk'] }}')">FK → {{ $k['fk'] }}</button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-1.5 font-mono text-xs text-gray-500">{{ $k['default'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="border border-gray-200 rounded-xl dark:border-gray-700">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900 dark:text-gray-400">Merujuk ke (FK keluar)</div>
                    <ul class="px-4 py-2 space-y-1 font-mono text-xs">
                        @forelse ($rel['keluar'] as $r)
                            <li>.{{ $r['kolomAnak'] }} → <button type="button" class="text-indigo-600 hover:underline dark:text-indigo-400" wire:click="pilihTabel('{{ $r['induk'] }}')">{{ $r['induk'] }}</button>.{{ $r['kolomInduk'] }}</li>
                        @empty
                            <li class="font-sans text-gray-500">tidak ada</li>
                        @endforelse
                    </ul>
                </div>
                <div class="border border-gray-200 rounded-xl dark:border-gray-700">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900 dark:text-gray-400">Dirujuk oleh (FK masuk)</div>
                    <ul class="px-4 py-2 space-y-1 font-mono text-xs">
                        @forelse ($rel['masuk'] as $r)
                            <li><button type="button" class="text-indigo-600 hover:underline dark:text-indigo-400" wire:click="pilihTabel('{{ $r['anak'] }}')">{{ $r['anak'] }}</button>.{{ $r['kolomAnak'] }} → .{{ $r['kolomInduk'] }}</li>
                        @empty
                            <li class="font-sans text-gray-500">tidak ada</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if (! empty($imp))
                <div class="mt-4 border rounded-xl border-amber-200 dark:border-amber-900/50">
                    <div class="px-4 py-2 text-xs font-semibold uppercase bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">Relasi implisit (tanpa FK, dijaga oleh kode)</div>
                    <ul class="px-4 py-2 space-y-1 font-mono text-xs">
                        @foreach ($imp as $r)
                            <li>{{ $r['anak'] }}.{{ $r['kolom'] }} ⇢ <button type="button" class="text-indigo-600 hover:underline dark:text-indigo-400" wire:click="pilihTabel('{{ $r['anak'] === $tabelDipilih ? $r['induk'] : $r['anak'] }}')">{{ $r['induk'] }}</button></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>
</div>
