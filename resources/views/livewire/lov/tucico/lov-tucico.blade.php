<?php

/**
 * LOV TUCICO — sumber: skacc_tucicos where active_status='1'.
 *
 * tucico_status = 'CI' (cash in / penerimaan) atau 'CO' (cash out / pengeluaran).
 *
 * Props:
 *   - filterStatus: 'CI' | 'CO' | '' → filter kategori. Default: '' (semua).
 *
 * Payload: ['tucico_id', 'tucico_desc', 'tucico_status', 'acc_id', 'acc_name']
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';
    public string $label = 'TUCICO';
    public string $placeholder = 'Ketik kode/nama TUCICO...';

    /** Filter kategori: 'CI' / 'CO' / '' (semua) */
    public string $filterStatus = '';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialTucicoId = null;

    public bool $disabled = false;

    public function mount(): void
    {
        if ($this->initialTucicoId) $this->loadSelected($this->initialTucicoId);
    }

    public function updatedInitialTucicoId(?string $value): void
    {
        $this->selected = null;
        $this->reset(['search', 'options', 'isOpen']);
        if (!empty($value)) $this->loadSelected($value);
    }

    protected function loadSelected(string $id): void
    {
        $row = DB::table('skacc_tucicos as t')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 't.acc_id')
            ->select('t.tucico_id', 't.tucico_desc', 't.tucico_status', 't.acc_id', 'a.acc_desc as acc_name')
            ->where('t.tucico_id', $id)->first();
        if ($row) $this->selected = $this->buildPayload($row);
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;
        $kw = trim($this->search);
        if (mb_strlen($kw) < 1) { $this->closeAndResetList(); return; }

        $up = mb_strtoupper($kw);

        $exact = $this->baseQuery()->where('t.tucico_id', $up)->first();
        if ($exact) { $this->dispatchSelected($this->buildPayload($exact)); return; }

        $rows = $this->baseQuery()
            ->where(function ($q) use ($up) {
                $q->whereRaw('UPPER(t.tucico_id) LIKE ?', ["%{$up}%"])
                  ->orWhereRaw('UPPER(t.tucico_desc) LIKE ?', ["%{$up}%"]);
            })
            ->orderBy('t.tucico_desc')->limit(50)->get();

        $this->options = $rows->map(fn($r) => array_merge($this->buildPayload($r), [
            'label' => (string) ($r->tucico_desc ?: $r->tucico_id),
        ]))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
        if ($this->isOpen) $this->emitScroll();
    }

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('skacc_tucicos as t')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 't.acc_id')
            ->select('t.tucico_id', 't.tucico_desc', 't.tucico_status', 't.acc_id', 'a.acc_desc as acc_name')
            ->where('t.active_status', '1');

        if ($this->filterStatus !== '') {
            $q->where('t.tucico_status', $this->filterStatus);
        }

        return $q;
    }

    protected function buildPayload(object $r): array
    {
        return [
            'tucico_id'     => (string) $r->tucico_id,
            'tucico_desc'   => (string) ($r->tucico_desc ?? ''),
            'tucico_status' => (string) ($r->tucico_status ?? ''),
            'acc_id'        => (string) ($r->acc_id ?? ''),
            'acc_name'      => (string) ($r->acc_name ?? ''),
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
                    :value="$selected['tucico_id'] . ' — ' . $selected['tucico_desc']" disabled />
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
            @if (!empty($selected['acc_id']))
                <div class="mt-1 text-[10px] text-gray-400">
                    akun: {{ $selected['acc_id'] }}
                    @if (!empty($selected['acc_name']))
                        ({{ $selected['acc_name'] }})
                    @endif
                </div>
            @endif
        @endif

        @if ($isOpen && $selected === null && !$disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $i => $o)
                        <li wire:key="lov-tucico-{{ $o['tucico_id'] }}-{{ $i }}" x-ref="lovItem{{ $i }}">
                            <x-lov.item wire:click="choose({{ $i }})" :active="$i === $selectedIndex">
                                <span class="font-semibold">{{ $o['label'] }}</span>
                                <div class="text-xs font-mono text-gray-400">
                                    {{ $o['tucico_id'] }}
                                    @if (!empty($o['acc_id']))
                                        · akun: {{ $o['acc_id'] }}
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-lov.dropdown>
