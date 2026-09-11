<?php

use Livewire\Component;

new class extends Component {
    public string $mode = 'bulanan'; // 'bulanan' (1 tahun, breakdown bulan) | 'tahunan' (multi-tahun, breakdown tahun)

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin', 'Tu']), 403);
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['bulanan', 'tahunan'], true)) {
            $this->mode = $mode;
        }
    }
};
?>

<div>
    <x-page-title
        title="Pendapatan Klinik"
        subtitle="Total pendapatan dari Administrasi Rawat Jalan + Penjualan Bebas Apotek — Tahunan (1 tahun, breakdown per bulan) atau Multi-Tahun (rentang tahun, breakdown per tahun). Status final saja: RJ rj_status='L'." />

    @hasanyrole('Admin|Tu')
    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TAB SWITCHER --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Periode:</span>
                    <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <button type="button" wire:click="setMode('bulanan')"
                            class="px-4 py-2 text-sm font-medium transition-colors
                                {{ $mode === 'bulanan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="1 tahun — breakdown per bulan (Januari–Desember)">
                            Tahunan
                        </button>
                        <button type="button" wire:click="setMode('tahunan')"
                            class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                {{ $mode === 'tahunan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="Rentang tahun yyyy–yyyy — breakdown per tahun">
                            Multi-Tahun
                        </button>
                    </div>
                </div>
            </div>

            {{-- Shared Chart.js loader (self-contained; guard double-register) --}}
            @once
                <script>
                    window.chartPendapatanKlinik = window.chartPendapatanKlinik || function(data) {
                        return {
                            chart: null,
                            _loadChartJs() {
                                return new Promise((resolve, reject) => {
                                    if (window.Chart) return resolve();
                                    let tag = document.querySelector('script[data-chartjs]');
                                    if (tag) {
                                        tag.addEventListener('load', resolve);
                                        tag.addEventListener('error', reject);
                                        return;
                                    }
                                    tag = document.createElement('script');
                                    tag.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                                    tag.defer = tag.async = true;
                                    tag.dataset.chartjs = '';
                                    tag.onload = resolve;
                                    tag.onerror = reject;
                                    document.head.appendChild(tag);
                                });
                            },
                            async init() {
                                await this._loadChartJs();
                                const canvas = this.$refs.canvas;
                                if (!canvas || this.chart) return;
                                this.chart = new Chart(canvas.getContext('2d'), {
                                    type: 'bar',
                                    data: {
                                        labels: data.labels,
                                        // Stack: BPJS (RJ) vs UMUM (RJ + Apotek).
                                        datasets: [
                                            { label: 'RJ BPJS',     data: data.rj_bpjs,  backgroundColor: 'rgba(5,150,105,0.85)',  borderWidth: 0, stack: 'bpjs' },
                                            { label: 'RJ UMUM',      data: data.rj_umum,  backgroundColor: 'rgba(16,185,129,0.45)', borderWidth: 0, stack: 'umum' },
                                            { label: 'Apotek (Bebas)', data: data.apt_umum, backgroundColor: 'rgba(245,158,11,0.55)', borderWidth: 0, stack: 'umum' },
                                            { type: 'line', label: 'Total', data: data.total, borderColor: 'rgb(71,85,105)', backgroundColor: 'rgba(71,85,105,0.1)', borderWidth: 2, pointRadius: 3, tension: 0.3, fill: false },
                                        ],
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        interaction: { mode: 'index', intersect: false },
                                        plugins: {
                                            legend: { position: 'top', align: 'end' },
                                            tooltip: {
                                                callbacks: {
                                                    label: (ctx) => `${ctx.dataset.label}: Rp ${Number(ctx.parsed.y || 0).toLocaleString('id-ID')}`,
                                                },
                                            },
                                        },
                                        scales: {
                                            x: { stacked: true, grid: { display: false } },
                                            y: { stacked: true, beginAtZero: true, ticks: { callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID') } },
                                        },
                                    },
                                });
                            },
                            destroy() {
                                if (this.chart) { this.chart.destroy(); this.chart = null; }
                            },
                        };
                    };
                </script>
            @endonce

            {{-- CHILD CONTENT --}}
            @if ($mode === 'bulanan')
                <livewire:pages::manajemen.rs.tu.pendapatan-klinik.pendapatan-klinik-bulanan wire:key="pendapatan-klinik-bulanan" />
            @else
                <livewire:pages::manajemen.rs.tu.pendapatan-klinik.pendapatan-klinik-tahunan wire:key="pendapatan-klinik-tahunan" />
            @endif

        </div>
    </div>
    @else
        <div class="p-6 text-sm text-rose-600 dark:text-rose-400">Anda tidak memiliki akses ke halaman ini.</div>
    @endhasanyrole
</div>
