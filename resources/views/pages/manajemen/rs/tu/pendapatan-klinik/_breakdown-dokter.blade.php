{{--
    Breakdown total pasien & pendapatan per dokter (Rawat Jalan).
    Props:
      $dokterRj      array of [dr_id, dr_name, poli_desc, jumlah, bpjs, umum, total]
      $periodeLabel  string label periode (mis. "2026" atau "2024–2026")
--}}
<div class="mt-6 space-y-4">

    {{-- RJ — per dokter × poli --}}
    <div class="bg-white border border-emerald-200 rounded-2xl dark:border-emerald-800 dark:bg-gray-900"
         x-data="{ open: true }">
        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
            <div class="flex-1">
                <div class="text-sm font-bold text-emerald-800 dark:text-emerald-200 uppercase tracking-wide">
                    Breakdown Rawat Jalan &mdash; per Dokter &times; Poli ({{ $periodeLabel }})
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ count($dokterRj) }} kombinasi · status final <code>rj_status='L'</code>
                </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="overflow-auto border-t border-emerald-200 dark:border-emerald-800">
            <table class="w-full text-xs text-left text-gray-700 dark:text-gray-300 table-auto">
                <thead class="text-[10px] text-emerald-900 uppercase bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-200">
                    <tr>
                        <th class="px-3 py-2 text-left">Dokter</th>
                        <th class="px-3 py-2 text-left">Poli</th>
                        <th class="px-3 py-2 text-right">Pasien</th>
                        <th class="px-3 py-2 text-right">BPJS</th>
                        <th class="px-3 py-2 text-right">UMUM</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dokterRj as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10">
                            <td class="px-3 py-1.5">{{ $row['dr_name'] }} <span class="text-gray-400 text-[10px]">({{ $row['dr_id'] }})</span></td>
                            <td class="px-3 py-1.5">{{ $row['poli_desc'] }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono text-gray-500">{{ number_format($row['umum'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-3 text-center text-gray-500 italic">Tidak ada data Rawat Jalan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
