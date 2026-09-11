<?php
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Cari Lain-lain';
    public string $placeholder = 'Ketik kode/nama lain-lain...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public ?array $selected = null;

    #[Reactive]
    public ?string $initialOtherId = null;

    public bool $disabled = false;

    public function mount(): void
    {
        if (!$this->initialOtherId) {
            return;
        }
        $this->loadSelected($this->initialOtherId);
    }

    public function updatedInitialOtherId($value): void
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

    protected function loadSelected(string $otherId): void
    {
        $row = DB::table('skmst_others')->select('other_id', 'other_desc', 'other_price')->where('other_id', $otherId)->where('active_status', '1')->first();

        if ($row) {
            $this->selected = [
                'other_id' => (string) $row->other_id,
                'other_desc' => (string) ($row->other_desc ?? ''),
                'other_price' => (int) ($row->other_price ?? 0),
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

        // ── Exact match by other_id ──
        if (ctype_alnum($keyword)) {
            $exact = DB::table('skmst_others')->select('other_id', 'other_desc', 'other_price')->where('other_id', $keyword)->where('active_status', '1')->first();

            if ($exact) {
                $this->dispatchSelected([
                    'other_id' => (string) $exact->other_id,
                    'other_desc' => (string) ($exact->other_desc ?? ''),
                    'other_price' => (int) ($exact->other_price ?? 0),
                ]);
                return;
            }
        }

        // ── Partial search ──
        $upper = mb_strtoupper($keyword);

        $rows = DB::table('skmst_others')
            ->select('other_id', 'other_desc', 'other_price')
            ->where('active_status', '1')
            ->where(function ($q) use ($upper) {
                $q->where(DB::raw('upper(other_desc)'), 'like', "%{$upper}%")->orWhere(DB::raw('upper(other_id)'), 'like', "%{$upper}%");
            })
            ->orderBy('other_desc')
            ->orderBy('other_id')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(
                fn($row) => [
                    'other_id' => (string) $row->other_id,
                    'other_desc' => (string) ($row->other_desc ?? ''),
                    'other_price' => (int) ($row->other_price ?? 0),
                    'label' => $row->other_desc ?: '-',
                    'hint' => 'Kode: ' . $row->other_id . ' • Rp ' . number_format($row->other_price ?? 0),
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
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
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
            'other_id' => $this->options[$index]['other_id'] ?? '',
            'other_desc' => $this->options[$index]['other_desc'] ?? '',
            'other_price' => $this->options[$index]['other_price'] ?? 0,
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
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['other_id'] . ' — ' . $selected['other_desc']" disabled />
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
                        <li wire:key="lov-lainlain-{{ $option['other_id'] }}-{{ $index }}"
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
