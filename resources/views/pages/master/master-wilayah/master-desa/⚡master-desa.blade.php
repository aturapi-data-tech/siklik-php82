<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;
    public string $parentFilter  = ''; // filter by parent kec_id

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedParentFilter(): void  { $this->resetPage(); }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->searchKeyword = '';
        $this->parentFilter  = '';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.desa.openCreate');
    }

    public function openEdit(int $desId): void
    {
        $this->dispatch('master.desa.openEdit', desId: $desId);
    }

    public function requestDelete(int $desId): void
    {
        $this->dispatch('master.desa.requestDelete', desId: $desId);
    }

    #[On('master.desa.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /** Daftar kecamatan untuk filter dropdown */
    #[Computed]
    public function parents()
    {
        return DB::table('skmst_kecamatans')
            ->select('kec_id', 'kec_name')
            ->orderBy('kec_name')
            ->get();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('skmst_desas AS k')
            ->leftJoin('skmst_kecamatans AS p', 'p.kec_id', '=', 'k.kec_id')
            ->select('k.des_id', 'k.des_name', 'k.kec_id', 'p.kec_name')
            ->orderBy('p.kec_name')
            ->orderBy('k.des_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(k.des_name) LIKE ?', ["%{$kw}%"]);
        }

        if ($this->parentFilter !== '') {
            $q->where('k.kec_id', (int) $this->parentFilter);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Desa / Kota"
        subtitle="Master desa/kota (RS) — anak dari Kecamatan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-2 lg:flex-row lg:max-w-2xl">
                        <div class="flex-1">
                            <x-input-label for="searchKeyword" value="Cari Desa" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari desa/kota..."
                                class="block w-full" />
                        </div>
                        <div class="w-full lg:w-64">
                            <x-input-label for="parentFilter" value="Filter Kecamatan" class="sr-only" />
                            <x-select-input id="parentFilter" wire:model.live="parentFilter">
                                <option value="">Semua Kecamatan</option>
                                @foreach ($this->parents as $p)
                                    <option value="{{ $p->kec_id }}">{{ $p->kec_name }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Desa
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>ID</th>
                                <th>DESA/KOTA</th>
                                <th>PROVINSI</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="desa-{{ $row->des_id }}">

                                    <td class="ds-td-token">{{ $row->des_id }}</td>
                                    <td class="ds-td-strong">{{ $row->des_name }}</td>
                                    <td class="text-xs text-muted dark:text-gray-400">
                                        {{ $row->kec_name ?? '— kecamatan tidak ditemukan —' }}
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit({{ $row->des_id }})" />

                                            <x-action-delete :action="'requestDelete(' . $row->des_id . ')'"
                                                title="Hapus Desa"
                                                message="Yakin hapus desa {{ $row->des_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data desa tidak ditemukan.
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

            <livewire:pages::master.master-wilayah.master-desa.master-desa-actions wire:key="master-desa-actions" />

        </div>
    </div>
</div>
