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
    public string $parentFilter  = ''; // filter by parent kab_id

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
        $this->dispatch('master.kecamatan.openCreate');
    }

    public function openEdit(int $kecId): void
    {
        $this->dispatch('master.kecamatan.openEdit', kecId: $kecId);
    }

    public function requestDelete(int $kecId): void
    {
        $this->dispatch('master.kecamatan.requestDelete', kecId: $kecId);
    }

    #[On('master.kecamatan.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /** Daftar kabupaten untuk filter dropdown */
    #[Computed]
    public function parents()
    {
        return DB::table('skmst_kabupatens')
            ->select('kab_id', 'kab_name')
            ->orderBy('kab_name')
            ->get();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('skmst_kecamatans AS k')
            ->leftJoin('skmst_kabupatens AS p', 'p.kab_id', '=', 'k.kab_id')
            ->select('k.kec_id', 'k.kec_name', 'k.kab_id', 'p.kab_name')
            ->orderBy('p.kab_name')
            ->orderBy('k.kec_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(k.kec_name) LIKE ?', ["%{$kw}%"]);
        }

        if ($this->parentFilter !== '') {
            $q->where('k.kab_id', (int) $this->parentFilter);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Kecamatan / Kota"
        subtitle="Master kecamatan/kota (RS) — anak dari Kabupaten" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-2 lg:flex-row lg:max-w-2xl">
                        <div class="flex-1">
                            <x-input-label for="searchKeyword" value="Cari Kecamatan" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari kecamatan/kota..."
                                class="block w-full" />
                        </div>
                        <div class="w-full lg:w-64">
                            <x-input-label for="parentFilter" value="Filter Kabupaten" class="sr-only" />
                            <x-select-input id="parentFilter" wire:model.live="parentFilter">
                                <option value="">Semua Kabupaten</option>
                                @foreach ($this->parents as $p)
                                    <option value="{{ $p->kab_id }}">{{ $p->kab_name }}</option>
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
                            + Tambah Kecamatan
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
                                <th>KECAMATAN/KOTA</th>
                                <th>PROVINSI</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="kecamatan-{{ $row->kec_id }}">

                                    <td class="ds-td-token">{{ $row->kec_id }}</td>
                                    <td class="ds-td-strong">{{ $row->kec_name }}</td>
                                    <td class="text-xs text-muted dark:text-gray-400">
                                        {{ $row->kab_name ?? '— kabupaten tidak ditemukan —' }}
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit({{ $row->kec_id }})" />

                                            <x-action-delete :action="'requestDelete(' . $row->kec_id . ')'"
                                                title="Hapus Kecamatan"
                                                message="Yakin hapus kecamatan {{ $row->kec_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data kecamatan tidak ditemukan.
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

            <livewire:pages::master.master-wilayah.master-kecamatan.master-kecamatan-actions wire:key="master-kecamatan-actions" />

        </div>
    </div>
</div>
