<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int $itemsPerPage = 10;
    public string $filterBulan = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->searchKeyword = '';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    /* ── Child modal triggers ── */
    public function openCreate(): void
    {
        $this->dispatch('penerimaan-kas.openCreate');
    }

    public function openEdit(string $ciNo): void
    {
        $this->dispatch('penerimaan-kas.openEdit', ciNo: $ciNo);
    }

    public function requestDelete(string $ciNo): void
    {
        $this->dispatch('penerimaan-kas.requestDelete', ciNo: $ciNo);
    }

    #[On('penerimaan-kas.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* ── Query — Penerimaan Kas TU = SKTXN_TUCASHINS ── */
    #[Computed]
    public function baseQuery()
    {
        $query = DB::table('sktxn_tucashins as a')
            ->leftJoin('skacc_tucicos as t', 'a.tucico_id', '=', 't.tucico_id')
            ->leftJoin('skmst_kasirs as k', 'a.kasir_id', '=', 'k.kasir_id')
            ->leftJoin('skacc_carabayars as cb', 'a.cb_id', '=', 'cb.cb_id')
            ->select([
                'a.ci_no',
                DB::raw("to_char(a.ci_date,'dd/mm/yyyy hh24:mi:ss') as ci_date_display"),
                'a.ci_desc',
                'a.ci_nominal',
                'a.ci_status',
                'a.tucico_id', 't.tucico_desc',
                'a.kasir_id', 'k.kasir_name',
                'a.cb_id', 'cb.cb_desc',
            ])
            ->orderByDesc('a.ci_date')
            ->orderByDesc('a.ci_no');

        if ($this->searchKeyword !== '') {
            $upper = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(a.ci_desc) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(t.tucico_desc) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(cb.cb_desc) LIKE ?', ["%{$upper}%"])
                  ->orWhere('a.ci_no', 'like', "%{$this->searchKeyword}%");
            });
        }

        if ($this->filterBulan !== '') {
            $query->whereRaw("TO_CHAR(a.ci_date,'MM/YYYY') = ?", [$this->filterBulan]);
        }

        return $query;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Penerimaan Kas TU"
        subtitle="Pencatatan penerimaan kas (Cash-In) di luar transaksi pelayanan klinik" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-40">
                            <x-input-label value="Bulan" class="sr-only" />
                            <x-text-input type="text" wire:model.live.debounce.300ms="filterBulan" placeholder="mm/yyyy" class="block w-full" />
                        </div>
                        <div class="w-full lg:max-w-md">
                            <x-input-label value="Cari" class="sr-only" />
                            <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari keterangan / akun..." class="block w-full" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label value="Per halaman" class="sr-only" />
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Penerimaan Kas
                        </x-primary-button>

                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>NO</th>
                                <th>TANGGAL</th>
                                <th>KETERANGAN</th>
                                <th class="text-right">NOMINAL</th>
                                <th>KATEGORI (TUCICO)</th>
                                <th>CARA BAYAR</th>
                                <th>KASIR</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="ci-row-{{ $row->ci_no }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="ds-td-token whitespace-nowrap">{{ $row->ci_no }}</td>
                                    <td class="whitespace-nowrap">
                                        {{ $row->ci_date_display ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $row->ci_desc ?? '-' }}
                                    </td>
                                    <td class="ds-td-token text-right whitespace-nowrap">Rp {{ number_format($row->ci_nominal ?? 0) }}</td>
                                    <td>
                                        <div>{{ $row->tucico_desc ?? '-' }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $row->tucico_id }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $row->cb_desc ?? '-' }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $row->cb_id }}</div>
                                    </td>
                                    <td>
                                        {{ $row->kasir_name ?? $row->kasir_id ?? '-' }}
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->ci_no }}')">
                                                Edit
                                            </x-secondary-button>
                                            @can('kas.hapusTransaksi')
                                                <x-hapus-button :action="'requestDelete(\'' . $row->ci_no . '\')'" title="Hapus Transaksi" message="Yakin ingin menghapus transaksi #{{ $row->ci_no }}?" />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data penerimaan kas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::transaksi.keuangan.penerimaan-kas-tu.penerimaan-kas-tu-actions wire:key="penerimaan-kas-tu-actions" />
        </div>
    </div>
</div>
