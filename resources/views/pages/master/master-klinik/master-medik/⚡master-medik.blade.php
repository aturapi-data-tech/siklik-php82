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
        $this->dispatch('master.medik.openCreate');
    }

    public function openEdit(string $medikNo): void
    {
        $this->dispatch('master.medik.openEdit', medikNo: $medikNo);
    }

    public function requestDelete(string $medikNo): void
    {
        $this->dispatch('master.medik.requestDelete', medikNo: $medikNo);
    }

    #[On('master.medik.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_medik')
            ->select('medik_no', 'medik_name', 'condition', 'kapasiti', 'jml', 'age', 'bln',
                     'sertifikat', 'izin', 'tgl_buy')
            ->orderBy('medik_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(medik_no) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(medik_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(sertifikat) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Alat Medis"
        subtitle="Tracking alat medis klinik — kondisi, sertifikat, izin, kapasitas" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari alat medis (no/nama/sertifikat)..." class="block w-full" />
                    </div>
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
                        <x-primary-button type="button" wire:click="openCreate">+ Tambah Alat Medis</x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">NO</th>
                                <th class="px-4 py-3 font-semibold">NAMA ALAT</th>
                                <th class="px-4 py-3 font-semibold">KONDISI</th>
                                <th class="px-4 py-3 font-semibold text-right">JUMLAH</th>
                                <th class="px-4 py-3 font-semibold">UMUR</th>
                                <th class="px-4 py-3 font-semibold">SERTIFIKAT</th>
                                <th class="px-4 py-3 font-semibold">IZIN</th>
                                <th class="px-4 py-3 font-semibold">TGL BELI</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="medik-{{ $row->medik_no }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->medik_no }}</td>
                                    <td class="px-4 py-3 font-semibold">
                                        {{ $row->medik_name ?? '-' }}
                                        @if ($row->kapasiti)
                                            <div class="text-[11px] font-normal text-gray-400">Kap: {{ $row->kapasiti }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ $row->condition ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-xs">{{ (int) ($row->jml ?? 0) }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        @php $age = (int) ($row->age ?? 0); $bln = (int) ($row->bln ?? 0); @endphp
                                        @if ($age || $bln) {{ $age }}th {{ $bln }}bln @else - @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ $row->sertifikat ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $row->izin ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ $row->tgl_buy ? \Carbon\Carbon::parse($row->tgl_buy)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->medik_no }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->medik_no . '\')'" title="Hapus Alat Medis"
                                                message="Yakin hapus alat {{ $row->medik_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data alat medis tidak ditemukan.
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

            <livewire:pages::master.master-klinik.master-medik.master-medik-actions wire:key="master-medik-actions" />

        </div>
    </div>
</div>
