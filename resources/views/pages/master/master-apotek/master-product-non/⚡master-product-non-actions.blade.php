<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public int    $qtyBox     = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'product_name'  => '',
        'uom_id'        => '',
        'cost_price'    => 0,
        'limit_stock'   => 0,
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    public function uomOptions(): array
    {
        return DB::table('tkmst_uoms')
            ->select('uom_id', 'uom_desc')
            ->where('active_status', '1')
            ->orderBy('uom_desc')
            ->get()
            ->all();
    }

    #[On('master.product-non.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->qtyBox     = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-product-non-actions');
        $this->dispatch('focus-product-name');
    }

    #[On('master.product-non.openEdit')]
    public function openEdit(string $productId): void
    {
        $row = DB::table('tkmst_productnons')->where('product_id', $productId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $productId;
        $this->qtyBox     = (int) ($row->qty_box ?? 0);
        $this->form = [
            'product_name'  => (string) ($row->product_name ?? ''),
            'uom_id'        => (string) ($row->uom_id ?? ''),
            'cost_price'    => (int) ($row->cost_price ?? 0),
            'limit_stock'   => (int) ($row->limit_stock ?? 0),
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-product-non-actions');
        $this->dispatch('focus-product-name');
    }

    #[On('master.product-non.toggleActive')]
    public function toggleActive(string $productId): void
    {
        $cur = (string) DB::table('tkmst_productnons')->where('product_id', $productId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('tkmst_productnons')->where('product_id', $productId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status barang → ' . ($next === '1' ? 'AKTIF' : 'NONAKTIF'));
        $this->dispatch('master.product-non.saved');
    }

    #[On('master.product-non.requestDelete')]
    public function deleteProductNon(string $productId): void
    {
        try {
            $isUsed = DB::table('tktxn_rcvdtlnons')->where('product_id', $productId)->exists();
            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Barang tidak bisa dihapus karena sudah dipakai pada penerimaan.');
                return;
            }

            $deleted = DB::table('tkmst_productnons')->where('product_id', $productId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data barang tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Barang berhasil dihapus.');
            $this->dispatch('master.product-non.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Barang tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.product_name'  => 'required|string|max:150',
            'form.uom_id'        => 'nullable|string|max:20',
            'form.cost_price'    => 'required|integer|min:0',
            'form.limit_stock'   => 'nullable|integer|min:0',
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.product_name.required' => 'Nama barang wajib diisi.',
            'form.product_name.max'      => 'Nama barang maksimal 150 karakter.',
            'form.cost_price.required'   => 'Harga beli wajib diisi.',
            'form.cost_price.integer'    => 'Harga beli harus berupa angka.',
            'form.cost_price.min'        => 'Harga beli tidak boleh negatif.',
            'form.limit_stock.integer'   => 'Batas stok harus berupa angka.',
            'form.limit_stock.min'       => 'Batas stok tidak boleh negatif.',
        ];

        $attributes = [
            'form.product_name'  => 'Nama Barang',
            'form.uom_id'        => 'Satuan',
            'form.cost_price'    => 'Harga Beli',
            'form.limit_stock'   => 'Batas Stok',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'product_name'  => mb_strtoupper($this->form['product_name']),
            'uom_id'        => $this->form['uom_id'] ?: null,
            'cost_price'    => (int) $this->form['cost_price'],
            'limit_stock'   => (int) ($this->form['limit_stock'] ?: 0),
            'active_status' => $this->form['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkmst_productnons')->insert([
                'product_id' => DB::raw('productnon_seq.nextval'),
                'qty_box'    => 0,
                ...$payload,
            ]);
        } else {
            DB::table('tkmst_productnons')->where('product_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data barang berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.product-non.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-product-non-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'product_name' => '', 'uom_id' => '', 'cost_price' => 0,
            'limit_stock' => 0, 'active_status' => '1',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-product-non-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-product-non-actions"
            event="master.product-non.saved"
            label="Barang Non-Medis"
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
                                    {{ $formMode === 'edit' ? 'Ubah Barang Non-Medis' : 'Tambah Barang Non-Medis' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Barang non-medis (ATK/Rumah Tangga) — stok tunggal di qty_box.
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

            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-product-name.window="$nextTick(() => setTimeout(() => $refs.inputProductName?.focus(), 150))">

                <x-border-form title="Data Barang">
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Nama Barang" />
                            <x-text-input wire:model.live="form.product_name" x-ref="inputProductName"
                                maxlength="150"
                                :error="$errors->has('form.product_name')"
                                class="w-full mt-1 uppercase" />
                            <x-input-error :messages="$errors->get('form.product_name')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Satuan" />
                                <x-select-input wire:model.live="form.uom_id"
                                    :error="$errors->has('form.uom_id')"
                                    class="w-full mt-1">
                                    <option value="">— Pilih Satuan —</option>
                                    @foreach ($this->uomOptions() as $u)
                                        <option value="{{ $u->uom_id }}">{{ $u->uom_desc }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.uom_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Harga Beli" />
                                <x-text-input wire:model.live="form.cost_price"
                                    type="number" min="0"
                                    :error="$errors->has('form.cost_price')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.cost_price')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Batas Stok (limit)" />
                                <x-text-input wire:model.live="form.limit_stock"
                                    type="number" min="0"
                                    :error="$errors->has('form.limit_stock')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.limit_stock')" class="mt-1" />
                            </div>
                        </div>

                        @if ($formMode === 'edit')
                            <div class="p-3 text-sm border border-gray-200 rounded-lg bg-gray-100/70 dark:bg-gray-800/50 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Stok berjalan (qty_box):</span>
                                <span class="ml-1 font-semibold text-gray-900 dark:text-gray-100 tabular-nums">
                                    {{ number_format($qtyBox, 0, ',', '.') }}
                                </span>
                                <span class="ml-2 text-xs text-gray-400">— hanya bisa diubah lewat modul Penerimaan.</span>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Status" />
                                <x-select-input wire:model.live="form.active_status"
                                    :error="$errors->has('form.active_status')"
                                    class="w-full mt-1">
                                    <option value="1">AKTIF</option>
                                    <option value="0">NONAKTIF</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pastikan data sudah benar sebelum menyimpan.
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
