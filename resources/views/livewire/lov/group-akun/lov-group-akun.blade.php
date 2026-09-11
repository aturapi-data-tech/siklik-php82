<?php

/**
 * LOV Group Akun — sumber: skacc_gr_accountses (5 group fixed: AKTIVA/HUTANG/EKUITAS/PENDAPATAN/BEBAN).
 * Catatan: kolom gra_status di tabel ini = 'N' (Neraca) / 'L' (Laba-Rugi), bukan flag aktif.
 *
 * Payload: ['gra_id', 'gra_desc', 'dk_status', 'gra_status']
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Group Akun';
    public string $placeholder = 'Ketik kode/nama group...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialGraId = null;

    public bool $disabled = false;

    public function mount(): void
    {
        if ($this->initialGraId) $this->loadSelected($this->initialGraId);
    }

    public function updatedInitialGraId(?string $value): void
    {
        $this->selected = null;
        $this->reset(['search', 'options', 'isOpen']);
        if (!empty($value)) $this->loadSelected($value);
    }

    protected function loadSelected(string $graId): void
    {
        $row = DB::table('skacc_gr_accountses')
            ->select('gra_id', 'gra_desc', 'gra_status', 'dk_status')
            ->where('gra_id', $graId)->first();
        if ($row) $this->selected = $this->buildPayload($row);
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;
        $kw = trim($this->search);
        if (mb_strlen($kw) < 1) { $this->closeAndResetList(); return; }

        $up = mb_strtoupper($kw);

        $exact = $this->baseQuery()->where('gra_id', $up)->first();
        if ($exact) { $this->dispatchSelected($this->buildPayload($exact)); return; }

        $rows = $this->baseQuery()
            ->where(function ($q) use ($up) {
                $q->whereRaw('UPPER(gra_id) LIKE ?', ["%{$up}%"])
                  ->orWhereRaw('UPPER(gra_desc) LIKE ?', ["%{$up}%"]);
            })
            ->orderBy('gra_id')->limit(50)->get();

        $this->options = $rows->map(fn($r) => array_merge($this->buildPayload($r), [
            'label' => (string) ($r->gra_desc ?: $r->gra_id),
        ]))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
        if ($this->isOpen) $this->emitScroll();
    }

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('skacc_gr_accountses')
            ->select('gra_id', 'gra_desc', 'gra_status', 'dk_status');
    }

    protected function buildPayload(object $r): array
    {
        return [
            'gra_id'     => (string) $r->gra_id,
            'gra_desc'   => (string) ($r->gra_desc ?? ''),
            'dk_status'  => (string) ($r->dk_status ?? ''),
            'gra_status' => (string) ($r->gra_status ?? ''),
        ];
    }

    public function clearSelected(): void
    {
        if ($this->disabled) return;
        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
    }

    public function close(): void { $this->isOpen = false; }
    public function resetLov(): void { $this->reset(['search', 'options', 'isOpen', 'selectedIndex']); }

    public function selectNext(): void
    {
        if (!$this->isOpen || !count($this->options)) return;
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || !count($this->options)) return;
        if (--$this->selectedIndex < 0) $this->selectedIndex = count($this->options) - 1;
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;
        $this->dispatchSelected($this->options[$index]);
    }

    public function chooseHighlighted(): void { $this->choose($this->selectedIndex); }

    protected function closeAndResetList(): void { $this->options = []; $this->isOpen = false; $this->selectedIndex = 0; }
    protected function dispatchSelected(array $p): void
    {
        $this->selected = $p;
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $p);
    }
    protected function emitScroll(): void { $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex); }
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
                <x-text-input type="text" class="block w-full"
                    :value="$selected['gra_id'] . ' — ' . $selected['gra_desc']" disabled />
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
            @if (!empty($selected['dk_status']))
                <div class="mt-1 text-[10px] text-gray-400">
                    {{ $selected['dk_status'] === 'D' ? 'Debit' : 'Kredit' }}
                </div>
            @endif
        @endif

        @if ($isOpen && $selected === null && !$disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $i => $o)
                        <li wire:key="lov-gra-{{ $o['gra_id'] }}-{{ $i }}" x-ref="lovItem{{ $i }}">
                            <x-lov.item wire:click="choose({{ $i }})" :active="$i === $selectedIndex">
                                <div class="flex justify-between gap-2">
                                    <span class="font-semibold">{{ $o['label'] }}</span>
                                    @if (!empty($o['dk_status']))
                                        <span class="px-1.5 text-[10px] rounded {{ $o['dk_status'] === 'D' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600' }}">
                                            {{ $o['dk_status'] === 'D' ? 'D' : 'K' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs font-mono text-gray-400">{{ $o['gra_id'] }}</div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-lov.dropdown>
