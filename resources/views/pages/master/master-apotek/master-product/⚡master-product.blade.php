<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\KolomSatuSehat;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;
    public string $statusFilter  = 'all';
    public string $catFilter     = '';
    public string $suppFilter    = '';

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedStatusFilter(): void  { $this->resetPage(); }
    public function updatedCatFilter(): void     { $this->resetPage(); }
    public function updatedSuppFilter(): void    { $this->resetPage(); }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->searchKeyword = '';
        $this->statusFilter  = 'all';
        $this->catFilter     = '';
        $this->suppFilter    = '';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.product.openCreate');
    }

    public function openEdit(string $productId): void
    {
        $this->dispatch('master.product.openEdit', productId: $productId);
    }

    public function requestDelete(string $productId): void
    {
        $this->dispatch('master.product.requestDelete', productId: $productId);
    }

    public function toggleActive(string $productId): void
    {
        $this->dispatch('master.product.toggleActive', productId: $productId);
    }

    #[On('master.product.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function categories()
    {
        return DB::table('skmst_categories')
            ->select('cat_id', 'cat_desc')
            ->where('active_status', '1')
            ->orderBy('cat_desc')
            ->get();
    }

    #[Computed]
    public function suppliers()
    {
        return DB::table('skmst_suppliers')
            ->select('supp_id', 'supp_name')
            ->where('active_status', '1')
            ->orderBy('supp_name')
            ->get();
    }

    /**
     * Kolom KFA sudah ada di skmst_products?
     *
     * Kolomnya ditambahkan lewat SQL manual, bukan migration — sebelum SQL-nya dijalankan
     * kolom ini belum ada dan menyebutnya di SELECT berarti ORA-00904 (seluruh halaman
     * mati). Dijawab sekali per request oleh KolomSatuSehat.
     */
    #[Computed]
    public function kolomKfaAda(): bool
    {
        return KolomSatuSehat::produkPunyaKfa();
    }

    #[Computed]
    public function rows()
    {
        $kolomList = [
            'p.product_id', 'p.product_name', 'p.product_type', 'p.product_rak',
            'p.cost_price', 'p.sales_price', 'p.margin_persen',
            'p.qty_box', 'p.limit_stock', 'p.active_status',
            'c.cat_desc', 'u.uom_desc', 's.supp_name',
        ];

        if ($this->kolomKfaAda) {
            $kolomList[] = 'p.' . KolomSatuSehat::PRODUK_KFA_KODE;
            $kolomList[] = 'p.' . KolomSatuSehat::PRODUK_KFA_NAMA;
        }

        $q = DB::table('skmst_products AS p')
            ->leftJoin('skmst_categories AS c', 'c.cat_id', '=', 'p.cat_id')
            ->leftJoin('skmst_uoms AS u', 'u.uom_id', '=', 'p.uom_id')
            ->leftJoin('skmst_suppliers AS s', 's.supp_id', '=', 'p.supp_id')
            ->select($kolomList)
            ->orderBy('p.product_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(p.product_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(p.product_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        if ($this->statusFilter === 'active')   $q->where('p.active_status', '1');
        if ($this->statusFilter === 'inactive') $q->where('p.active_status', '0');
        if ($this->catFilter !== '')            $q->where('p.cat_id', $this->catFilter);
        if ($this->suppFilter !== '')           $q->where('p.supp_id', $this->suppFilter);

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Produk Apotek"
        subtitle="Inventory obat &amp; alkes — referensi untuk transaksi penjualan/pembelian" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-2 lg:flex-row">
                        <div class="flex-1">
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari produk (ID / nama)..." class="block w-full" />
                        </div>
                        <div class="grid grid-cols-2 gap-2 lg:flex">
                            <div class="lg:w-48">
                                <x-select-input wire:model.live="catFilter">
                                    <option value="">Semua Kategori</option>
                                    @foreach ($this->categories as $c)
                                        <option value="{{ $c->cat_id }}">{{ $c->cat_desc }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div class="lg:w-48">
                                <x-select-input wire:model.live="suppFilter">
                                    <option value="">Semua Supplier</option>
                                    @foreach ($this->suppliers as $s)
                                        <option value="{{ $s->supp_id }}">{{ $s->supp_name }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div class="lg:w-32">
                                <x-select-input wire:model.live="statusFilter">
                                    <option value="all">Semua Status</option>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </x-select-input>
                            </div>
                            <div class="lg:w-24">
                                <x-select-input wire:model.live="itemsPerPage">
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </x-select-input>
                            </div>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">+ Tambah Produk</x-primary-button>
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
                                <th>PRODUK</th>
                                <th>KATEGORI / UOM</th>
                                <th class="text-right">HPP</th>
                                <th class="text-right">JUAL</th>
                                <th class="text-right">MARGIN%</th>
                                <th class="text-right">LIMIT</th>
                                <th>SUPPLIER</th>
                                <th>STATUS</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="product-{{ $row->product_id }}">
                                    <td class="ds-td-token">{{ $row->product_id }}</td>
                                    <td class="ds-td-strong">
                                        {{ $row->product_name }}
                                        @if ($row->product_rak)
                                            <div class="text-[11px] font-normal text-gray-400">Rak: {{ $row->product_rak }}</div>
                                        @endif
                                        @if ($this->kolomKfaAda)
                                            @if (!empty($row->product_id_satusehat))
                                                <div class="text-[11px] font-normal text-emerald-600 dark:text-emerald-400"
                                                    title="Kode KFA SATUSEHAT — {{ $row->product_name_satusehat ?: $row->product_name }}">
                                                    KFA <span class="font-mono">{{ $row->product_id_satusehat }}</span>
                                                </div>
                                            @else
                                                <div class="text-[11px] font-normal text-amber-600 dark:text-amber-400"
                                                    title="Tanpa kode KFA obat ini tidak bisa dikirim ke SATUSEHAT">
                                                    KFA belum diisi
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        <div>{{ $row->cat_desc ?? '-' }}</div>
                                        <div class="text-gray-500">{{ $row->uom_desc ?? '-' }}</div>
                                    </td>
                                    <td class="ds-td-token text-right">
                                        {{ number_format((float) ($row->cost_price ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="ds-td-token text-right">
                                        {{ number_format((float) ($row->sales_price ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="text-xs text-right">
                                        {{ rtrim(rtrim(number_format((float) ($row->margin_persen ?? 0), 2, ',', '.'), '0'), ',') }}%
                                    </td>
                                    <td class="text-xs text-right">
                                        {{ (int) ($row->limit_stock ?? 0) }}
                                    </td>
                                    <td class="text-xs">{{ $row->supp_name ?? '-' }}</td>
                                    <td>
                                        <x-toggle :current="(string) $row->active_status" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->product_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'AKTIF' : 'NONAKTIF' }}
                                        </x-toggle>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->product_id }}')" />
                                            <x-action-delete
                                                :action="'requestDelete(\'' . $row->product_id . '\')'"
                                                title="Hapus Produk"
                                                message="Yakin hapus produk {{ $row->product_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data produk tidak ditemukan.
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

            <livewire:pages::master.master-apotek.master-product.master-product-actions wire:key="master-product-actions" />

        </div>
    </div>
</div>
