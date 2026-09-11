<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use App\Http\Traits\BPJS\PcareTrait;

new class extends Component {
    use PcareTrait;

    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Diagnosa (ICD 10)';
    public string $placeholder = 'Ketik kode/nama diagnosa...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** Sumber data: false = lokal (skmst_mstdiags), true = BPJS PCare getDiagnosa */
    public bool $useBpjs = false;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim diag_id yang sudah tersimpan.
     * Cukup kirim initialDiagnosaId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialDiagnosaId = null;

    /**
     * Fallback deskripsi (mis. icdX dari BPJS yang belum ada di skmst_mstdiags).
     * Dipakai kalau lookup lokal gagal supaya edit mode tetap nampilin label.
     */
    #[Reactive]
    public ?string $initialDiagnosaDesc = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    /**
     * Tampilkan info tambahan di dropdown
     */
    public bool $showAdditionalInfo = true;

    public function mount(): void
    {
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialDiagnosaId)) {
            return;
        }

        // Cek berdasarkan diag_id terlebih dahulu
        $row = DB::table('skmst_mstdiags')->where('diag_id', $this->initialDiagnosaId)->first();

        // Jika tidak ditemukan, cek berdasarkan icdx
        if (!$row) {
            $row = DB::table('skmst_mstdiags')->where('icdx', $this->initialDiagnosaId)->first();
        }
        if ($row) {
            $this->setSelectedFromRow($row);
            return;
        }

        // Fallback: pakai desc dari parent (mis. data BPJS yang belum ada di lokal)
        if (!empty($this->initialDiagnosaDesc)) {
            $this->selected = [
                'diag_id' => (string) $this->initialDiagnosaId,
                'diag_desc' => (string) $this->initialDiagnosaDesc,
                'icdx' => (string) $this->initialDiagnosaId,
            ];
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'diag_id' => (string) $row->diag_id,
            'diag_desc' => (string) ($row->diag_desc ?? ''),
            'icdx' => (string) ($row->icdx ?? ''),
        ];
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

        if ($this->useBpjs) {
            $this->searchFromBpjs($keyword);
        } else {
            $this->searchFromLocal($keyword);
        }
    }

    /** Toggle sumber data lokal ↔ BPJS dan re-search */
    public function toggleSource(): void
    {
        $this->useBpjs = !$this->useBpjs;
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        if (mb_strlen(trim($this->search)) >= 2) {
            $this->updatedSearch();
        }
    }

    protected function searchFromLocal(string $keyword): void
    {
        // ===== 1) exact match by diag_id atau icdx =====
        $exactQuery = DB::table('skmst_mstdiags')->where(function ($q) use ($keyword) {
            $q->where('diag_id', $keyword . 'xxx')->orWhere('icdx', $keyword . 'xxxx');
        });

        $exactRow = $exactQuery->first();

        if ($exactRow) {
            $this->dispatchSelected($this->mapRowToPayload($exactRow));
            return;
        }

        // ===== 2) search by diag_id / icdx / diag_desc partial =====
        $upperKeyword = mb_strtoupper($keyword);

        $query = DB::table('skmst_mstdiags')
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw('UPPER(diag_id) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(icdx) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(diag_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('icdx')
            ->orderBy('diag_desc');

        $rows = $query->limit(50)->get();

        $this->options = $rows
            ->map(function ($row) {
                return $this->mapRowToOption($row);
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
        }
    }

    protected function searchFromBpjs(string $keyword): void
    {
        try {
            $resp = $this->getDiagnosa($keyword, 0, 50)->getOriginalContent();
            $code = $resp['metadata']['code'] ?? 0;

            if ($code != 200) {
                $msg = $resp['metadata']['message'] ?? "code {$code}";
                $this->dispatch('toast', type: 'error',
                    message: 'BPJS getDiagnosa: ' . $msg, title: 'BPJS');
                $this->closeAndResetList();
                return;
            }

            $list = $resp['response']['list'] ?? $resp['response'] ?? [];

            $this->options = collect($list)
                ->map(function ($row) {
                    $kd  = (string) ($row['kdDiag'] ?? $row['kode'] ?? '');
                    $nm  = (string) ($row['nmDiag'] ?? $row['nama'] ?? '');
                    return [
                        'diag_id' => $kd,
                        'diag_desc' => $nm,
                        'icdx' => $kd,
                        'label' => $kd ? "{$kd} - {$nm}" : $nm,
                        'code' => $kd,
                        'description' => $nm,
                        'hint' => 'BPJS · Kode: ' . $kd,
                    ];
                })
                ->filter(fn($o) => $o['diag_id'] !== '')
                ->values()
                ->toArray();

            $this->isOpen = count($this->options) > 0;
            $this->selectedIndex = 0;

            if ($this->isOpen) {
                $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
            }
        } catch (\Exception $e) {
            \Log::error('lov-diagnosa BPJS search exception', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error',
                message: 'Error BPJS: ' . $e->getMessage(), title: 'BPJS');
            $this->closeAndResetList();
        }
    }

    protected function mapRowToPayload($row): array
    {
        return [
            'diag_id' => (string) $row->diag_id,
            'diag_desc' => (string) ($row->diag_desc ?? ''),
            'icdx' => (string) ($row->icdx ?? ''),
        ];
    }

    protected function mapRowToOption($row): array
    {
        $diagId = (string) $row->diag_id;
        $icdx = (string) ($row->icdx ?? '');
        $diagDesc = (string) ($row->diag_desc ?? '');

        $displayCode = $icdx ?: $diagId;
        $displayText = $diagDesc ?: '-';

        return [
            // payload
            'diag_id' => $diagId,
            'diag_desc' => $diagDesc,
            'icdx' => $icdx,

            // UI
            'label' => $displayCode ? "{$displayCode} - {$displayText}" : $displayText,
            'code' => $displayCode,
            'description' => $diagDesc,
            'hint' => "Kode: {$displayCode}",
        ];
    }

    public function clearSelected(): void
    {
        // Jika disabled, tidak bisa clear selected
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();

        // Dispatch event ke parent bahwa selection di-clear
        $this->dispatch('lov.cleared.' . $this->target, target: $this->target);
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
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
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

        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $payload = [
            'diag_id' => $this->options[$index]['diag_id'] ?? '',
            'diag_desc' => $this->options[$index]['diag_desc'] ?? '',
            'icdx' => $this->options[$index]['icdx'] ?? '',
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
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function updatedInitialDiagnosaId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $row = DB::table('skmst_mstdiags')->where('diag_id', $value)->first()
            ?? DB::table('skmst_mstdiags')->where('icdx', $value)->first();

        if ($row) {
            $this->setSelectedFromRow($row);
            return;
        }

        if (!empty($this->initialDiagnosaDesc)) {
            $this->selected = [
                'diag_id' => (string) $value,
                'diag_desc' => (string) $this->initialDiagnosaDesc,
                'icdx' => (string) $value,
            ];
        }
    }

    /**
     * Get display text for selected item
     */
    public function getSelectedDisplayProperty(): string
    {
        if (!$this->selected) {
            return '';
        }

        $code = $this->selected['icdx'] ?: $this->selected['diag_id'];
        $desc = $this->selected['diag_desc'] ?? '';

        return $code ? "{$code} - {$desc}" : $desc;
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <div class="flex items-center justify-between">
        <x-input-label :value="$label" />
        @if ($selected === null && !$disabled)
            <button type="button" wire:click.prevent="toggleSource"
                class="text-xs px-2 py-0.5 rounded-md border transition
                    {{ $useBpjs
                        ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-300'
                        : 'bg-gray-50 border-gray-300 text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}"
                title="Toggle sumber: lokal / BPJS PCare">
                {{ $useBpjs ? 'Sumber: BPJS' : 'Sumber: Lokal' }}
            </button>
        @endif
    </div>

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted"
                    autocomplete="off" />
                <div wire:loading wire:target="search,toggleSource"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Mencari{{ $useBpjs ? ' di BPJS' : '' }}...
                </div>
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full bg-gray-50 dark:bg-gray-800" :value="$this->selectedDisplay"
                        disabled />
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-diag-{{ $option['diag_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? '-' }}
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
                        Data diagnosa tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
