<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Supplier';
    public string $placeholder = 'Ketik nama/kode supplier...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim supp_id yang sudah tersimpan.
     */
    public ?string $initialSuppId = null;

    /**
     * Mode readonly: jika true, tombol "Ubah" akan hilang saat selected.
     */
    public bool $readonly = false;

    /**
     * Legacy prop (sirus) — diabaikan di siklik karena tabel skmst_suppliers
     * tidak punya kolom medis/nonmedis. Dipertahankan supaya pemanggil lama
     * tidak rusak. Klinik pratama tidak membedakan jenis supplier.
     */
    public string $jenisSupplier = 'all';

    public function mount(): void
    {
        if (!$this->initialSuppId) {
            return;
        }

        $row = DB::table('skmst_suppliers')
            ->select(['supp_id', 'supp_name', 'supp_phone1', 'supp_address'])
            ->where('supp_id', $this->initialSuppId)
            ->where('active_status', '1')
            ->first();

        if ($row) {
            $this->selected = [
                'supp_id'   => (string) $row->supp_id,
                'supp_name' => (string) ($row->supp_name ?? ''),
                'phone'     => (string) ($row->supp_phone1 ?? ''),
                'address'   => (string) ($row->supp_address ?? ''),
            ];
        }
    }

    public function updatedSearch(): void
    {
        // kalau sudah selected, jangan cari lagi
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // minimal 2 char
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by supp_id =====
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('skmst_suppliers')
                ->select(['supp_id', 'supp_name', 'supp_phone1', 'supp_address'])
                ->where('active_status', '1')
                ->where('supp_id', $keyword)
                ->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'supp_id'   => (string) $exactRow->supp_id,
                    'supp_name' => (string) ($exactRow->supp_name ?? ''),
                    'phone'     => (string) ($exactRow->supp_phone1 ?? ''),
                    'address'   => (string) ($exactRow->supp_address ?? ''),
                ]);
                return;
            }
        }

        // ===== 2) search by supp_name partial =====
        $upperKeyword = mb_strtoupper($keyword);

        $rows = DB::table('skmst_suppliers')
            ->select(['supp_id', 'supp_name', 'supp_phone1', 'supp_address'])
            ->where('active_status', '1')
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw("UPPER(supp_id) LIKE '%' || ? || '%'", [$upperKeyword])
                    ->orWhereRaw("UPPER(supp_name) LIKE '%' || ? || '%'", [$upperKeyword])
                    ->orWhereRaw("UPPER(supp_phone1) LIKE '%' || ? || '%'", [$upperKeyword]);
            })
            ->orderBy('supp_name')
            ->limit(50)
            ->get();

        $this->options = array_map(function ($row) {
            $suppId   = (string) $row->supp_id;
            $suppName = (string) ($row->supp_name ?? '');
            $phone    = (string) ($row->supp_phone1 ?? '');
            $address  = (string) ($row->supp_address ?? '');

            $shortAddress = $address ? (strlen($address) > 60 ? substr($address, 0, 60) . '...' : $address) : '';

            return [
                'supp_id'       => $suppId,
                'supp_name'     => $suppName,
                'phone'         => $phone,
                'address'       => $address,
                'label'         => $suppName ?: '-',
                'hint'          => "Kode: {$suppId} • Telp: {$phone}",
                'address_short' => $shortAddress,
            ];
        }, $rows->toArray());

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        // Jika readonly, tidak bisa clear selected
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
            'supp_id'   => $this->options[$index]['supp_id'] ?? '',
            'supp_name' => $this->options[$index]['supp_name'] ?? '',
            'phone'     => $this->options[$index]['phone'] ?? '',
            'address'   => $this->options[$index]['address'] ?? '',
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
        // set selected -> UI berubah jadi nama + tombol ubah
        $this->selected = $payload;

        // bersihkan mode search
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        // emit ke parent
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
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                    wire:model.live.debounce.250ms="search" wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                    wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['supp_name'] ?? ''" disabled />
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $selected['phone'] ?? '' }}
                    </div>
                </div>

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
                        <li wire:key="lov-supplier-{{ $option['supp_id'] ?? $index }}-{{ $index }}"
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

                                    @if (!empty($option['address_short']))
                                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            <span class="font-medium">Alamat:</span> {{ $option['address_short'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Supplier tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
