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
    public string $statusFilter  = 'all';

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedStatusFilter(): void  { $this->resetPage(); }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->searchKeyword = '';
        $this->statusFilter  = 'all';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.supplier.openCreate');
    }

    public function openEdit(string $suppId): void
    {
        $this->dispatch('master.supplier.openEdit', suppId: $suppId);
    }

    public function requestDelete(string $suppId): void
    {
        $this->dispatch('master.supplier.requestDelete', suppId: $suppId);
    }

    public function toggleActive(string $suppId): void
    {
        $this->dispatch('master.supplier.toggleActive', suppId: $suppId);
    }

    #[On('master.supplier.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('skmst_suppliers')
            ->select('supp_id', 'supp_name', 'supp_email', 'supp_phone1', 'supp_phone2', 'supp_address', 'active_status')
            ->orderBy('supp_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(supp_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(supp_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(supp_phone1) LIKE ?', ["%{$kw}%"]);
            });
        }

        if ($this->statusFilter === 'active')   $q->where('active_status', '1');
        if ($this->statusFilter === 'inactive') $q->where('active_status', '0');

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Supplier"
        subtitle="Kelola data supplier obat &amp; alkes untuk modul apotek" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-2 lg:flex-row lg:max-w-2xl">
                        <div class="flex-1">
                            <x-input-label for="searchKeyword" value="Cari Supplier" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari supplier..."
                                class="block w-full" />
                        </div>
                        <div class="w-full lg:w-40">
                            <x-input-label for="statusFilter" value="Status" class="sr-only" />
                            <x-select-input id="statusFilter" wire:model.live="statusFilter">
                                <option value="all">Semua Status</option>
                                <option value="active">Hanya Aktif</option>
                                <option value="inactive">Hanya Nonaktif</option>
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
                            + Tambah Supplier
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
                                <th>NAMA</th>
                                <th>TELEPON</th>
                                <th>EMAIL</th>
                                <th>STATUS</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="supplier-{{ $row->supp_id }}">

                                    <td class="ds-td-token">{{ $row->supp_id }}</td>
                                    <td class="ds-td-strong">{{ $row->supp_name }}</td>
                                    <td>
                                        @if ($row->supp_phone1) <div>{{ $row->supp_phone1 }}</div> @endif
                                        @if ($row->supp_phone2) <div class="text-xs text-gray-500">{{ $row->supp_phone2 }}</div> @endif
                                    </td>
                                    <td class="text-xs">{{ $row->supp_email ?? '-' }}</td>
                                    <td>
                                        <x-toggle :current="(string) $row->active_status" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->supp_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'AKTIF' : 'NONAKTIF' }}
                                        </x-toggle>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->supp_id }}')" />

                                            <x-action-delete
                                                :action="'requestDelete(\'' . $row->supp_id . '\')'"
                                                title="Hapus Supplier"
                                                message="Yakin hapus supplier {{ $row->supp_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data supplier tidak ditemukan.
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

            <livewire:pages::master.master-apotek.master-supplier.master-supplier-actions wire:key="master-supplier-actions" />

        </div>
    </div>
</div>
