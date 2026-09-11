<?php

/**
 * LOV Kasir — sumber: SKMST_KASIRS (siklik klinik pratama).
 *
 * Sebelumnya pakai SKMST_EMPLOYERS (sirus only) — tabel itu nggak ada
 * di siklik. Klinik pratama cuma punya master kasir simple
 * (kasir_id, kasir_name, active_status).
 *
 * Payload dispatch ke parent (compat alias emp_id/emp_name supaya
 * kode lama yg pakai LOV ini nggak perlu di-rename):
 *   [
 *     'emp_id'   => kasir_id,    // alias (compat)
 *     'emp_name' => kasir_name,  // alias (compat)
 *     'kasir_id'   => '...',
 *     'kasir_name' => '...',
 *   ]
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Kasir';
    public string $placeholder = 'Ketik kode/nama kasir...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialEmpId = null;

    public bool $disabled = false;

    /* ── Lifecycle ── */

    public function mount(): void
    {
        if ($this->initialEmpId) {
            $this->loadSelected($this->initialEmpId);
        }
    }

    public function updatedInitialEmpId(?string $value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (!empty($value)) {
            $this->loadSelected($value);
        }
    }

    /* ── Load mode edit ── */

    protected function loadSelected(string $empId): void
    {
        // Load tanpa filter active supaya record lama tetap nampil saat edit.
        $row = DB::table('skmst_kasirs')
            ->select('kasir_id', 'kasir_name', 'active_status')
            ->where('kasir_id', $empId)
            ->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    /* ── Query dasar — SKMST_KASIRS, hanya yang aktif ── */

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('skmst_kasirs')
            ->select('kasir_id', 'kasir_name', 'active_status')
            ->where('active_status', '1');
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

        // Exact match by kasir_id → langsung pilih
        $exactRow = $this->baseQuery()->where('kasir_id', $keyword)->first();

        if ($exactRow) {
            $this->dispatchSelected($this->buildPayload($exactRow));
            return;
        }

        // Partial match
        $rows = $this->baseQuery()
            ->where(
                fn($q) => $q
                    ->where('kasir_id', 'like', "%{$keyword}%")
                    ->orWhereRaw('UPPER(kasir_name) LIKE ?', ["%{$upperKeyword}%"]),
            )
            ->orderBy('kasir_name')
            ->limit(50)
            ->get();

        $this->options = $rows->map(fn($row) => array_merge($this->buildPayload($row), [
            'label' => (string) ($row->kasir_name ?: $row->kasir_id),
        ]))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ── Build payload ── */

    protected function buildPayload(object $row): array
    {
        return [
            // Compat alias supaya parent yg masih pakai emp_id/emp_name jalan
            'emp_id'     => (string) ($row->kasir_id ?? ''),
            'emp_name'   => (string) ($row->kasir_name ?? ''),
            // Native key SKMST_KASIRS
            'kasir_id'   => (string) ($row->kasir_id ?? ''),
            'kasir_name' => (string) ($row->kasir_name ?? ''),
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
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        if (--$this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }
        $this->dispatchSelected($this->options[$index]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

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
                    <x-text-input type="text" class="block w-full font-mono" :value="$selected['emp_id'] . ' — ' . $selected['emp_name']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>

            {{-- Schema klinik simpel — tdk ada emp_index/emp_grade/bu_id/phone.
                 Display id + nama saja sudah cukup di mode selected. --}}
        @endif

        {{-- Dropdown list --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-kasir-{{ $option['kasir_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['kasir_name'] ?: $option['kasir_id'] }}
                                </div>
                                <div class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                    {{ $option['kasir_id'] }}
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Kasir tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
