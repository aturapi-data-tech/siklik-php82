<?php

/**
 * LOV Akun (general purpose) — sumber: skacc_accountses where active_status='1'.
 *
 * Props filter:
 *   - kasOnly: true → hanya kas_status='1' (akun kas / bank)
 *   - dkStatus: 'D' | 'K' | '' → filter debit/kredit
 *   - graId: '' atau 'GRA_XXX' → filter group tertentu
 *
 * Payload: ['acc_id', 'acc_desc', 'kas_status', 'gra_id', 'gra_desc', 'acc_dk_status']
 *
 * Replace lov-akun-ci, lov-akun-co, lov-kas (sirus-only) — query tabel
 * yang benar (skacc_accountses).
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Akun';
    public string $placeholder = 'Ketik kode/nama akun...';

    /* Filter (props dari parent) */
    public bool $kasOnly = false;
    public string $dkStatus = '';
    public string $graId = '';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialAccId = null;

    public bool $disabled = false;

    public function mount(): void
    {
        if ($this->initialAccId) $this->loadSelected($this->initialAccId);
    }

    public function updatedInitialAccId(?string $value): void
    {
        $this->selected = null;
        $this->reset(['search', 'options', 'isOpen']);
        if (!empty($value)) $this->loadSelected($value);
    }

    /* Load tanpa filter active — supaya record lama yg ke-nonaktifkan tetap nampil di edit mode */
    protected function loadSelected(string $accId): void
    {
        $row = DB::table('skacc_accountses as a')
            ->leftJoin('skacc_gr_accountses as g', 'g.gra_id', '=', 'a.gra_id')
            ->select('a.acc_id', 'a.acc_desc', 'a.kas_status', 'a.gra_id',
                'g.gra_desc', 'a.acc_dk_status', 'a.active_status')
            ->where('a.acc_id', $accId)->first();
        if ($row) $this->selected = $this->buildPayload($row);
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;
        $kw = trim($this->search);
        if (mb_strlen($kw) < 1) { $this->closeAndResetList(); return; }

        $up = mb_strtoupper($kw);

        $exact = $this->baseQuery()->where('a.acc_id', $up)->first();
        if ($exact) { $this->dispatchSelected($this->buildPayload($exact)); return; }

        $rows = $this->baseQuery()
            ->where(function ($q) use ($up) {
                $q->whereRaw('UPPER(a.acc_id) LIKE ?', ["%{$up}%"])
                  ->orWhereRaw('UPPER(a.acc_desc) LIKE ?', ["%{$up}%"]);
            })
            ->orderBy('a.acc_id')->limit(50)->get();

        $this->options = $rows->map(fn($r) => array_merge($this->buildPayload($r), [
            'label' => (string) ($r->acc_desc ?: $r->acc_id),
        ]))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
        if ($this->isOpen) $this->emitScroll();
    }

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('skacc_accountses as a')
            ->leftJoin('skacc_gr_accountses as g', 'g.gra_id', '=', 'a.gra_id')
            ->select('a.acc_id', 'a.acc_desc', 'a.kas_status', 'a.gra_id',
                'g.gra_desc', 'a.acc_dk_status', 'a.active_status')
            ->where('a.active_status', '1');

        if ($this->kasOnly) {
            $q->where('a.kas_status', '1');
        }
        if ($this->dkStatus !== '') {
            $q->where('a.acc_dk_status', $this->dkStatus);
        }
        if ($this->graId !== '') {
            $q->where('a.gra_id', $this->graId);
        }

        return $q;
    }

    protected function buildPayload(object $r): array
    {
        return [
            'acc_id'        => (string) $r->acc_id,
            'acc_desc'      => (string) ($r->acc_desc ?? ''),
            'kas_status'    => (string) ($r->kas_status ?? '0'),
            'gra_id'        => (string) ($r->gra_id ?? ''),
            'gra_desc'      => (string) ($r->gra_desc ?? ''),
            'acc_dk_status' => (string) ($r->acc_dk_status ?? ''),
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
                    :value="$selected['acc_id'] . ' — ' . $selected['acc_desc']" disabled />
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-[10px] text-gray-400">
                @if (!empty($selected['gra_desc']))
                    <span>{{ $selected['gra_desc'] }}</span>
                @endif
                @if (!empty($selected['acc_dk_status']))
                    <span>·</span>
                    <span>{{ $selected['acc_dk_status'] === 'D' ? 'Debit' : 'Kredit' }}</span>
                @endif
                @if ((string) ($selected['kas_status'] ?? '0') === '1')
                    <span>·</span>
                    <span class="text-amber-600 dark:text-amber-400 font-semibold">KAS</span>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && !$disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $i => $o)
                        <li wire:key="lov-akun-{{ $o['acc_id'] }}-{{ $i }}" x-ref="lovItem{{ $i }}">
                            <x-lov.item wire:click="choose({{ $i }})" :active="$i === $selectedIndex">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">{{ $o['label'] }}</span>
                                    <div class="flex gap-1 shrink-0">
                                        @if ((string) ($o['kas_status'] ?? '0') === '1')
                                            <span class="px-1.5 text-[10px] rounded bg-amber-100 text-amber-700">KAS</span>
                                        @endif
                                        @if (!empty($o['acc_dk_status']))
                                            <span class="px-1.5 text-[10px] rounded {{ $o['acc_dk_status'] === 'D' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600' }}">
                                                {{ $o['acc_dk_status'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-xs font-mono text-gray-400">
                                    {{ $o['acc_id'] }}
                                    @if (!empty($o['gra_desc']))
                                        · {{ $o['gra_desc'] }}
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>
                @if (mb_strlen(trim($search)) >= 1 && !count($options))
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Akun tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
