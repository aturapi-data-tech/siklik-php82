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

    public function openCreate(): void { $this->dispatch('master.tucico.openCreate'); }
    public function openEdit(string $id): void { $this->dispatch('master.tucico.openEdit', tucicoId: $id); }
    public function requestDelete(string $id): void { $this->dispatch('master.tucico.requestDelete', tucicoId: $id); }

    public function toggleActive(string $id): void
    {
        $cur = (string) DB::table('tkacc_tucicos')->where('tucico_id', $id)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('tkacc_tucicos')->where('tucico_id', $id)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success', message: 'Status diubah ke ' . ($next === '1' ? 'Aktif' : 'Non-aktif'));
        $this->resetPage();
    }

    #[On('master.tucico.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        $q = DB::table('tkacc_tucicos as t')
            ->leftJoin('tkacc_accountses as a', 'a.acc_id', '=', 't.acc_id')
            ->select('t.tucico_id', 't.tucico_desc', 't.tucico_status',
                't.active_status', 't.acc_id', 'a.acc_desc as acc_name')
            ->orderByRaw("CASE WHEN t.active_status = '1' THEN 0 ELSE 1 END")
            ->orderBy('t.tucico_status')
            ->orderBy('t.tucico_desc');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(t.tucico_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(t.tucico_desc) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(a.acc_desc) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Master TUCICO (Transit Cash In/Out)"
        subtitle="Pos kas transit untuk penerimaan/pengeluaran kas non-transaksi (mis. setoran ke bank, ambil kas dari brankas). Sumber: tkacc_tucicos." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <x-text-input type="text"
                        wire:model.live.debounce.300ms="searchKeyword"
                        placeholder="Cari TUCICO..." class="flex-1 max-w-md" />
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah TUCICO
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
                                <th class="px-4 py-3 font-semibold w-20 text-center">CI/CO</th>
                                <th class="px-4 py-3 font-semibold">AKUN</th>
                                <th class="px-4 py-3 font-semibold w-24 text-center">STATUS</th>
                                <th class="px-4 py-3 font-semibold w-48">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="tucico-{{ $row->tucico_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->tucico_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->tucico_desc }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if ((string) $row->tucico_status === 'CI')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">CI</span>
                                        @elseif ((string) $row->tucico_status === 'CO')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-rose-100 text-rose-800">CO</span>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $row->tucico_status ?: '—' }}</span>
                                        @endif
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
                                    <td class="px-4 py-3">
                                        <x-toggle :current="(string) $row->active_status"
                                            trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->tucico_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'Aktif' : 'Non-aktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->tucico_id }}')" />
                                            <x-action-delete
                                                :action="'requestDelete(\'' . $row->tucico_id . '\')'"
                                                title="Hapus TUCICO"
                                                message="Yakin hapus {{ $row->tucico_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data TUCICO tidak ditemukan.
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

            <livewire:pages::master.master-akuntansi.master-tucico.master-tucico-actions wire:key="master-tucico-actions" />
        </div>
    </div>
</div>
