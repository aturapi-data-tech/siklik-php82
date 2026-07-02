<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\PcareTrait;

new class extends Component {
    use WithPagination, PcareTrait;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public int $itemsPerPage = 7;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->searchKeyword = '';
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.poli.openCreate');
    }

    public function openEdit(string $poliId): void
    {
        $this->dispatch('master.poli.openEdit', poliId: $poliId);
    }

    /* -------------------------
    | Request Delete (delegate ke actions)
    * ------------------------- */
    public function requestDelete(string $poliId): void
    {
        $this->dispatch('master.poli.requestDelete', poliId: $poliId);
    }

    /* -------------------------
     | Sync poli BPJS PCare — auto-fill kd_poli_bpjs
     | Match by poli_desc (case-insensitive substring).
     * ------------------------- */
    public function syncBpjs(): void
    {
        try {
            $response = $this->getPoliFktp(0, 100)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $msg  = $response['metadata']['message'] ?? '';

            if ($code != 200) {
                $this->dispatch('toast', type: 'error',
                    message: 'BPJS getPoliFktp gagal: ' . $msg, title: 'BPJS Error');
                return;
            }

            $bpjsList = $response['response']['list'] ?? $response['response'] ?? [];
            if (!is_array($bpjsList) || empty($bpjsList)) {
                $this->dispatch('toast', type: 'warning',
                    message: 'Daftar poli BPJS kosong.', title: 'BPJS');
                return;
            }

            $polisLocal = DB::table('rsmst_polis')->select('poli_id', 'poli_desc', 'kd_poli_bpjs')->get();
            $matched = 0;
            $skipped = 0;

            foreach ($bpjsList as $bpjsPoli) {
                $bpjsKode = $bpjsPoli['kdPoli']  ?? $bpjsPoli['kode']  ?? null;
                $bpjsName = $bpjsPoli['nmPoli']  ?? $bpjsPoli['nama']  ?? null;
                if (!$bpjsKode || !$bpjsName) continue;

                // Match by poli_desc (case-insensitive substring both ways)
                $bpjsUp = mb_strtoupper($bpjsName);
                $localMatches = $polisLocal->filter(fn($p) =>
                    str_contains(mb_strtoupper($p->poli_desc ?? ''), $bpjsUp) ||
                    str_contains($bpjsUp, mb_strtoupper($p->poli_desc ?? ''))
                );

                if ($localMatches->count() === 1) {
                    $local = $localMatches->first();
                    if ($local->kd_poli_bpjs !== $bpjsKode) {
                        DB::table('rsmst_polis')
                            ->where('poli_id', $local->poli_id)
                            ->update(['kd_poli_bpjs' => $bpjsKode]);
                        $matched++;
                    }
                } else {
                    $skipped++;
                }
            }

            $this->dispatch('toast', type: 'success',
                message: "Sync BPJS selesai. Updated: {$matched}, Skipped (no match / ambigu): {$skipped}",
                title: 'BPJS Sync', duration: 6000);
        } catch (\Exception $e) {
            \Log::error('Master-poli syncBpjs exception', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error',
                message: 'Error: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }
    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.poli.saved')]
    public function refreshAfterSaved(): void
    {
        // resetPage kadang tidak trigger kalau sudah di page 1 → paksa refresh
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsmst_polis')->select('poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid')->orderBy('poli_desc', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('poli_id', $searchKeyword);
                }

                $subQuery
                    ->orWhereRaw('UPPER(poli_desc) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(kd_poli_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(poli_uuid) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>


<div>

    <x-page-title
        title="Master Poli"
        subtitle="Kelola data poli & ruangan untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Poli" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari poli..." class="block w-full" />
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-secondary-button type="button" wire:click="syncBpjs"
                            wire:loading.attr="disabled" wire:target="syncBpjs"
                            title="Sync kd_poli_bpjs dari BPJS PCare">
                            <span wire:loading.remove wire:target="syncBpjs">Sync BPJS</span>
                            <span wire:loading wire:target="syncBpjs">Syncing...</span>
                        </x-secondary-button>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Poli Baru
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>


            {{-- TABLE WRAPPER: card (flex-fill, scroll internal) --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA (yang boleh scroll) --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD (optional sticky) --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">POLI</th>
                                <th class="px-4 py-3 font-semibold">BPJS</th>
                                <th class="px-4 py-3 font-semibold">UUID</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="poli-row-{{ $row->poli_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3">{{ $row->poli_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->poli_desc }}</td>
                                    <td class="px-4 py-3">{{ $row->kd_poli_bpjs }}</td>
                                    <td class="px-4 py-3">{{ $row->poli_uuid }}</td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->poli_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->poli_id . '\')'" title="Hapus Poli"
                                                message="Yakin hapus data poli {{ $row->poli_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION STICKY di bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-klinik.master-poli.master-poli-actions wire:key="master-poli-actions" />
        </div>
    </div>
</div>
