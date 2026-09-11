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

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->searchKeyword = '';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.prov-toko.openCreate');
    }

    public function openEdit(string $provId): void
    {
        $this->dispatch('master.prov-toko.openEdit', provId: $provId);
    }

    public function requestDelete(string $provId): void
    {
        $this->dispatch('master.prov-toko.requestDelete', provId: $provId);
    }

    #[On('master.prov-toko.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('skmst_provs')
            ->select('prov_id', 'prov_name')
            ->orderBy('prov_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(prov_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(prov_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Provinsi Toko"
        subtitle="Kelola provinsi master untuk modul toko/apotek (Datang sendiri, Rujukan, dll)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Provinsi Toko" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari provinsi toko..."
                            class="block w-full" />
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
                            + Tambah Provinsi Toko Baru
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
                                <th>DESKRIPSI</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="prov-toko-{{ $row->prov_id }}">

                                    <td class="ds-td-token">
                                        {{ $row->prov_id }}
                                    </td>

                                    <td class="ds-td-strong">
                                        {{ $row->prov_name }}
                                    </td>

                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->prov_id }}')" />

                                            <x-action-delete
                                                :action="'requestDelete(\'' . $row->prov_id . '\')'"
                                                title="Hapus Provinsi Toko"
                                                message="Yakin hapus provinsi toko {{ $row->prov_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data provinsi toko tidak ditemukan.
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

            <livewire:pages::master.master-apotek.master-prov-toko.master-prov-toko-actions wire:key="master-prov-toko-actions" />

        </div>
    </div>
</div>
