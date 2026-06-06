<?php

/**
 * Pembayaran Hutang Non-Medis — Modal Proses Pembayaran.
 *
 * Port skema klinik dari modul Pembayaran Hutang PBF, memakai tabel/sequence
 * *NONS sendiri (cashout & payment non-medis terpisah dari medis).
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_angsuran:
 *   1. Validasi: bayar <= total sisa hutang nota terpilih
 *   2. INSERT 1x TKTXN_CASHOUTHDRNONS (master cashout)
 *   3. Loop nota terpilih (check_boxstatus='1') urut VCOUNT (urutan klik user):
 *        - Kalau bayar >= sisa nota: bayar full → INSERT cashoutdtlnons + rcvpaymentnons,
 *          UPDATE rcvhdrnons.rcv_status='L', kurangi bayar
 *        - Kalau bayar < sisa nota & bayar > 0: bayar partial (cicilan) → INSERT
 *          cashoutdtlnons + rcvpaymentnons dgn nominal sisa bayar, status tetap 'H', habiskan
 *   4. Reset check_boxstatus='0' & vcount=null untuk supplier ini
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $suppId   = null;
    public ?string $suppName = null;
    public float   $sisaChecked = 0.0;
    public int     $jumlah  = 0;

    public ?string $tanggal     = null;
    public ?string $cbId        = null;
    public ?string $keterangan  = null;
    public ?int    $bayar       = null;

    public array $renderVersions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open modal ── */
    #[On('hutang-non.openProsesBayar')]
    public function openProsesBayar(string $suppId, string $suppName, float $sisaChecked, int $jumlah = 0): void
    {
        $this->resetFormFields();
        $this->suppId      = $suppId;
        $this->suppName    = $suppName;
        $this->sisaChecked = $sisaChecked;
        $this->jumlah      = $jumlah;
        $this->tanggal     = Carbon::now()->format('d/m/Y H:i:s');
        $this->bayar       = (int) $sisaChecked; // default: lunasi semua yg dicentang
        $this->keterangan  = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pembayaran-hutang-non-medis-actions');
        $this->dispatch('focus-bayar-tanggal');
    }

    /* ── LOV Listener ── */
    #[On('lov.selected.hutang-non-cb')]
    public function onCbSelected(string $target, ?array $payload): void
    {
        $this->cbId = $payload['cb_id'] ?? null;
    }

    /* ── Proses pembayaran ── */
    public function processBayar(): void
    {
        $this->validate(
            [
                'suppId'   => 'required|string',
                'tanggal'  => 'required|date_format:d/m/Y H:i:s',
                'cbId'     => 'required|string|exists:tkacc_carabayars,cb_id',
                'bayar'    => 'required|integer|min:1',
            ],
            [
                'suppId.required'      => 'Supplier tidak terdeteksi.',
                'tanggal.required'     => 'Tanggal wajib diisi.',
                'tanggal.date_format'  => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'cbId.required'        => 'Cara bayar wajib dipilih.',
                'cbId.exists'          => 'Cara bayar tidak valid.',
                'bayar.required'       => 'Nominal bayar wajib diisi.',
                'bayar.min'            => 'Nominal bayar minimal Rp 1.',
            ],
        );

        $bayar = (int) $this->bayar;

        if ($bayar > (int) $this->sisaChecked) {
            $this->dispatch('toast', type: 'error',
                message: 'Nominal bayar (Rp ' . number_format($bayar)
                    . ') melebihi total sisa hutang nota terpilih (Rp ' . number_format($this->sisaChecked) . ').');
            return;
        }

        // Resolve kasir_id dari USERS.kasir_id
        $kasirId = auth()->user()->kasir_id ?? null;
        if ($kasirId) {
            $valid = DB::table('tkmst_kasirs')
                ->where('kasir_id', $kasirId)
                ->where('active_status', '1')
                ->exists();
            if (!$valid) $kasirId = null;
        }
        if (!$kasirId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil kasir Anda belum di-mapping (kasir_id). Hubungi admin via User Control.');
            return;
        }

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";

        $totalDibayarkan = 0;
        $notaLunas = 0;
        $notaCicilan = 0;

        try {
            DB::transaction(function () use ($kasirId, $tanggalDb, &$totalDibayarkan, &$notaLunas, &$notaCicilan, $bayar) {
                // Re-fetch nota terpilih dgn lock, urut VCOUNT (urutan klik user)
                $headers = DB::table('tktxn_rcvhdrnons')
                    ->where('supp_id', $this->suppId)
                    ->where('rcv_status', 'H')
                    ->where('check_boxstatus', '1')
                    ->orderBy('vcount')
                    ->orderBy('rcv_no')
                    ->lockForUpdate()
                    ->get();

                if ($headers->isEmpty()) {
                    throw new \RuntimeException('Tidak ada nota yang ditandai untuk dibayar (check_boxstatus=1).');
                }

                // Insert master cashout (1x)
                $cashoutNo = (int) DB::selectOne('SELECT cashoutnon_seq.nextval AS val FROM dual')->val;
                $desc = 'Angsuran Atas Nama "' . $this->suppName . '"'
                    . (!empty($this->keterangan) ? ' - ' . $this->keterangan : '');

                DB::table('tktxn_cashouthdrnons')->insert([
                    'cb_id'         => $this->cbId,
                    'cashout_no'    => $cashoutNo,
                    'cashout_date'  => DB::raw($tanggalDb),
                    'cashout_desc'  => $desc,
                    'cashout_value' => $bayar,
                    'kasir_id'      => $kasirId,
                    'supp_id'       => $this->suppId,
                ]);

                $sisaBayar = $bayar;

                foreach ($headers as $hdr) {
                    if ($sisaBayar <= 0) break;

                    $rcvNo = $hdr->rcv_no;

                    // Hitung sisa nota ini (re-compute)
                    $totalDetail = (float) DB::table('tktxn_rcvdtlnons')
                        ->where('rcv_no', $rcvNo)
                        ->select(DB::raw("
                            NVL(SUM(
                                (NVL(qty,0)*NVL(cost_price,0))
                                - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                                - NVL(dtl_diskon,0)
                                - (((NVL(qty,0)*NVL(cost_price,0))
                                    - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                                    - NVL(dtl_diskon,0))
                                  * NVL(dtl_persen1,0)/100)
                                - NVL(dtl_diskon1,0)
                            ),0) as t"))
                        ->value('t');

                    $setelahDiskon = $totalDetail - (float) ($hdr->rcv_diskon ?? 0);
                    $ppn = ((string) ($hdr->rcv_ppn_status ?? '1')) === '1'
                        ? ($setelahDiskon * (float) ($hdr->rcv_ppn ?? 0)) / 100
                        : 0;
                    // Cast ke int rupiah sejak awal — selisih sub-1 rupiah dari kaskade
                    // PPN/diskon nggak signifikan & bikin status nyangkut di 'H'.
                    $grandTotal = (int) round($setelahDiskon + $ppn + (float) ($hdr->rcv_materai ?? 0));

                    $titipan = (int) round((float) DB::table('tktxn_rcvpaymentnons')
                        ->where('rcv_no', $rcvNo)
                        ->sum('rcvp_value'));

                    $sisaNota = $grandTotal - $titipan;
                    if ($sisaNota <= 0) continue;

                    if ($sisaBayar >= $sisaNota) {
                        // Bayar full → lunas
                        $nominalBayar = $sisaNota;
                        $rcvStatusBaru = 'L';
                        $payDateRaw = DB::raw($tanggalDb);
                        $notaLunas++;
                    } else {
                        // Bayar partial → cicilan, status tetap H
                        $nominalBayar = $sisaBayar;
                        $rcvStatusBaru = 'H';
                        $payDateRaw = null;
                        $notaCicilan++;
                    }

                    DB::table('tktxn_rcvpaymentnons')->insert([
                        'rcvp_no'    => DB::raw('rcvpnon_seq.nextval'),
                        'rcv_no'     => $rcvNo,
                        'rcvp_date'  => DB::raw($tanggalDb),
                        'rcvp_value' => $nominalBayar,
                    ]);

                    DB::table('tktxn_cashoutdtlnons')->insert([
                        'cashout_no'  => $cashoutNo,
                        'rcv_no'      => $rcvNo,
                        'cashout_dtl' => DB::raw('codtlnon_seq.nextval'),
                    ]);

                    $updateData = ['rcv_status' => $rcvStatusBaru];
                    if ($payDateRaw !== null) {
                        $updateData['pay_date'] = $payDateRaw;
                    }

                    DB::table('tktxn_rcvhdrnons')
                        ->where('rcv_no', $rcvNo)
                        ->update($updateData);

                    $totalDibayarkan += $nominalBayar;
                    $sisaBayar -= $nominalBayar;
                }

                // Reset check_boxstatus & vcount untuk supplier (ekuivalen "menetralkan transaksi")
                DB::table('tktxn_rcvhdrnons')
                    ->where('supp_id', $this->suppId)
                    ->update([
                        'check_boxstatus' => '0',
                        'vcount'          => null,
                    ]);
            });

            $msg = "Pembayaran Rp " . number_format($totalDibayarkan) . " berhasil. ";
            $msg .= "{$notaLunas} nota lunas";
            if ($notaCicilan > 0) $msg .= ", {$notaCicilan} nota cicilan";
            $msg .= '.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->closeModal();
            $this->dispatch('hutang-non.paid');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pembayaran-hutang-non-medis-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['suppId', 'suppName', 'sisaChecked', 'jumlah', 'tanggal', 'cbId', 'keterangan', 'bayar']);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="pembayaran-hutang-non-medis-actions" size="2xl" focusable>
        <div class="flex flex-col" wire:key="{{ $this->renderKey('modal', [$suppId]) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Proses Pembayaran Hutang Non-Medis
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Sistem akan mengalokasikan pembayaran ke <strong>{{ $jumlah }}</strong> nota yang Anda centang (sesuai urutan klik).
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="px-6 py-5 space-y-5"
                x-data
                x-on:focus-bayar-tanggal.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))">

                {{-- Info Supplier --}}
                <div class="px-4 py-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Supplier</div>
                            <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ $suppName ?? '-' }}
                            </div>
                            <div class="font-mono text-xs text-gray-500">Kode: {{ $suppId ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Total Sisa Hutang Terpilih</div>
                            <div class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400">
                                Rp {{ number_format($sisaChecked) }}
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
                    <livewire:lov.cara-bayar.lov-cara-bayar
                        target="hutang-non-cb"
                        label="Cara Bayar"
                        placeholder="Cari cara bayar..."
                        :initialCbId="$cbId"
                        wire:key="lov-cb-hutang-non-{{ $suppId ?? 'empty' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('cbId')" class="mt-1" />
                </div>

                {{-- Nominal Bayar --}}
                <div>
                    <x-input-label value="Nominal Bayar (Rp)" :required="true" />
                    <x-text-input-number wire:model="bayar" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Default = total sisa nota terpilih. Bisa kurang dari itu untuk angsuran (sisa nota tetap berstatus Hutang).
                    </p>
                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                </div>

                {{-- Keterangan --}}
                <div>
                    <x-input-label value="Keterangan (opsional)" />
                    <x-text-input type="text" wire:model="keterangan" placeholder="Keterangan tambahan..."
                        class="w-full mt-1" />
                </div>

                {{-- Note --}}
                <div class="px-4 py-3 border rounded-lg border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="text-xs text-amber-800 dark:text-amber-300">
                            <p class="font-semibold">Cara alokasi pembayaran:</p>
                            <ul class="mt-1 ml-5 space-y-0.5 list-disc">
                                <li>Nota dialokasikan sesuai <strong>urutan klik</strong> (badge angka di tabel) — jadi user yang menentukan nota mana yang dilunasi duluan</li>
                                <li>Nominal bayar dialokasikan penuh sampai nota lunas, sisa diteruskan ke nota berikutnya</li>
                                <li>Kalau nominal kurang dari total sisa, nota terakhir akan menjadi <strong>cicilan</strong> (status tetap Hutang)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-3">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="processBayar"
                        wire:loading.attr="disabled" wire:target="processBayar">
                        <span wire:loading.remove wire:target="processBayar">Proses Pembayaran</span>
                        <span wire:loading wire:target="processBayar">Memproses...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
