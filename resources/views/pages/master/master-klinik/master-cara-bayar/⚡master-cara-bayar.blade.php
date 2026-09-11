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
        $current = (string) DB::table('skacc_carabayars')->where('cb_id', $cbId)->value('active_status');
        $next = $current === '1' ? '0' : '1';

        DB::table('skacc_carabayars')->where('cb_id', $cbId)->update(['active_status' => $next]);

        $this->dispatch('toast', type: 'success',
            message: 'Status cara bayar diubah ke ' . ($next === '1' ? 'Aktif' : 'Non-aktif'));
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        // skacc_accountses (akun pusat) — pakai acc_desc bukan acc_name.
        $q = DB::table('skacc_carabayars as cb')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 'cb.acc_id')
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
        subtitle="Kelola metode pembayaran (Tunai, Transfer, BPJS, dll) — sumber tabel: skacc_carabayars." />

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
                                <th>AKUN</th>
                                <th class="w-32 ds-c">STATUS</th>
                                <th class="w-40">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="cara-bayar-{{ $row->cb_id }}">

                                    <td class="ds-td-token">
                                        {{ $row->cb_id }}
                                    </td>
                                    <td class="ds-td-strong">
                                        {{ $row->cb_desc }}
                                    </td>
                                    <td class="text-xs text-muted dark:text-gray-400">
                                        @if (!empty($row->acc_id))
                                            <span class="font-mono">{{ $row->acc_id }}</span>
                                            @if (!empty($row->acc_name))
                                                — {{ $row->acc_name }}
                                            @endif
                                        @else
                                            <span class="italic text-gray-400">— belum dipetakan —</span>
                                        @endif
                                    </td>
                                    <td class="ds-c">
                                        @if ((string) $row->active_status === '1')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Aktif</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">Non-aktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->cb_id }}')" />
                                            <x-toggle :current="(string) $row->active_status" trueValue="1" falseValue="0"
                                                wireClick="toggleActive('{{ $row->cb_id }}')">
                                                {{ (string) $row->active_status === '1' ? 'AKTIF' : 'NONAKTIF' }}
                                            </x-toggle>
                                            <x-action-delete :action="'requestDelete(\'' . $row->cb_id . '\')'" title="Hapus Cara Bayar"
                                                message="Yakin hapus cara bayar {{ $row->cb_desc }}?" />
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

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-klinik.master-cara-bayar.master-cara-bayar-actions wire:key="master-cara-bayar-actions" />

        </div>
    </div>
</div>
