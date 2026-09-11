<?php

/**
 * Pembayaran Piutang RJ — Modal Pelunasan (Grouping).
 *
 * Equivalent dgn xtogle button + g_rj procedure di Oracle Forms SKVIEW_RJKASIR:
 *  - Loop rj_no piutang yang ditandai user via toggle (rj_status='L', txn_status='H', cek_bayar='1')
 *  - Hitung total tagihan vs sudah dibayar (titipan)
 *  - Jika sisa>0: insert SKTXN_RJCASHINS (g_status='G'), UPDATE SKTXN_RJHDRS.txn_status='L'
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $regNo = null;
    public ?string $regName = null;
    public float $totsisa = 0.0;
    public int $jumlah = 0;

    public ?string $tanggal = null;
    public ?string $cbId = null;

    public array $renderVersions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open modal ── */
    #[On('piutang-rj.openGrouping')]
    public function openGrouping(string $regNo, string $regName, float $totsisa, int $jumlah = 0): void
    {
        $this->resetFormFields();
        $this->regNo = $regNo;
        $this->regName = $regName;
        $this->totsisa = $totsisa;
        $this->jumlah = $jumlah;
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pembayaran-piutang-rj-actions');
        $this->dispatch('focus-piutang-tanggal');
    }

    /* ── LOV Listener ── */
    #[On('lov.selected.piutang-rj-cb')]
    public function onCbSelected(string $target, ?array $payload): void
    {
        $this->cbId = $payload['cb_id'] ?? null;
        $this->dispatch('focus-btn-proses-piutang');
    }

    /* ── Proses pelunasan ── */
    public function processGrouping(): void
    {
        $this->validate(
            [
                'regNo' => 'required|string',
                'tanggal' => 'required|date_format:d/m/Y H:i:s',
                'cbId' => 'required|string|exists:skacc_carabayars,cb_id',
            ],
            [
                'regNo.required' => 'Pasien tidak terdeteksi.',
                'tanggal.required' => 'Tanggal wajib diisi.',
                'tanggal.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'cbId.required' => 'Cara bayar wajib dipilih.',
                'cbId.exists' => 'Cara bayar tidak valid.',
            ],
        );

        // Resolve kasir_id dari USERS.kasir_id
        $kasirId = auth()->user()->kasir_id ?? null;
        if ($kasirId) {
            $valid = DB::table('skmst_kasirs')->where('kasir_id', $kasirId)->where('active_status', '1')->exists();
            if (!$valid) {
                $kasirId = null;
            }
        }
        if (!$kasirId) {
            $this->dispatch('toast', type: 'error', message: 'Profil kasir Anda belum di-mapping (kasir_id). Hubungi admin via User Control.');
            return;
        }

        // Resolve shift dari SKTXN_SHIFTCTLS by jam tanggal pembayaran
        $jam = Carbon::createFromFormat('d/m/Y H:i:s', $this->tanggal)->format('H:i:s');
        $shift =
            DB::table('sktxn_shiftctls')
                ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
                ->value('shift') ?? '1';

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";

        $processedCount = 0;
        $totalNominal = 0;

        try {
            DB::transaction(function () use ($kasirId, $shift, $tanggalDb, &$processedCount, &$totalNominal) {
                // Re-fetch rj piutang dgn lock
                $headers = DB::table('sktxn_rjhdrs')->where('reg_no', $this->regNo)->where('rj_status', 'L')->where('txn_status', 'H')->where('cek_bayar', '1')->lockForUpdate()->get();

                if ($headers->isEmpty()) {
                    throw new \RuntimeException('Tidak ada transaksi yang ditandai untuk dilunasi (cek_bayar=1).');
                }

                $regName = DB::table('skmst_pasiens')->where('reg_no', $this->regNo)->value('reg_name');

                foreach ($headers as $hdr) {
                    $rjNo = $hdr->rj_no;

                    $hn = (float) DB::table('sktxn_rjaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price');
                    $obat = (float) DB::table('sktxn_rjobats')->where('rj_no', $rjNo)->sum(DB::raw('qty*price'));
                    $jk = (float) DB::table('sktxn_rjactemps')->where('rj_no', $rjNo)->sum('acte_price');
                    $lab = (float) DB::table('sktxn_rjlabs')->where('rj_no', $rjNo)->sum('lab_price');
                    $jm = (float) DB::table('sktxn_rjactparams')->where('rj_no', $rjNo)->sum('pact_price');
                    $rad = (float) DB::table('sktxn_rjrads')->where('rj_no', $rjNo)->sum('rad_price');
                    $other = (float) DB::table('sktxn_rjothers')->where('rj_no', $rjNo)->sum('other_price');
                    $titip = (float) DB::table('sktxn_rjcashins')->where('rj_no', $rjNo)->sum('rjc_nominal');

                    $total = $hn + $obat + $jk + $lab + $jm + $rad + $other + (float) ($hdr->rj_admin ?? 0) + (float) ($hdr->rs_admin ?? 0) + (float) ($hdr->poli_price ?? 0) - (float) ($hdr->rj_diskon ?? 0);

                    $sisa = $total - $titip;

                    if ($sisa > 0) {
                        DB::table('sktxn_rjcashins')->insert([
                            'cb_id' => $this->cbId,
                            'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
                            'rjc_date' => DB::raw($tanggalDb),
                            'rjc_desc' => ($regName ?? '') . ' / ' . $rjNo,
                            'rjc_nominal' => $sisa,
                            'kasir_id' => $kasirId,
                            'rj_no' => $rjNo,
                            'shift' => $shift,
                            'g_status' => 'G',
                        ]);

                        DB::table('sktxn_rjhdrs')
                            ->where('rj_no', $rjNo)
                            ->update([
                                'txn_status' => 'L',
                                'pay_date' => DB::raw($tanggalDb),
                                'cb_id' => $this->cbId,
                                'kasir_id' => $kasirId,
                                'cek_bayar' => '0',
                            ]);

                        $processedCount++;
                        $totalNominal += $sisa;
                    }
                }

                if ($processedCount === 0) {
                    throw new \RuntimeException('Semua piutang sudah Rp 0, tidak ada yang perlu diproses.');
                }
            });

            $this->dispatch('toast', type: 'success', message: "Pelunasan {$processedCount} transaksi berhasil. Total: Rp " . number_format($totalNominal));
            $this->closeModal();
            $this->dispatch('piutang-rj.grouped');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pelunasan: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pembayaran-piutang-rj-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['regNo', 'regName', 'totsisa', 'jumlah', 'tanggal', 'cbId']);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="pembayaran-piutang-rj-actions" size="2xl" focusable>
        <div class="flex flex-col" wire:key="{{ $this->renderKey('modal', [$regNo]) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Proses Pelunasan Piutang RJ
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Sistem akan melunasi <strong>{{ $jumlah }}</strong> transaksi RJ piutang yang Anda
                            centang.
                        </p>
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

            {{-- BODY --}}
            <div class="px-6 py-5 space-y-5" x-data
                x-on:focus-piutang-tanggal.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))"
                x-on:focus-btn-proses-piutang.window="$nextTick(() => setTimeout(() => $refs.btnProses?.focus(), 150))">

                {{-- Info Pasien --}}
                <div
                    class="px-4 py-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Nama Pasien</div>
                            <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ $regName ?? '-' }}
                            </div>
                            <div class="font-mono text-xs text-gray-500">No RM: {{ $regNo ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Total Sisa Piutang</div>
                            <div class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400">
                                Rp {{ number_format($totsisa) }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tanggal --}}
                <div>
                    <x-input-label value="Tanggal Pembayaran" :required="true" />
                    <x-text-input type="text" wire:model="tanggal" placeholder="dd/mm/yyyy hh:mm:ss"
                        x-ref="inputTanggal" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                </div>

                {{-- Cara Bayar --}}
                <div>
                    <livewire:lov.cara-bayar.lov-cara-bayar target="piutang-rj-cb" label="Cara Bayar"
                        placeholder="Cari cara bayar..." :initialCbId="$cbId"
                        wire:key="lov-cb-piutang-{{ $regNo ?? 'empty' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('cbId')" class="mt-1" />
                </div>

                {{-- Note --}}
                <div
                    class="px-4 py-3 border rounded-lg border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="text-xs text-amber-800 dark:text-amber-300">
                            <p class="font-semibold">Pastikan sebelum melanjutkan:</p>
                            <ul class="mt-1 ml-5 space-y-0.5 list-disc">
                                <li>Tanggal & cara bayar sudah benar</li>
                                <li>Hanya {{ $jumlah }} transaksi yang Anda <strong>centang</strong> akan dilunasi
                                </li>
                                <li>Status transaksi akan berubah ke <strong>Lunas (L)</strong> dan tidak bisa di-undo
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-3">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="processGrouping" x-ref="btnProses"
                        wire:loading.attr="disabled" wire:target="processGrouping">
                        <span wire:loading.remove wire:target="processGrouping">Proses Pelunasan</span>
                        <span wire:loading wire:target="processGrouping">Memproses...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
