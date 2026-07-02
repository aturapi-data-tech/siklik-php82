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
    public string $parentFilter  = ''; // filter by parent prov_id

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
        $this->dispatch('master.kota-toko.openCreate');
    }

    public function openEdit(string $kotaId): void
    {
        $this->dispatch('master.kota-toko.openEdit', kotaId: $kotaId);
    }

    public function requestDelete(string $kotaId): void
    {
        $this->dispatch('master.kota-toko.requestDelete', kotaId: $kotaId);
    }

    #[On('master.kota-toko.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /** Daftar provinsi-toko untuk filter dropdown */
    #[Computed]
    public function parents()
    {
        return DB::table('tkmst_provs')
            ->select('prov_id', 'prov_name')
            ->orderBy('prov_name')
            ->get();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('tkmst_kotas AS k')
            ->leftJoin('tkmst_provs AS p', 'p.prov_id', '=', 'k.prov_id')
            ->select('k.kota_id', 'k.kota_name', 'k.prov_id', 'p.prov_name')
            ->orderBy('p.prov_name')
            ->orderBy('k.kota_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(k.kota_name) LIKE ?', ["%{$kw}%"]);
        }

        if ($this->parentFilter !== '') {
            $q->where('k.prov_id', (int) $this->parentFilter);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Kota Toko / Kota"
        subtitle="Master kota-toko/kota (RS) — anak dari Provinsi Toko" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-2 lg:flex-row lg:max-w-2xl">
                        <div class="flex-1">
                            <x-input-label for="searchKeyword" value="Cari Kota Toko" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari kota-toko/kota..."
                                class="block w-full" />
                        </div>
                        <div class="w-full lg:w-64">
                            <x-input-label for="parentFilter" value="Filter Provinsi Toko" class="sr-only" />
                            <x-select-input id="parentFilter" wire:model.live="parentFilter">
                                <option value="">Semua Provinsi Toko</option>
                                @foreach ($this->parents as $p)
                                    <option value="{{ $p->prov_id }}">{{ $p->prov_name }}</option>
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
                            + Tambah Kota Toko
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">KOTA</th>
                                <th class="px-4 py-3 font-semibold">PROVINSI</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="kota-toko-{{ $row->kota_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->kota_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->kota_name }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                                        {{ $row->prov_name ?? '— provinsi-toko tidak ditemukan —' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->kota_id }}')" />

                                            <x-action-delete
                                                :action="'requestDelete(\'' . $row->kota_id . '\')'"
                                                title="Hapus Kota Toko"
                                                message="Yakin hapus kota-toko {{ $row->kota_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data kota-toko tidak ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-apotek.master-kota-toko.master-kota-toko-actions wire:key="master-kota-toko-actions" />

        </div>
    </div>
</div>
