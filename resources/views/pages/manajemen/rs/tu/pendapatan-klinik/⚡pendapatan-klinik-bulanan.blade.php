<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Tu\PendapatanKlinikTrait;

new class extends Component {
    use PendapatanKlinikTrait;

    public int $filterTahun;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin', 'Tu']), 403);
        $this->filterTahun = Carbon::now()->year;
    }

    public function resetFilters(): void
    {
        $this->filterTahun = Carbon::now()->year;
    }

    private function periodeRange(): array
    {
        $start = Carbon::create($this->filterTahun, 1, 1)->startOfYear();
        $end = (clone $start)->endOfYear();
        return [$start, $end];
    }

    #[Computed]
    public function rows(): array
    {
        [$start, $end] = $this->periodeRange();
        $agg = $this->buildPendapatanKlinikAggregate($start, $end, 'MM');

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $row = $agg[$key] ?? ['rj_bpjs' => 0, 'rj_umum' => 0, 'apt_umum' => 0, 'bpjs' => 0, 'umum' => 0, 'total' => 0];
            $row['label'] = $this->bulanLabelPendapatan($m);
            $result[] = $row;
        }
        return $result;
    }

    #[Computed]
    public function totals(): array
    {
        return $this->totalsPendapatanKlinik($this->rows);
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->chartDataPendapatanKlinik($this->rows);
    }

    #[Computed]
    public function dokterRj(): array
    {
        [$start, $end] = $this->periodeRange();
        return $this->dokterBreakdownRjKlinik($start, $end);
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        $chartKey = md5("klinik-bulanan-{$filterTahun}");
    @endphp

    {{-- TOOLBAR --}}
    <div class="mt-4 p-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-auto">
                <x-input-label value="Tahun" />
                <x-text-input type="number" wire:model.live.debounce.500ms="filterTahun" min="2000" max="2099" maxlength="4"
                    class="mt-1 block w-full sm:w-32 !font-bold" />
            </div>
            <div class="ml-auto">
                <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">Reset</x-secondary-button>
            </div>
        </div>

        {{-- SUMMARY --}}
        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
            <div class="p-2 bg-emerald-50 border border-emerald-200 rounded-lg dark:border-emerald-800 dark:bg-emerald-900/20">
                <div class="text-emerald-700 dark:text-emerald-300 uppercase">RJ BPJS</div>
                <div class="font-mono text-sm font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($tot['rj_bpjs'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-emerald-50/50 border border-emerald-200 rounded-lg dark:border-emerald-800/50 dark:bg-emerald-900/10">
                <div class="text-emerald-600 dark:text-emerald-400 uppercase">RJ UMUM</div>
                <div class="font-mono text-sm font-bold text-emerald-800 dark:text-emerald-200">{{ number_format($tot['rj_umum'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-amber-50 border border-amber-200 rounded-lg dark:border-amber-800 dark:bg-amber-900/20">
                <div class="text-amber-700 dark:text-amber-300 uppercase">Apotek (Bebas)</div>
                <div class="font-mono text-sm font-bold text-amber-900 dark:text-amber-100">{{ number_format($tot['apt_umum'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-slate-100 border border-slate-300 rounded-lg dark:border-slate-600 dark:bg-slate-800">
                <div class="text-slate-700 dark:text-slate-300 uppercase font-semibold">Total UMUM</div>
                <div class="font-mono text-sm font-extrabold text-slate-900 dark:text-slate-100">{{ number_format($tot['umum'], 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="mt-2 p-3 bg-slate-100 border border-slate-300 rounded-lg dark:border-slate-600 dark:bg-slate-800 flex items-baseline justify-between">
            <div class="text-xs text-slate-700 dark:text-slate-300 uppercase font-semibold">Grand Total {{ $filterTahun }}</div>
            <div class="font-mono text-lg font-extrabold text-slate-900 dark:text-slate-100">Rp {{ number_format($tot['total'], 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- CHART --}}
    <div class="mt-4 p-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
            Tren pendapatan {{ $filterTahun }} &mdash; per bulan: <span class="text-emerald-600 dark:text-emerald-400 font-bold">RJ (BPJS+UMUM)</span> + <span class="text-amber-600 dark:text-amber-400 font-bold">Apotek</span>
        </div>
        <div class="h-80" wire:ignore wire:key="chart-{{ $chartKey }}"
            x-data="chartPendapatanKlinik(@js($this->chartData))" x-init="init()" x-on:destroy="destroy()">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="mt-4 overflow-auto bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-xs text-left text-gray-700 dark:text-gray-300 table-auto">
            <thead class="text-[10px] text-gray-900 uppercase bg-gray-100 dark:bg-gray-900 dark:text-gray-100">
                <tr>
                    <th rowspan="2" class="px-3 py-2 text-left border-r border-gray-300 dark:border-gray-600">Bulan</th>
                    <th colspan="2" class="px-3 py-1 text-center border-r border-gray-300 dark:border-gray-600 bg-emerald-100 dark:bg-emerald-900/30">Rawat Jalan</th>
                    <th rowspan="2" class="px-3 py-2 text-right border-r border-gray-300 dark:border-gray-600 bg-amber-100 dark:bg-amber-900/30">Apotek (Bebas)</th>
                    <th rowspan="2" class="px-3 py-2 text-right">Total</th>
                </tr>
                <tr class="text-[10px]">
                    <th class="px-3 py-1 text-right bg-emerald-50 dark:bg-emerald-900/20">BPJS</th>
                    <th class="px-3 py-1 text-right border-r border-gray-300 dark:border-gray-600 bg-emerald-50/50 dark:bg-emerald-900/10">UMUM</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->rows as $row)
                    <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-3 py-2 font-medium border-r border-gray-200 dark:border-gray-700">{{ $row['label'] }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($row['rj_bpjs'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-500 border-r border-gray-200 dark:border-gray-700">{{ number_format($row['rj_umum'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-amber-700 dark:text-amber-300 border-r border-gray-200 dark:border-gray-700">{{ number_format($row['apt_umum'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="font-bold bg-gray-200 dark:bg-gray-700 border-t-2 border-gray-300 dark:border-gray-600">
                    <td class="px-3 py-2 border-r border-gray-300 dark:border-gray-600">Total {{ $filterTahun }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ number_format($tot['rj_bpjs'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-gray-300 dark:border-gray-600">{{ number_format($tot['rj_umum'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono text-amber-800 dark:text-amber-200 border-r border-gray-300 dark:border-gray-600">{{ number_format($tot['apt_umum'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ number_format($tot['total'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @include('pages::manajemen.rs.tu.pendapatan-klinik._breakdown-dokter', [
        'dokterRj'     => $this->dokterRj,
        'periodeLabel' => (string) $filterTahun,
    ])
</div>
