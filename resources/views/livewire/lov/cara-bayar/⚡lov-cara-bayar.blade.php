<?php

/**
 * LOV Cara Bayar — sumber tabel: skacc_carabayars where active_status='1'.
 *
 * Payload dispatch ke parent:
 *   ['cb_id' => '...', 'cb_desc' => '...', 'acc_id' => '...', 'acc_name' => '...']
 *
 * Pakai dari parent:
 *   <livewire:lov.cara-bayar.lov-cara-bayar
 *       target="cara-bayar-kasir-rj"
 *       :initialCbId="$cbId" />
 *
 *   #[On('lov.selected.cara-bayar-kasir-rj')]
 *   public function onCaraBayarSelected(string $target, ?array $payload): void { ... }
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';

    public string $label = 'Cara Bayar';
    public string $placeholder = 'Ketik kode/nama cara bayar...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialCbId = null;

    public bool $disabled = false;

    /* ── Lifecycle ── */

    public function mount(): void
    {
        if ($this->initialCbId) {
            $this->loadSelected($this->initialCbId);
        }
    }

    public function updatedInitialCbId(?string $value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (!empty($value)) {
            $this->loadSelected($value);
        }
    }

    /* ── Load mode edit (tdk filter active supaya record lama yg ke-nonaktif tetap ke-load) ── */

    protected function loadSelected(string $cbId): void
    {
        // skacc_accountses pakai acc_desc (bukan acc_name) untuk display nama akun.
        $row = DB::table('skacc_carabayars as cb')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 'cb.acc_id')
            ->select('cb.cb_id', 'cb.cb_desc', 'cb.active_status', 'cb.acc_id', 'a.acc_desc as acc_name')
            ->where('cb.cb_id', $cbId)
            ->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    /* ── Pencarian real-time ── */

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 1) {
            $this->closeAndResetList();
            return;
        }

        $upperKeyword = mb_strtoupper($keyword);

        // Exact match by cb_id → langsung pilih
        $exactRow = $this->baseQuery()->where('cb.cb_id', $upperKeyword)->first();
        if ($exactRow) {
            $this->dispatchSelected($this->buildPayload($exactRow));
            return;
        }

        // Partial match
        $rows = $this->baseQuery()
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw('UPPER(cb.cb_id) LIKE ?', ["%{$upperKeyword}%"])
                  ->orWhereRaw('UPPER(cb.cb_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('cb.cb_desc')
            ->limit(50)
            ->get();

        $this->options = $rows->map(fn($row) => array_merge(
            $this->buildPayload($row),
            ['label' => (string) ($row->cb_desc ?: $row->cb_id)]
        ))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ── Query dasar — hanya cara bayar yg aktif ── */

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('skacc_carabayars as cb')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 'cb.acc_id')
            ->select('cb.cb_id', 'cb.cb_desc', 'cb.active_status', 'cb.acc_id', 'a.acc_desc as acc_name')
            ->where('cb.active_status', '1');
    }

    /* ── Build payload ── */

    protected function buildPayload(object $row): array
    {
        return [
            'cb_id'    => (string) $row->cb_id,
            'cb_desc'  => (string) ($row->cb_desc ?? ''),
            'acc_id'   => (string) ($row->acc_id ?? ''),
            'acc_name' => (string) ($row->acc_name ?? ''),
        ];
    }

    /* ── Navigasi ── */

    public function clearSelected(): void
    {
        if ($this->disabled) return;
        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
    }

    public function close(): void { $this->isOpen = false; }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || !count($this->options)) return;
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || !count($this->options)) return;
        if (--$this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;
        $this->dispatchSelected($this->options[$index]);
    }

    public function chooseHighlighted(): void { $this->choose($this->selectedIndex); }

    /* ── Helpers ── */

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

        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
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
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                    wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full"
                        :value="$selected['cb_id'] . ' — ' . $selected['cb_desc']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && !$disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-cb-{{ $option['cb_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] }}
                                </span>
                                <div class="text-xs font-mono text-gray-400 dark:text-gray-500">
                                    {{ $option['cb_id'] }}
                                    @if (!empty($option['acc_id']))
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        akun: {{ $option['acc_id'] }}
                                        @if (!empty($option['acc_name']))
                                            ({{ $option['acc_name'] }})
                                        @endif
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>
                @if (mb_strlen(trim($search)) >= 1 && !count($options))
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Cara bayar tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
