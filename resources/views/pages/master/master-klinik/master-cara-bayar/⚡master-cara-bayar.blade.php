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

    public function openCreate(): void
    {
        $this->dispatch('master.cara-bayar.openCreate');
    }

    public function openEdit(string $cbId): void
    {
        $this->dispatch('master.cara-bayar.openEdit', cbId: $cbId);
    }

    public function requestDelete(string $cbId): void
    {
        $this->dispatch('master.cara-bayar.requestDelete', cbId: $cbId);
    }

    #[On('master.cara-bayar.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    public function toggleActive(string $cbId): void
    {
        $current = (string) DB::table('tkacc_carabayars')->where('cb_id', $cbId)->value('active_status');
        $next = $current === '1' ? '0' : '1';

        DB::table('tkacc_carabayars')->where('cb_id', $cbId)->update(['active_status' => $next]);

        $this->dispatch('toast', type: 'success',
            message: 'Status cara bayar diubah ke ' . ($next === '1' ? 'Aktif' : 'Non-aktif'));
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        // tkacc_accountses (akun pusat) — pakai acc_desc bukan acc_name.
        $q = DB::table('tkacc_carabayars as cb')
            ->leftJoin('tkacc_accountses as a', 'a.acc_id', '=', 'cb.acc_id')
            ->select('cb.cb_id', 'cb.cb_desc', 'cb.active_status', 'cb.acc_id', 'a.acc_desc as acc_name')
            ->orderByRaw("CASE WHEN cb.active_status = '1' THEN 0 ELSE 1 END")
            ->orderBy('cb.cb_desc');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(cb.cb_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(cb.cb_desc) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(cb.acc_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(a.acc_desc) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Master Cara Bayar"
        subtitle="Kelola metode pembayaran (Tunai, Transfer, BPJS, dll) — sumber tabel: tkacc_carabayars." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Cara Bayar" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari cara bayar..."
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
                            + Tambah Cara Bayar Baru
                        </x-primary-button>
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
                                <th class="px-4 py-3 font-semibold">AKUN</th>
                                <th class="px-4 py-3 font-semibold w-32 text-center">STATUS</th>
                                <th class="px-4 py-3 font-semibold w-40">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="cara-bayar-{{ $row->cb_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    <td class="px-4 py-3 font-mono text-xs">
                                        {{ $row->cb_id }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold">
                                        {{ $row->cb_desc }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                                        @if (!empty($row->acc_id))
                                            <span class="font-mono">{{ $row->acc_id }}</span>
                                            @if (!empty($row->acc_name))
                                                — {{ $row->acc_name }}
                                            @endif
                                        @else
                                            <span class="italic text-gray-400">— belum dipetakan —</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ((string) $row->active_status === '1')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Aktif</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">Non-aktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->cb_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-secondary-button type="button"
                                                wire:click="toggleActive('{{ $row->cb_id }}')" class="px-2 py-1 text-xs">
                                                {{ (string) $row->active_status === '1' ? 'Non-aktifkan' : 'Aktifkan' }}
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->cb_id . '\')'"
                                                title="Hapus Cara Bayar"
                                                message="Yakin hapus cara bayar {{ $row->cb_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data cara bayar tidak ditemukan.
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

            <livewire:pages::master.master-klinik.master-cara-bayar.master-cara-bayar-actions wire:key="master-cara-bayar-actions" />

        </div>
    </div>
</div>
