<?php
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Cari Jasa Karyawan';
    public string $placeholder = 'Ketik kode/nama jasa karyawan...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public ?array $selected = null;

    #[Reactive]
    public ?string $initialActeId = null;

    public bool $disabled = false;

    public function mount(): void
    {
        if (!$this->initialActeId) {
            return;
        }
        $this->loadSelected($this->initialActeId);
    }

    public function updatedInitialActeId($value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }
        $this->loadSelected($value);
    }

    protected function loadSelected(string $acteId): void
    {
        $row = DB::table('skmst_actemps')->select('acte_id', 'acte_desc', 'acte_price')->where('acte_id', $acteId)->first();

        if ($row) {
            $this->selected = [
                'acte_id' => (string) $row->acte_id,
                'acte_desc' => (string) ($row->acte_desc ?? ''),
                'acte_price' => (int) ($row->acte_price ?? 0),
            ];
        }
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ── Exact match by acte_id ──
        if (ctype_alnum($keyword)) {
            $exact = DB::table('skmst_actemps')->select('acte_id', 'acte_desc', 'acte_price')->where('acte_id', $keyword)->first();

            if ($exact) {
                $this->dispatchSelected([
                    'acte_id' => (string) $exact->acte_id,
                    'acte_desc' => (string) ($exact->acte_desc ?? ''),
                    'acte_price' => (int) ($exact->acte_price ?? 0),
                ]);
                return;
            }
        }

        // ── Partial search ──
        $upper = mb_strtoupper($keyword);

        $rows = DB::table('skmst_actemps')
            ->select('acte_id', 'acte_desc', 'acte_price')
            ->where(function ($q) use ($keyword, $upper) {
                $q->where(DB::raw('upper(acte_desc)'), 'like', "%{$upper}%")->orWhere(DB::raw('upper(acte_id)'), 'like', "%{$upper}%");
            })
            ->orderBy('acte_desc')
            ->orderBy('acte_id')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(
                fn($row) => [
                    'acte_id' => (string) $row->acte_id,
                    'acte_desc' => (string) ($row->acte_desc ?? ''),
                    'acte_price' => (int) ($row->acte_price ?? 0),
                    'label' => $row->acte_desc ?: '-',
                    'hint' => 'Kode: ' . $row->acte_id . ' • Rp ' . number_format($row->acte_price ?? 0),
                ],
            )
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.selected', target: $this->target, payload: null);
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

        $this->dispatchSelected([
            'acte_id' => $this->options[$index]['acte_id'] ?? '',
            'acte_desc' => $this->options[$index]['acte_desc'] ?? '',
            'acte_price' => $this->options[$index]['acte_price'] ?? 0,
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

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
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['acte_id'] . ' — ' . $selected['acte_desc']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- Dropdown list --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-jk-{{ $option['acte_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] }}
                                </div>
                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif
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
