<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/laborat/rm-laborat-rj-actions.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Support\KolomOpsional;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, WithValidationToastTrait, EmrRJTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['laborat-order-modal'];

    /* =======================
     | Props dari parent
     * ======================= */
    public string $rjNo = '';
    public bool $disabled = false;

    /* =======================
     | State Modal
     * ======================= */
    public string $searchItem = '';
    public array $selectedItems = []; // [ clabitem_id => [...item] ]
    public string $klinisDesc = ''; // Diagnosis/Keterangan Klinis — wajib diisi

    protected function rules(): array
    {
        return [
            'klinisDesc' => 'required|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'klinisDesc.required' => 'Diagnosis/Keterangan Klinis harus diisi.',
            'klinisDesc.max' => 'Diagnosis/Keterangan Klinis maksimal 500 karakter.',
        ];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(string $rjNo = '', bool $disabled = false): void
    {
        $this->rjNo = $rjNo;
        $this->disabled = $disabled;
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selectedItems = [];
        $this->searchItem = '';
        $this->klinisDesc = '';
        $this->resetValidation();
        $this->resetPage();
        $this->incrementVersion('laborat-order-modal');

        $this->dispatch('open-modal', name: "laborat-order-rj-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "laborat-order-rj-{$this->rjNo}");
        $this->reset(['selectedItems', 'searchItem', 'klinisDesc']);
        $this->resetValidation();
    }

    /* ===============================
     | QUERY ITEM LAB (paginated)
     =============================== */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchItem);

        return DB::table('skmst_clabitems')->select('clabitem_id', 'clabitem_desc', 'price', 'clabitem_group', 'item_code')->whereNull('clabitem_group')->whereNotNull('clabitem_desc')->when($search, fn($q) => $q->whereRaw('UPPER(clabitem_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))->orderBy('clabitem_desc', 'asc')->paginate(15);
    }

    /* ===============================
     | TOGGLE / REMOVE SELECTED ITEM
     =============================== */
    public function toggleItem(string $id, string $desc, ?float $price, ?string $itemCode): void
    {
        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'clabitem_id' => $id,
                'clabitem_desc' => $desc,
                'price' => $price,
                'item_code' => $itemCode,
            ];
        }
    }

    public function isSelected(string $id): bool
    {
        return isset($this->selectedItems[$id]);
    }

    public function removeSelected(string $id): void
    {
        unset($this->selectedItems[$id]);
    }

    /* ===============================
     | KIRIM ORDER LABORATORIUM
     =============================== */
    public function kirimLaboratorium(): void
    {
        // 1. Guard: tidak ada item dipilih
        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        // 2. Guard: Diagnosis/Keterangan Klinis wajib diisi (rules + toast)
        //    Dilempar SEBELUM sentuhan DB apa pun — order gagal tanpa menulis baris.
        $this->klinisDesc = trim($this->klinisDesc);
        $this->validateWithToast();

        // 3. Guard: pasien sudah pulang
        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah pemeriksaan.');
            return;
        }

        // 4. Ambil reg_no & dr_id
        $rjData = $this->getRjData();
        if (!$rjData) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($rjData) {
                // 5. Lock row JSON dulu — cegah race condition update JSON bersamaan
                $this->lockRJRow($this->rjNo);

                $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM sktxn_checkuphdrs');

                // 6. Insert header sktxn_checkuphdrs
                $header = [
                    'checkup_no' => $checkupNo,
                    'reg_no' => $rjData->reg_no,
                    'dr_id' => $rjData->dr_id,
                    'checkup_date' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
                    'status_rjri' => 'RJ',
                    'checkup_status' => 'P',
                    'ref_no' => $this->rjNo,
                ];

                // Kolom klinis_desc datang dari database/sql/2026_09_11_alter_penunjang_add_klinis_desc.sql.
                // Selama SQL itu belum dijalankan DBA, key-nya tidak ikut di-insert (cegah ORA-00904)
                // — order tetap terkirim, keterangan klinisnya saja yang belum tersimpan.
                if (KolomOpsional::laboratPunyaKlinisDesc()) {
                    $header[KolomOpsional::KLINIS_DESC] = $this->klinisDesc;
                }

                DB::table('sktxn_checkuphdrs')->insert($header);

                // 7. Insert detail untuk setiap item yang dipilih
                foreach ($this->selectedItems as $item) {
                    $this->insertItemAndChildren($checkupNo, $item);
                }

                // 8. Ambil data terkini dari DB (setelah lock) + patch key lab
                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                $labList = $data['pemeriksaan']['pemeriksaanPenunjang']['lab'] ?? [];
                $labList[] = [
                    'labHdr' => [
                        'labHdrNo' => $checkupNo,
                        'labHdrDate' => $now,
                        'labDtl' => array_values($this->selectedItems),
                    ],
                ];

                $data['pemeriksaan']['pemeriksaanPenunjang']['lab'] = $labList;

                $this->updateJsonRJ($this->rjNo, $data);

                // Audit log (kategori Rekam Medis)
                $this->appendAdminLogRJ((int) $this->rjNo, 'Order Lab — ' . collect($this->selectedItems)->pluck('clabitem_desc')->implode(', '), 'MR');
            });

            // 9. Notify parent agar refresh dataDaftarPoliRJ
            $this->dispatch('laborat-order-terkirim');
            $this->dispatch('toast', type: 'success', message: count($this->selectedItems) . ' item laboratorium berhasil dikirim.');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */

    /**
     * Ambil reg_no & dr_id dari DB.
     */
    private function getRjData(): ?object
    {
        return DB::table('sktxn_rjhdrs')->select('reg_no', 'dr_id')->where('rj_no', $this->rjNo)->first();
    }

    /**
     * Insert satu item + child items (clabitem_group) ke sktxn_checkupdtls.
     * Dipanggil dari dalam DB::transaction.
     */
    private function insertItemAndChildren(int $checkupNo, array $item): void
    {
        $dtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM sktxn_checkupdtls');

        DB::table('sktxn_checkupdtls')->insert([
            'clabitem_id' => $item['clabitem_id'],
            'checkup_no' => $checkupNo,
            'checkup_dtl' => $dtlNo,
            'lab_item_code' => $item['item_code'],
            'price' => $item['price'],
        ]);

        // Insert child items (sub-panel)
        $children = DB::table('skmst_clabitems')->select('clabitem_id', 'item_code', 'price')->where('clabitem_group', $item['clabitem_id'])->orderBy('item_seq', 'asc')->orderBy('clabitem_desc', 'asc')->get();

        foreach ($children as $child) {
            $childDtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM sktxn_checkupdtls');

            DB::table('sktxn_checkupdtls')->insert([
                'clabitem_id' => $child->clabitem_id,
                'checkup_no' => $checkupNo,
                'checkup_dtl' => $childDtlNo,
                'lab_item_code' => $child->item_code,
                'price' => $child->price,
            ]);
        }
    }
};
?>

<div>
    <div class="grid grid-cols-1 my-2">
        {{-- Tombol trigger --}}
        <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal"
            :disabled="$disabled">
            <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Order Laboratorium
            </span>
            <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                <x-loading /> Memuat...
            </span>
        </x-primary-button>
    </div>

    {{-- Modal Order Laboratorium --}}
    <x-modal name="laborat-order-rj-{{ $rjNo }}" size="full" height="full"
        focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('laborat-order-modal', [$rjNo ?: 'empty']) }}">

            {{-- Modal Header --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.05]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-green/15">
                            <svg class="w-5 h-5 text-brand-green dark:text-brand-lime" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Order Pemeriksaan Laboratorium
                            </h2>
                            <p class="text-xs text-gray-500">No. RJ: <span
                                    class="font-mono font-medium">{{ $rjNo }}</span></p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- Display Pasien --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                    wire:key="display-pasien-rj-{{ $rjNo }}" />
            </div>

            {{-- Selected Items Chips --}}
            @if (!empty($selectedItems))
                <div class="flex flex-wrap items-center gap-1.5 px-6 py-2 border-b border-gray-100 dark:border-gray-700 bg-brand-green/5">
                    <p class="text-xs font-semibold text-brand-green shrink-0">
                        {{ count($selectedItems) }} item dipilih:
                    </p>
                    @foreach ($selectedItems as $id => $sel)
                            <x-badge variant="brand" class="gap-1 !rounded-full border border-brand-green/20">
                                {{ $sel['clabitem_desc'] }}
                                @if ($sel['price'])
                                    <span class="text-brand-green/60">· {{ number_format($sel['price']) }}</span>
                                @endif
                                <button type="button" wire:click="removeSelected('{{ $id }}')"
                                    class="ml-0.5 hover:text-red-500 transition-colors" title="Hapus item">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-badge>
                    @endforeach
                </div>
            @endif

            {{-- Diagnosis/Keterangan Klinis — wajib, dibaca petugas laboratorium --}}
            <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700">
                <x-input-label for="klinisDescLaborat" value="Diagnosis/Keterangan Klinis" required />
                <x-textarea id="klinisDescLaborat" wire:model="klinisDesc" rows="2" maxlength="500"
                    class="mt-1 text-sm" placeholder="Diagnosis kerja / keterangan klinis pasien..."
                    :error="$errors->has('klinisDesc')" />
                <x-input-error :messages="$errors->get('klinisDesc')" class="mt-1" />
            </div>

            {{-- Search --}}
            <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="searchItem"
                        placeholder="Cari item pemeriksaan..."
                        class="w-full py-2 pl-10 pr-4 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
                </div>
            </div>

            {{-- Item Grid — partial: pilihan item pemeriksaan --}}
            @include('pages.transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.partials.grid-item-laborat')

            {{-- Modal Footer --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">

                    {{-- Kiri: info --}}
                    <div>
                        @if (!empty($selectedItems))
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-brand-green bg-brand-green/10 border border-brand-green/30 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                {{ count($selectedItems) }} item dipilih
                            </span>
                        @else
                            <span class="text-xs italic text-gray-400">Klik item untuk memilih pemeriksaan</span>
                        @endif
                    </div>

                    {{-- Kanan: buttons --}}
                    <div class="flex items-center gap-3">
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        @if (!empty($selectedItems))
                            <x-primary-button type="button" wire:click="kirimLaboratorium"
                                wire:loading.attr="disabled" wire:target="kirimLaboratorium">
                                <span wire:loading.remove wire:target="kirimLaboratorium"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Kirim Order
                                </span>
                                <span wire:loading wire:target="kirimLaboratorium" class="flex items-center gap-1.5">
                                    <x-loading /> Mengirim...
                                </span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
