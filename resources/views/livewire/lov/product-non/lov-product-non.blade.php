<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Barang';
    public string $placeholder = 'Ketik nama/kode barang non-medis...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim product_id yang sudah tersimpan.
     */
    public ?string $initialProductId = null;

    /**
     * Mode readonly: jika true, tombol "Ubah" akan hilang saat selected.
     */
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialProductId) {
            return;
        }

        $row = DB::table('tkmst_productnons')
            ->select(['product_id', 'product_name', 'cost_price', 'qty_box'])
            ->where('product_id', $this->initialProductId)
            ->where('active_status', '1')
            ->first();

        if ($row) {
            $this->selected = [
                'product_id' => (string) $row->product_id,
                'product_name' => (string) ($row->product_name ?? ''),
                'cost_price' => (int) ($row->cost_price ?? 0),
                'qty_box' => (int) ($row->qty_box ?? 0),
            ];
        }
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 1) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by product_id =====
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('tkmst_productnons')
                ->select(['product_id', 'product_name', 'cost_price', 'qty_box'])
                ->where('active_status', '1')
                ->where('product_id', $keyword)
                ->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'product_id' => (string) $exactRow->product_id,
                    'product_name' => (string) ($exactRow->product_name ?? ''),
                    'cost_price' => (int) ($exactRow->cost_price ?? 0),
                    'qty_box' => (int) ($exactRow->qty_box ?? 0),
                ]);
                return;
            }
        }

        // ===== 2) search by name =====
        $rows = DB::table('tkmst_productnons')
            ->select(['product_id', 'product_name', 'cost_price', 'qty_box'])
            ->where('active_status', '1')
            ->whereRaw('UPPER(product_name) LIKE ?', ['%' . strtoupper($keyword) . '%'])
            ->orderBy('product_name')
            ->limit(50)
            ->get();

        $this->options = $rows->map(function ($row) {
            $productId = (string) $row->product_id;
            $productName = (string) ($row->product_name ?? '');
            $costPrice = (int) ($row->cost_price ?? 0);
            $qtyBox = (int) ($row->qty_box ?? 0);

            $formattedPrice = number_format($costPrice, 0, ',', '.');

            return [
                // payload
                'product_id' => $productId,
                'product_name' => $productName,
                'cost_price' => $costPrice,
                'qty_box' => $qtyBox,

                // UI
                'label' => $productName ?: '-',
                'hint' => "ID: {$productId} • Harga Beli: Rp {$formattedPrice} • Stok: {$qtyBox}",
            ];
        })->all();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->selected = null;
        $this->resetLov();
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }

        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $payload = [
            'product_id' => $this->options[$index]['product_id'] ?? '',
            'product_name' => $this->options[$index]['product_name'] ?? '',
            'cost_price' => (int) ($this->options[$index]['cost_price'] ?? 0),
            'qty_box' => (int) ($this->options[$index]['qty_box'] ?? 0),
        ];

        $this->dispatchSelected($payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected = $payload;

        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        $eventName = 'lov.selected.' . $this->target;
        $this->dispatch($eventName, target: $this->target, payload: $payload);
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$readonly)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <x-text-input type="text" class="flex-1 block w-full" :value="$selected['product_name'] ?? ''" disabled />

                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak readonly --}}
        @if ($isOpen && $selected === null && !$readonly)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-product-non-{{ $option['product_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] ?? '-' }}
                                    </div>

                                    @if (!empty($option['hint']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $option['hint'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
