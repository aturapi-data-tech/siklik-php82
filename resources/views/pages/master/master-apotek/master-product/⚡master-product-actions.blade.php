<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Support\KolomSatuSehat;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    /**
     * Kolom pemetaan KFA sudah ada di skmst_products?
     *
     * Kolomnya datang dari SQL manual (database/sql/2026_09_11_alter_skmst_products_add_satusehat.sql),
     * bukan migration — jadi ada jendela waktu ketika halaman ini sudah terpasang tapi
     * kolomnya belum dibuat. Tanpa penjaga ini SELECT/UPDATE-nya ORA-00904 dan seluruh
     * modal mati; dengan penjaga, bagian KFA sekadar disembunyikan sampai SQL-nya jalan.
     */
    public bool $kolomKfaAda = false;

    public array $form = [
        'product_id'    => '',
        'product_name'  => '',
        'product_type'  => 'OBT',  // OBT (obat), ALK (alkes), BHP, dll
        'product_rak'   => '',
        'cat_id'        => '',
        'uom_id'        => '',
        'supp_id'       => '',
        'cost_price'    => '0',
        'sales_price'   => '0',
        'margin_persen' => '0',
        'qty_box'       => '0',
        'limit_stock'   => '0',
        'active_status' => '1',
        // SATUSEHAT — kode & nama KFA (Kamus Farmasi & Alkes Kemenkes).
        'product_id_satusehat'   => '',
        'product_name_satusehat' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->kolomKfaAda = KolomSatuSehat::produkPunyaKfa();
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
    public function uoms()
    {
        return DB::table('skmst_uoms')
            ->select('uom_id', 'uom_desc')
            ->where('active_status', '1')
            ->orderBy('uom_desc')
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

    /** Auto-calc margin saat cost atau sales berubah */
    public function updatedFormCostPrice(): void  { $this->recalcMargin(); }
    public function updatedFormSalesPrice(): void { $this->recalcMargin(); }

    private function recalcMargin(): void
    {
        $cost  = (float) $this->form['cost_price'];
        $sales = (float) $this->form['sales_price'];
        if ($cost > 0 && $sales > 0) {
            $margin = (($sales - $cost) / $cost) * 100;
            $this->form['margin_persen'] = number_format($margin, 2, '.', '');
        }
    }

    #[On('master.product.openCreate')]
    public function openCreate(): void
    {
        $this->kolomKfaAda = KolomSatuSehat::produkPunyaKfa();
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-product-actions');
        $this->dispatch('focus-product-id');
    }

    #[On('master.product.openEdit')]
    public function openEdit(string $productId): void
    {
        $this->kolomKfaAda = KolomSatuSehat::produkPunyaKfa();

        $row = DB::table('skmst_products')->where('product_id', $productId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $productId;
        $this->form = [
            'product_id'    => (string) $row->product_id,
            'product_name'  => (string) ($row->product_name ?? ''),
            'product_type'  => (string) ($row->product_type ?? 'OBT'),
            'product_rak'   => (string) ($row->product_rak ?? ''),
            'cat_id'        => (string) ($row->cat_id ?? ''),
            'uom_id'        => (string) ($row->uom_id ?? ''),
            'supp_id'       => (string) ($row->supp_id ?? ''),
            'cost_price'    => (string) ($row->cost_price ?? '0'),
            'sales_price'   => (string) ($row->sales_price ?? '0'),
            'margin_persen' => (string) ($row->margin_persen ?? '0'),
            'qty_box'       => (string) ($row->qty_box ?? '0'),
            'limit_stock'   => (string) ($row->limit_stock ?? '0'),
            'active_status' => (string) ($row->active_status ?? '1'),
            'product_id_satusehat'   => $this->kolomKfaAda ? (string) ($row->product_id_satusehat ?? '') : '',
            'product_name_satusehat' => $this->kolomKfaAda ? (string) ($row->product_name_satusehat ?? '') : '',
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-product-actions');
        $this->dispatch('focus-product-name');
    }

    #[On('master.product.toggleActive')]
    public function toggleActive(string $productId): void
    {
        $cur = (string) DB::table('skmst_products')->where('product_id', $productId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('skmst_products')->where('product_id', $productId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status produk → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.product.saved');
    }

    #[On('master.product.requestDelete')]
    public function deleteProduct(string $productId): void
    {
        try {
            $isUsedSls = DB::table('sktxn_slsdtls')->where('product_id', $productId)->exists();
            $isUsedRcv = DB::table('sktxn_rcvdtls')->where('product_id', $productId)->exists();
            if ($isUsedSls || $isUsedRcv) {
                $this->dispatch('toast', type: 'error', message: 'Produk tidak bisa dihapus karena masih dipakai pada transaksi penjualan/pembelian.');
                return;
            }

            $deleted = DB::table('skmst_products')->where('product_id', $productId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data produk tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Produk berhasil dihapus.');
            $this->dispatch('master.product.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Produk tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.product_id'    => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:skmst_products,product_id'
                : 'required|string',
            'form.product_name'  => 'required|string|max:100',
            'form.product_type'  => 'required|string|max:3',
            'form.product_rak'   => 'nullable|string|max:100',
            'form.cat_id'        => 'required|string|exists:skmst_categories,cat_id',
            'form.uom_id'        => 'required|string|exists:skmst_uoms,uom_id',
            'form.supp_id'       => 'required|string|exists:skmst_suppliers,supp_id',
            'form.cost_price'    => 'required|numeric|min:0',
            'form.sales_price'   => 'required|numeric|min:0',
            'form.margin_persen' => 'nullable|numeric',
            'form.qty_box'       => 'nullable|numeric|min:0',
            'form.limit_stock'   => 'nullable|integer|min:0',
            'form.active_status' => 'required|in:0,1',
            'form.product_id_satusehat'   => 'nullable|string|max:50',
            'form.product_name_satusehat' => 'nullable|string|max:250',
        ];

        $messages = [
            'form.product_id.required' => 'ID Produk wajib diisi.',
            'form.product_id.unique'   => 'ID Produk sudah digunakan.',
            'form.product_name.required' => 'Nama Produk wajib diisi.',
            'form.cat_id.required'  => 'Kategori wajib dipilih.',
            'form.uom_id.required'  => 'Satuan wajib dipilih.',
            'form.supp_id.required' => 'Supplier wajib dipilih.',
            'form.cost_price.required'  => 'HPP (cost price) wajib diisi.',
            'form.sales_price.required' => 'Harga jual wajib diisi.',
        ];

        $attributes = [
            'form.product_id'    => 'ID Produk',
            'form.product_name'  => 'Nama Produk',
            'form.product_type'  => 'Tipe Produk',
            'form.product_rak'   => 'Rak',
            'form.cat_id'        => 'Kategori',
            'form.uom_id'        => 'Satuan',
            'form.supp_id'       => 'Supplier',
            'form.cost_price'    => 'HPP',
            'form.sales_price'   => 'Harga Jual',
            'form.margin_persen' => 'Margin %',
            'form.qty_box'       => 'Qty per Box',
            'form.limit_stock'   => 'Limit Stok',
            'form.active_status' => 'Status',
            'form.product_id_satusehat'   => 'Kode KFA',
            'form.product_name_satusehat' => 'Nama KFA',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'product_name'  => mb_strtoupper($this->form['product_name']),
            'product_type'  => mb_strtoupper($this->form['product_type']),
            'product_rak'   => $this->form['product_rak'] ?: null,
            'cat_id'        => $this->form['cat_id'],
            'uom_id'        => $this->form['uom_id'],
            'supp_id'       => $this->form['supp_id'],
            'cost_price'    => (float) $this->form['cost_price'],
            'sales_price'   => (float) $this->form['sales_price'],
            'margin_persen' => (float) $this->form['margin_persen'],
            'qty_box'       => (float) ($this->form['qty_box'] ?: 0),
            'limit_stock'   => (int)   ($this->form['limit_stock'] ?: 0),
            'active_status' => $this->form['active_status'],
        ];

        // Kolom KFA hanya ikut ditulis kalau memang sudah ada di tabel — menyertakannya
        // sebelum SQL dijalankan berarti ORA-00904 dan produk gagal disimpan sama sekali.
        if ($this->kolomKfaAda) {
            $payload[KolomSatuSehat::PRODUK_KFA_KODE] = trim($this->form['product_id_satusehat']) ?: null;
            $payload[KolomSatuSehat::PRODUK_KFA_NAMA] = trim($this->form['product_name_satusehat']) ?: null;
        }

        if ($this->formMode === 'create') {
            DB::table('skmst_products')->insert([
                'product_id' => mb_strtoupper($this->form['product_id']),
                ...$payload,
            ]);
        } else {
            DB::table('skmst_products')->where('product_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data produk berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.product.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-product-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'product_id' => '', 'product_name' => '', 'product_type' => 'OBT', 'product_rak' => '',
            'cat_id' => '', 'uom_id' => '', 'supp_id' => '',
            'cost_price' => '0', 'sales_price' => '0', 'margin_persen' => '0',
            'qty_box' => '0', 'limit_stock' => '0',
            'active_status' => '1',
            'product_id_satusehat' => '', 'product_name_satusehat' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-product-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-product-actions"
            event="master.product.saved"
            label="Produk"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                     style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Produk' : 'Tambah Produk' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Produk apotek/toko — referensi inventory.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 space-y-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-product-id.window="$nextTick(() => setTimeout(() => $refs.inputProductId?.focus(), 150))"
                 x-on:focus-product-name.window="$nextTick(() => setTimeout(() => $refs.inputProductName?.focus(), 150))">

                @include('pages::master.master-apotek.master-product.master-product-form')
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Margin auto-update saat HPP atau Harga Jual berubah.
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
