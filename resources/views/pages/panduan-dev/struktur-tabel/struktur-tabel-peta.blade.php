{{-- Partial: Peta Rename lama → baru, dibaca dari database/sql/rename-siklik/peta_nama.csv --}}
<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">02 — Mulai</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Peta Rename: nama lama → nama baru</h1>
<p class="max-w-3xl mb-4 text-sm">
    Sumber: <code>database/sql/rename-siklik/peta_nama.csv</code>, hasil generator
    <code>php artisan siklik:ddl-rename</code> terhadap DB target. Ketik sebagian nama lama, nama baru, atau modul.
</p>

<div class="max-w-md mb-4">
    <x-input-label for="cariPeta" value="Cari" class="sr-only" />
    <x-text-input id="cariPeta" type="search" wire:model.live.debounce.300ms="cari" placeholder="mis. rjhdrs, products, akuntansi" class="block w-full" />
</div>

@php $peta = $this->peta; @endphp
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
    <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 text-left text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 font-semibold">Jenis</th>
                    <th class="px-4 py-2 font-semibold">Nama lama</th>
                    <th class="px-4 py-2 font-semibold">Nama baru</th>
                    <th class="px-4 py-2 font-semibold">Modul</th>
                    <th class="px-4 py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($peta as $p)
                    <tr wire:key="peta-{{ $p['lama'] }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="px-4 py-1.5"><x-badge :variant="$p['jenis'] === 'VIEW' ? 'info' : 'gray'">{{ $p['jenis'] }}</x-badge></td>
                        <td class="px-4 py-1.5 font-mono text-gray-500 line-through decoration-gray-300 dark:text-gray-400">{{ $p['lama'] }}</td>
                        <td class="px-4 py-1.5 font-mono font-semibold text-gray-900 dark:text-white">{{ $p['baru'] }}</td>
                        <td class="px-4 py-1.5">{{ $p['modul'] }}</td>
                        <td class="px-4 py-1.5 text-right whitespace-nowrap">
                            <button type="button" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                wire:click="pilihTabel('{{ $p['baru'] }}')" x-on:click="go('tabel')">rincian →</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-gray-500">Tidak ada yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-2 text-xs text-gray-500 border-t border-gray-200 dark:border-gray-700 dark:text-gray-400">{{ count($peta) }} objek</div>
</div>
