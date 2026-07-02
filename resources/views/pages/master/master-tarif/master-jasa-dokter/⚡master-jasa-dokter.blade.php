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
    public string $statusFilter  = 'all'; // all|active|inactive

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
        $this->dispatch('master.jasa-dokter.openCreate');
    }

    public function openEdit(string $accdocId): void
    {
        $this->dispatch('master.jasa-dokter.openEdit', accdocId: $accdocId);
    }

    public function requestDelete(string $accdocId): void
    {
        $this->dispatch('master.jasa-dokter.requestDelete', accdocId: $accdocId);
    }

    public function toggleActive(string $accdocId): void
    {
        $this->dispatch('master.jasa-dokter.toggleActive', accdocId: $accdocId);
    }

    #[On('master.jasa-dokter.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_accdocs')
            ->select('accdoc_id', 'accdoc_desc', 'accdoc_price', 'active_status')
            ->orderBy('accdoc_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(accdoc_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(accdoc_desc) LIKE ?', ["%{$kw}%"]);
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
        title="Master Jasa Dokter"
        subtitle="Kelola tarif jasa dokter (ACCDOC) untuk billing rawat jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-2 lg:flex-row lg:max-w-2xl">
                        <div class="flex-1">
                            <x-input-label for="searchKeyword" value="Cari Jasa Dokter" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari jasa dokter..."
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
                            + Tambah Jasa Dokter
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
                                <th class="px-4 py-3 font-semibold">DESKRIPSI</th>
                                <th class="px-4 py-3 font-semibold text-right">TARIF</th>
                                <th class="px-4 py-3 font-semibold">STATUS</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="jasa-dokter-{{ $row->accdoc_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->accdoc_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->accdoc_desc }}</td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        Rp {{ number_format((float) $row->accdoc_price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-toggle :current="(string) $row->active_status" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->accdoc_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'AKTIF' : 'NONAKTIF' }}
                                        </x-toggle>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->accdoc_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->accdoc_id . '\')'"
                                                title="Hapus Jasa Dokter"
                                                message="Yakin hapus jasa dokter {{ $row->accdoc_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data jasa dokter tidak ditemukan.
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

            <livewire:pages::master.master-tarif.master-jasa-dokter.master-jasa-dokter-actions wire:key="master-jasa-dokter-actions" />

        </div>
    </div>
</div>
