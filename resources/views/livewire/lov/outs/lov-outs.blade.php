<?php

/**
 * resources/views/livewire/lov/outs/lov-outs.blade.php
 *
 * LOV Keterangan Keluar (skmst_outs).
 *
 * Payload dispatch ke parent:
 *   [
 *     'out_no'   => '...',
 *     'out_desc' => '...',
 *   ]
 *
 * Penggunaan:
 *   <livewire:lov.outs.lov-outs target="outs-kasir-ri" label="Keterangan Keluar"
 *       :initialOutNo="$outNo" wire:key="lov-outs-{{ $riHdrNo }}" />
 *
 * Di parent:
 *   #[On('lov.selected.outs-kasir-ri')]
 *   public function onOutsSelected(string $target, ?array $payload): void
 *   {
 *       $this->outNo   = $payload['out_no']   ?? null;
 *       $this->outDesc = $payload['out_desc'] ?? null;
 *   }
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target      = 'default';
    public string $label       = 'Keterangan Keluar';
    public string $placeholder = 'Ketik kode/keterangan keluar...';

    public string $search        = '';
    public array  $options       = [];
    public bool   $isOpen        = false;
    public int    $selectedIndex = 0;
    public ?array $selected      = null;
    public bool   $disabled      = false;

    #[Reactive]
    public ?string $initialOutNo = null;

    /* ── Lifecycle ── */

    public function mount(): void
    {
        if ($this->initialOutNo !== null && $this->initialOutNo !== '') {
            $this->loadSelected($this->initialOutNo);
        }
    }

    public function updatedInitialOutNo(?string $value): void
    {
        $this->selected = null;
        $this->search   = '';
        $this->options  = [];
        $this->isOpen   = false;

        if (!empty($value)) {
            $this->loadSelected($value);
        }
    }

    /* ── Load mode edit ── */

    protected function loadSelected(string $outNo): void
    {
        $row = DB::table('skmst_outs')
            ->where('out_no', $outNo)
            ->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    /* ── Pencarian real-time ── */

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

        $upperKeyword = mb_strtoupper($keyword);

        // Exact match → langsung pilih
        $exactRow = DB::table('skmst_outs')
            ->whereRaw("UPPER(out_no) = ?", [mb_strtoupper($keyword)])
            ->first();

        if ($exactRow) {
            $this->dispatchSelected($this->buildPayload($exactRow));
            return;
        }

        // Partial match
        $rows = DB::table('skmst_outs')
            ->where(fn($q) => $q
                ->whereRaw('UPPER(out_no) LIKE ?', ["%{$upperKeyword}%"])
                ->orWhereRaw('UPPER(out_desc) LIKE ?', ["%{$upperKeyword}%"])
            )
            ->orderBy('out_no')
            ->limit(50)
            ->get();

        $this->options = $rows->map(fn($row) => [
            ...$this->buildPayload($row),
            'label' => (string) ($row->out_desc ?: $row->out_no),
        ])->toArray();

        $this->isOpen        = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ── Build payload ── */

    protected function buildPayload(object $row): array
    {
        return [
            'out_no'   => (string) $row->out_no,
            'out_desc' => (string) ($row->out_desc ?? ''),
        ];
    }

    /* ── Navigasi ── */

    public function clearSelected(): void
    {
        if ($this->disabled) {
            return;
        }
        $this->selected = null;
        $this->resetLov();
        $this->dispatch("lov.selected.{$this->target}", target: $this->target, payload: null);
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

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* ── Helpers ── */

    protected function closeAndResetList(): void
    {
        $this->options       = [];
        $this->isOpen        = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected      = $payload;
        $this->search        = '';
        $this->options       = [];
        $this->isOpen        = false;
        $this->selectedIndex = 0;

        $this->dispatch("lov.selected.{$this->target}", target: $this->target, payload: $payload);
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
                    wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious"
                    wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full"
                        :value="$selected['out_no'] . ' — ' . $selected['out_desc']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- Dropdown --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-64 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-outs-{{ $option['out_no'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] }}
                                    </span>
                                    <span class="font-mono text-xs text-gray-400 dark:text-gray-500 shrink-0">
                                        {{ $option['out_no'] }}
                                    </span>
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
