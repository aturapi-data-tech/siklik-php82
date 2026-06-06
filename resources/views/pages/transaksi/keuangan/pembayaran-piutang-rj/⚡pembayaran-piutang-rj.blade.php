<?php

/**
 * Pembayaran Piutang RJ.
 *
 * Tujuan: pasien lama datang bayar tagihan RJ yang masih piutang
 * (txn_status='H' AND rj_status='L').
 *
 * Catatan: filter `cek_bayar='1'` di legacy Oracle Forms tidak dipakai —
 * di siklik-php82 kolom itu tidak di-set saat kasir-rj save, jadi cukup
 * pakai txn_status='H' (Hutang/cicilan) + rj_status='L' (pelayanan selesai).
 *
 * Flow:
 *  1) User pilih reg_no pasien via lov-pasien
 *  2) Sistem tampilkan list rj_no piutang + ringkasan tottotal/totbayar/totsisa
 *  3) User klik "Proses Pelunasan" → buka modal pilih cara bayar + tanggal
 *  4) Per rj_no yg sisa>0: insert RSTXN_RJCASHINS, set RSTXN_RJHDRS.txn_status='L', pay_date=tanggal
 *
 * Sumber rumus: form Oracle Forms RSVIEW_RJKASIR (xtogle / g_rj / hitung_*).
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?string $regNo = null;
    public ?array  $pasien = null;

    /* ── LOV Listener ── */
    #[On('lov.selected.piutang-rj-pasien')]
    public function onPasienSelected(string $target, ?array $payload): void
    {
        $this->regNo  = $payload['reg_no'] ?? null;
        $this->pasien = $payload;
    }

    public function clearPasien(): void
    {
        $this->reset(['regNo', 'pasien']);
    }

    /* ── Listener: setelah modal grouping selesai, refresh data ── */
    #[On('piutang-rj.grouped')]
    public function refreshAfterGrouped(): void
    {
        // Auto-clear pasien setelah pelunasan sukses → user balik ke fresh state.
        $this->reset(['regNo', 'pasien']);
        unset($this->rjList, $this->summary);
    }

    /* ── List rj_no piutang utk pasien terpilih ── */
    #[Computed]
    public function rjList()
    {
        if (!$this->regNo) return collect();

        // Dapatkan rj_no piutang dasar
        $headers = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->select([
                'h.rj_no',
                'h.reg_no',
                'p.reg_name',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"),
                'h.rj_date',
                DB::raw('NVL(h.rj_admin,0)  as rj_admin'),
                DB::raw('NVL(h.rs_admin,0)  as rs_admin'),
                DB::raw('NVL(h.poli_price,0) as poli_price'),
                DB::raw('NVL(h.rj_diskon,0)  as rj_diskon'),
                'h.cb_id',
                'h.txn_status',
                'h.rj_status',
                'h.cek_bayar',
            ])
            ->where('h.reg_no', $this->regNo)
            ->where('h.rj_status', 'L')
            ->where('h.txn_status', 'H')
            ->orderBy('h.rj_date')
            ->get();

        if ($headers->isEmpty()) return collect();

        $rjNos = $headers->pluck('rj_no')->all();

        // Aggregasi per rj_no untuk semua kategori biaya
        $sumByRj = function (string $table, string $expr) use ($rjNos) {
            return DB::table($table)
                ->select('rj_no', DB::raw("NVL(SUM($expr),0) as total"))
                ->whereIn('rj_no', $rjNos)
                ->groupBy('rj_no')
                ->pluck('total', 'rj_no');
        };

        $hn    = $sumByRj('rstxn_rjaccdocs',  'accdoc_price');
        $obat  = $sumByRj('rstxn_rjobats',    'qty*price');
        $jk    = $sumByRj('rstxn_rjactemps',  'acte_price');
        $lab   = $sumByRj('rstxn_rjlabs',     'lab_price');
        $jm    = $sumByRj('rstxn_rjactparams','pact_price');
        $rad   = $sumByRj('rstxn_rjrads',     'rad_price');
        $other = $sumByRj('rstxn_rjothers',   'other_price');
        $titip = $sumByRj('rstxn_rjcashins',  'rjc_nominal');

        return $headers->map(function ($r) use ($hn, $obat, $jk, $lab, $jm, $rad, $other, $titip) {
            $rj = $r->rj_no;
            $total = (float) ($hn[$rj]    ?? 0)
                   + (float) ($obat[$rj]  ?? 0)
                   + (float) ($jk[$rj]    ?? 0)
                   + (float) ($lab[$rj]   ?? 0)
                   + (float) ($jm[$rj]    ?? 0)
                   + (float) ($rad[$rj]   ?? 0)
                   + (float) ($other[$rj] ?? 0)
                   + (float) ($r->rj_admin ?? 0)
                   + (float) ($r->rs_admin ?? 0)
                   + (float) ($r->poli_price ?? 0)
                   - (float) ($r->rj_diskon  ?? 0);

            $bayar = (float) ($titip[$rj] ?? 0);
            $sisa  = $total - $bayar;

            return (object) [
                'rj_no'           => $rj,
                'rj_date_display' => $r->rj_date_display,
                'reg_name'        => $r->reg_name,
                'hn'              => (float) ($hn[$rj]    ?? 0),
                'obat'            => (float) ($obat[$rj]  ?? 0),
                'jk'              => (float) ($jk[$rj]    ?? 0),
                'lab'             => (float) ($lab[$rj]   ?? 0),
                'jm'              => (float) ($jm[$rj]    ?? 0),
                'rad'             => (float) ($rad[$rj]   ?? 0),
                'other'           => (float) ($other[$rj] ?? 0),
                'admin'           => (float) ($r->rj_admin ?? 0) + (float) ($r->rs_admin ?? 0),
                'poli_price'      => (float) ($r->poli_price ?? 0),
                'diskon'          => (float) ($r->rj_diskon  ?? 0),
                'total'           => $total,
                'bayar'           => $bayar,
                'sisa'            => $sisa,
                'cek_bayar'       => (string) ($r->cek_bayar ?? ''),
                'is_checked'      => ((string) ($r->cek_bayar ?? '')) === '1',
            ];
        });
    }

    /* ── Toggle cek_bayar 0/NULL ↔ 1 ── */
    public function toggleCekBayar(int $rjNo): void
    {
        $row = DB::table('rstxn_rjhdrs')
            ->where('rj_no', $rjNo)
            ->where('reg_no', $this->regNo)
            ->where('rj_status', 'L')
            ->where('txn_status', 'H')
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tidak valid.');
            return;
        }

        $newVal = ((string) ($row->cek_bayar ?? '')) === '1' ? '0' : '1';

        DB::table('rstxn_rjhdrs')
            ->where('rj_no', $rjNo)
            ->update(['cek_bayar' => $newVal]);

        unset($this->rjList, $this->summary);
    }

    public function toggleAllCekBayar(): void
    {
        if (!$this->regNo) return;

        $allChecked = $this->rjList->every(fn ($r) => $r->is_checked);
        $newVal = $allChecked ? '0' : '1';

        DB::table('rstxn_rjhdrs')
            ->where('reg_no', $this->regNo)
            ->where('rj_status', 'L')
            ->where('txn_status', 'H')
            ->update(['cek_bayar' => $newVal]);

        unset($this->rjList, $this->summary);
    }

    #[Computed]
    public function summary(): array
    {
        $list = $this->rjList;
        $checked = $list->where('is_checked', true);
        return [
            'jumlah'         => $list->count(),
            'jumlah_checked' => $checked->count(),
            'tottotal'       => (float) $list->sum('total'),
            'totbayar'       => (float) $list->sum('bayar'),
            'totsisa'        => (float) $list->sum('sisa'),
            'sisa_checked'   => (float) $checked->sum('sisa'),
        ];
    }

    /* ── Trigger ke modal actions ── */
    public function openGrouping(): void
    {
        if (!$this->regNo) {
            $this->dispatch('toast', type: 'error', message: 'Pilih pasien terlebih dahulu.');
            return;
        }
        if ($this->summary['jumlah'] === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada transaksi piutang untuk pasien ini.');
            return;
        }
        if ($this->summary['jumlah_checked'] === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Centang minimal 1 transaksi yang ingin dilunasi.');
            return;
        }
        if ($this->summary['sisa_checked'] <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'Sisa transaksi terpilih sudah Rp 0.');
            return;
        }

        $this->dispatch('piutang-rj.openGrouping',
            regNo: $this->regNo,
            regName: $this->pasien['reg_name'] ?? '',
            totsisa: $this->summary['sisa_checked'],
            jumlah: $this->summary['jumlah_checked'],
        );
    }
};
?>

<div>
    <x-page-title
        title="Pembayaran Piutang RJ"
        subtitle="Pelunasan tagihan rawat jalan pasien yang belum lunas (status piutang)" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">

            {{-- 1) FORM PILIH PASIEN --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            1. Pilih Pasien
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Cari berdasarkan No RM / Nama / NIK / No BPJS / Alamat
                        </p>
                    </div>
                    @if ($regNo)
                        <x-secondary-button type="button" wire:click="clearPasien"
                            title="Reset pilihan pasien untuk transaksi baru">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Transaksi Baru
                        </x-secondary-button>
                    @endif
                </div>
                <div class="px-5 py-5">
                    <livewire:lov.pasien.lov-pasien
                        target="piutang-rj-pasien"
                        label="Cari Pasien"
                        placeholder="Ketik No RM / Nama / NIK / No BPJS / Alamat..."
                        :initialRegNo="$regNo"
                        wire:key="lov-pasien-piutang-rj-{{ $regNo ?? 'empty' }}" />
                </div>
            </div>

            @if ($regNo)
                {{-- 2) RINGKASAN TOTAL PASIEN --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="px-4 py-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Jumlah Transaksi</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->summary['jumlah'] }}</div>
                    </div>
                    <div class="px-4 py-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Tagihan</div>
                        <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-gray-100">
                            Rp {{ number_format($this->summary['tottotal']) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Sudah Dibayar (Titipan)</div>
                        <div class="mt-1 font-mono text-xl font-bold text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format($this->summary['totbayar']) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 border rounded-2xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Sisa Piutang (Semua)</div>
                        <div class="mt-1 font-mono text-xl font-bold {{ $this->summary['totsisa'] > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                            Rp {{ number_format($this->summary['totsisa']) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 border-2 rounded-2xl
                        {{ $this->summary['sisa_checked'] > 0 ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/30' : 'border-gray-300 bg-gray-50 dark:bg-gray-800/50' }}">
                        <div class="text-xs {{ $this->summary['sisa_checked'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500' }}">
                            Akan Dilunasi ({{ $this->summary['jumlah_checked'] }} dipilih) ✓
                        </div>
                        <div class="mt-1 font-mono text-xl font-bold {{ $this->summary['sisa_checked'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-400' }}">
                            Rp {{ number_format($this->summary['sisa_checked']) }}
                        </div>
                    </div>
                </div>

                {{-- 3) DETAIL PER RJ_NO --}}
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                2. Detail Transaksi Piutang
                            </h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Centang ✓ transaksi yang ingin dilunasi, lalu klik <strong>Proses Pelunasan</strong>.
                                {{ $this->summary['jumlah_checked'] }} dari {{ $this->summary['jumlah'] }} dipilih.
                            </p>
                        </div>
                        <x-primary-button type="button" wire:click="openGrouping"
                            @class(['opacity-50 cursor-not-allowed' => $this->summary['sisa_checked'] <= 0])>
                            Proses Pelunasan →
                        </x-primary-button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    @php
                                        $allChecked = $this->rjList->isNotEmpty() && $this->rjList->every(fn ($r) => $r->is_checked);
                                    @endphp
                                    <th class="px-3 py-2 font-semibold text-center w-16">
                                        <x-toggle :current="$allChecked ? '1' : '0'" trueValue="1" falseValue="0"
                                            wireClick="toggleAllCekBayar" />
                                    </th>
                                    <th class="px-3 py-2 font-semibold">RJ No</th>
                                    <th class="px-3 py-2 font-semibold">Tanggal</th>
                                    <th class="px-3 py-2 font-semibold text-right">Honor Dokter</th>
                                    <th class="px-3 py-2 font-semibold text-right">Obat</th>
                                    <th class="px-3 py-2 font-semibold text-right">Jasa Karyawan</th>
                                    <th class="px-3 py-2 font-semibold text-right">Jasa Medis</th>
                                    <th class="px-3 py-2 font-semibold text-right">Lab</th>
                                    <th class="px-3 py-2 font-semibold text-right">Radiologi</th>
                                    <th class="px-3 py-2 font-semibold text-right">Lain</th>
                                    <th class="px-3 py-2 font-semibold text-right">Adm/Poli</th>
                                    <th class="px-3 py-2 font-semibold text-right">Diskon</th>
                                    <th class="px-3 py-2 font-semibold text-right text-gray-900 dark:text-gray-100">Total</th>
                                    <th class="px-3 py-2 font-semibold text-right text-emerald-700">Bayar</th>
                                    <th class="px-3 py-2 font-semibold text-right text-rose-700">Sisa</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                @forelse($this->rjList as $row)
                                    <tr wire:key="piutang-rj-{{ $row->rj_no }}"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800/60
                                            {{ $row->is_checked ? 'bg-amber-50/60 dark:bg-amber-950/20' : '' }}">
                                        <td class="px-3 py-2">
                                            <x-toggle :current="$row->is_checked ? '1' : '0'" trueValue="1" falseValue="0"
                                                wireClick="toggleCekBayar({{ $row->rj_no }})" />
                                        </td>
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->rj_no }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $row->rj_date_display }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->hn) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->obat) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->jk) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->jm) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->lab) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->rad) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->other) }}</td>
                                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row->admin + $row->poli_price) }}</td>
                                        <td class="px-3 py-2 font-mono text-right text-rose-600">-{{ number_format($row->diskon) }}</td>
                                        <td class="px-3 py-2 font-mono font-semibold text-right">{{ number_format($row->total) }}</td>
                                        <td class="px-3 py-2 font-mono text-right text-emerald-700">{{ number_format($row->bayar) }}</td>
                                        <td class="px-3 py-2 font-mono font-bold text-right text-rose-700">{{ number_format($row->sisa) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                                <p>Tidak ada transaksi piutang untuk pasien ini.</p>
                                                <p class="text-xs text-gray-400">Pasien tidak memiliki tagihan RJ dengan status piutang (txn_status='H').</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($this->rjList->isNotEmpty())
                                <tfoot class="text-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="font-semibold">
                                        <td colspan="12" class="px-3 py-3 text-right">TOTAL SEMUA</td>
                                        <td class="px-3 py-3 font-mono text-right">Rp {{ number_format($this->summary['tottotal']) }}</td>
                                        <td class="px-3 py-3 font-mono text-right text-emerald-700">Rp {{ number_format($this->summary['totbayar']) }}</td>
                                        <td class="px-3 py-3 font-mono text-right text-rose-700">Rp {{ number_format($this->summary['totsisa']) }}</td>
                                    </tr>
                                    @if ($this->summary['jumlah_checked'] > 0)
                                        <tr class="font-semibold bg-amber-50 dark:bg-amber-950/30">
                                            <td colspan="14" class="px-3 py-3 text-right text-amber-800 dark:text-amber-300">
                                                AKAN DILUNASI ({{ $this->summary['jumlah_checked'] }} dipilih)
                                            </td>
                                            <td class="px-3 py-3 font-mono text-right text-amber-800 dark:text-amber-300">
                                                Rp {{ number_format($this->summary['sisa_checked']) }}
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            @else
                <div class="px-6 py-16 text-center bg-white border border-gray-200 border-dashed rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <svg class="w-16 h-16 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Pilih pasien terlebih dahulu untuk melihat daftar piutang.
                    </p>
                </div>
            @endif

            {{-- Modal grouping --}}
            <livewire:pages::transaksi.keuangan.pembayaran-piutang-rj.pembayaran-piutang-rj-actions
                wire:key="pembayaran-piutang-rj-actions" />
        </div>
    </div>
</div>
