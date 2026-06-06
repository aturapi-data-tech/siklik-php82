<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AntrianTrait;

/**
 * Jadwal Mingguan — fetch jadwal dokter dari BPJS Antrean RS untuk 7 hari.
 *
 * Flow:
 *   1. User pilih poli (LOV → ambil kd_poli_bpjs)
 *   2. User pilih start date (default hari ini)
 *   3. Klik "Ambil Jadwal" → loop 7 hari ke
 *      GET {ANTRIAN_URL}/jadwaldokter/kodepoli/{kdPoli}/tanggal/{tgl}
 *   4. Aggregate per dokter → tabel dokter (rows) × hari (cols)
 *
 * Output yang dipake:
 *   - Visual reference jadwal mingguan klinik di BPJS
 *   - Bisa dipakai untuk validasi/sync manual ke SCMST_SCPOLIS
 */
new class extends Component {
    use AntrianTrait;

    /* ─── Form input ─── */
    public string $poliId = '';
    public string $poliDesc = '';
    public string $kdPoliBpjs = '';
    public string $startDate = '';

    /* ─── Hasil fetch ─── */
    public array $tanggalKolom = [];     // 7 dates Y-m-d
    public array $hariLabel    = [];     // 7 labels SENIN..MINGGU
    public array $jadwal       = [];     // [kodedokter => [namadokter, days[Y-m-d => {jampraktek, kapasitas}]]]
    public array $progressLog  = [];     // log per hari
    public bool  $isFetching   = false;
    public bool  $hasResult    = false;

    /* ─── Apply ke SCMST_SCPOLIS ─── */
    public array $applyLog    = [];
    public string $applyResult = '';
    public bool  $isApplying  = false;

    public function mount(): void
    {
        $this->startDate = Carbon::now(config('app.timezone'))->format('Y-m-d');
    }

    /* ===============================
     | LOCAL SCHEDULE — query SCVIEW_SCPOLIS untuk poli yg dipilih
     |
     | Return shape:
     |   [dr_id => ['dr_name', 'kd_dr_bpjs', 'days' => [day_id => [{ket, kuota}, ...]]]]
     =============================== */
    #[Computed]
    public function localJadwal(): array
    {
        if (empty($this->poliId)) return [];

        $rows = DB::table('scview_scpolis')
            ->select('dr_id', 'dr_name', 'kd_dr_bpjs', 'day_id', 'sc_poli_ket', 'kuota', 'mulai_praktek')
            ->where('poli_id', (int) $this->poliId)
            ->where('sc_poli_status_', '1')
            ->orderBy('dr_name')
            ->orderBy('day_id')
            ->orderBy('mulai_praktek')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $key = (string) $r->dr_id;
            if (!isset($result[$key])) {
                $result[$key] = [
                    'dr_id'      => $r->dr_id,
                    'dr_name'    => $r->dr_name ?: '-',
                    'kd_dr_bpjs' => $r->kd_dr_bpjs ?: '',
                    'days'       => [],
                ];
            }
            $result[$key]['days'][(int) $r->day_id][] = [
                'ket'   => $r->sc_poli_ket,
                'kuota' => (int) $r->kuota,
            ];
        }
        return $result;
    }

    /* ===============================
     | LOV poli
     =============================== */
    #[On('lov.selected.jadwal-mingguan-poli')]
    public function onPoliSelected(?array $payload): void
    {
        if (!$payload) {
            $this->poliId = '';
            $this->poliDesc = '';
            $this->kdPoliBpjs = '';
            return;
        }
        $this->poliId     = (string) ($payload['poli_id'] ?? '');
        $this->poliDesc   = (string) ($payload['poli_desc'] ?? '');
        $this->kdPoliBpjs = (string) ($payload['kd_poli_bpjs'] ?? '');
    }

    public function clearPoli(): void
    {
        $this->poliId = '';
        $this->poliDesc = '';
        $this->kdPoliBpjs = '';
        $this->resetHasil();
    }

    public function resetHasil(): void
    {
        $this->jadwal = [];
        $this->progressLog = [];
        $this->tanggalKolom = [];
        $this->hariLabel = [];
        $this->hasResult = false;
        $this->applyLog = [];
        $this->applyResult = '';
    }

    /* ===============================
     | LOOP fetch 7 hari
     =============================== */
    public function fetchJadwal(): void
    {
        if (empty($this->kdPoliBpjs)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih poli dulu (yang punya kode BPJS).');
            return;
        }
        if (empty($this->startDate)) {
            $this->dispatch('toast', type: 'error', message: 'Tanggal mulai belum diisi.');
            return;
        }

        $this->isFetching = true;
        $this->resetHasil();

        $hariMap = [
            'Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU',
        ];

        $start = Carbon::parse($this->startDate, config('app.timezone'));

        for ($i = 0; $i < 7; $i++) {
            $tgl = $start->copy()->addDays($i)->format('Y-m-d');
            $hari = $hariMap[$start->copy()->addDays($i)->dayName] ?? '?';

            $this->tanggalKolom[] = $tgl;
            $this->hariLabel[]    = $hari;

            $result = self::ref_jadwal_dokter($this->kdPoliBpjs, $tgl);

            $this->progressLog[] = sprintf(
                '[%s %s] %s — %s',
                $hari,
                Carbon::parse($tgl)->format('d/m'),
                $result['ok'] ? "OK ({$result['code']})" : "GAGAL ({$result['code']})",
                $result['msg'] ?: '-'
            );

            if (!$result['ok']) continue;

            foreach ($result['list'] as $row) {
                $kodedokter = (string) ($row['kodedokter'] ?? '');
                $namadokter = (string) ($row['namadokter'] ?? '-');
                if (!$kodedokter) continue;

                if (!isset($this->jadwal[$kodedokter])) {
                    $this->jadwal[$kodedokter] = [
                        'namadokter' => $namadokter,
                        'days'       => [],
                    ];
                }
                $this->jadwal[$kodedokter]['days'][$tgl] = [
                    'jampraktek' => (string) ($row['jampraktek'] ?? ''),
                    'kapasitas'  => (int) ($row['kapasitas'] ?? 0),
                ];
            }
        }

        // Sort dokter by name
        uasort($this->jadwal, fn ($a, $b) => strcmp($a['namadokter'], $b['namadokter']));

        $this->isFetching = false;
        $this->hasResult = true;

        $totalDokter = count($this->jadwal);
        $this->dispatch('toast', type: 'success', message: "Selesai. Ditemukan {$totalDokter} dokter dalam 7 hari.");
    }

    /* ===============================
     | APPLY ke SCMST_SCPOLIS
     |
     | UPSERT key composite: (DAY_ID, POLI_ID, DR_ID, MULAI_PRAKTEK, SELESAI_PRAKTEK).
     | Skip dokter yang KD_DR_BPJS-nya gak match RSMST_DOCTORS (active_status='1').
     |
     | Hari → DAY_ID dari hariLabel ("SENIN"..."MINGGU"), bukan dari tanggal langsung
     | (BPJS bisa kembalikan jadwal sama untuk 2 tanggal di rentang 7 hari kalau
     |  rentang melewati 2 hari minggu yang sama — kita merge ke 1 row per hari).
     =============================== */
    public function applyKeSiklik(): void
    {
        if (!$this->hasResult || empty($this->jadwal)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada hasil fetch. Klik "Ambil Jadwal" dulu.');
            return;
        }
        if (empty($this->poliId) || empty($this->kdPoliBpjs)) {
            $this->dispatch('toast', type: 'error', message: 'Poli belum dipilih.');
            return;
        }

        $this->isApplying = true;
        $this->applyLog = [];

        $dayIdMap = [
            'SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4,
            'JUMAT' => 5, 'SABTU' => 6, 'MINGGU' => 7,
        ];

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($this->jadwal as $kodeBpjs => $dr) {
            // Resolve dr_id via KD_DR_BPJS (active only)
            $doctor = DB::table('rsmst_doctors')
                ->select('dr_id', 'dr_name')
                ->where('kd_dr_bpjs', (string) $kodeBpjs)
                ->where('active_status', '1')
                ->first();

            if (!$doctor) {
                $skipCount = count($dr['days']);
                $skipped += $skipCount;
                $this->applyLog[] = "[SKIP] Dr. {$dr['namadokter']} (BPJS {$kodeBpjs}) — tidak ada/non-aktif di RSMST_DOCTORS ({$skipCount} jadwal)";
                continue;
            }

            $perDokterIns = 0;
            $perDokterUpd = 0;
            foreach ($dr['days'] as $tgl => $cell) {
                $idx  = array_search($tgl, $this->tanggalKolom);
                $hari = $idx !== false ? ($this->hariLabel[$idx] ?? null) : null;
                $dayId = $dayIdMap[$hari] ?? null;
                if (!$dayId) {
                    $skipped++;
                    continue;
                }

                $parts = explode('-', $cell['jampraktek']);
                if (count($parts) !== 2) {
                    $skipped++;
                    $this->applyLog[] = "[SKIP] {$doctor->dr_name} {$hari}: format jampraktek invalid '{$cell['jampraktek']}'";
                    continue;
                }
                $mulai   = trim($parts[0]) . ':00';
                $selesai = trim($parts[1]) . ':00';

                // Shift dari RSTXN_SHIFTCTLS (fallback 1)
                $shiftRow = DB::table('rstxn_shiftctls')
                    ->whereRaw('? BETWEEN shift_start AND shift_end', [$mulai])
                    ->first();
                $shift = (int) ($shiftRow->shift ?? ($mulai < '14:00:00' ? 1 : 2));

                try {
                    $exists = DB::table('scmst_scpolis')
                        ->where('day_id', $dayId)
                        ->where('poli_id', (int) $this->poliId)
                        ->where('dr_id', $doctor->dr_id)
                        ->where('mulai_praktek', $mulai)
                        ->where('selesai_praktek', $selesai)
                        ->exists();

                    if ($exists) {
                        DB::table('scmst_scpolis')
                            ->where('day_id', $dayId)
                            ->where('poli_id', (int) $this->poliId)
                            ->where('dr_id', $doctor->dr_id)
                            ->where('mulai_praktek', $mulai)
                            ->where('selesai_praktek', $selesai)
                            ->update([
                                'sc_poli_status_' => '1',
                                'sc_poli_ket'     => $cell['jampraktek'],
                                'kuota'           => $cell['kapasitas'],
                                'shift'           => $shift,
                            ]);
                        $updated++;
                        $perDokterUpd++;
                    } else {
                        // NO_URUT increment per (DAY_ID, POLI_ID, DR_ID)
                        $maxNoUrut = (int) DB::table('scmst_scpolis')
                            ->where('day_id', $dayId)
                            ->where('poli_id', (int) $this->poliId)
                            ->where('dr_id', $doctor->dr_id)
                            ->max('no_urut');

                        DB::table('scmst_scpolis')->insert([
                            'sc_poli_status_'      => '1',
                            'sc_poli_ket'          => $cell['jampraktek'],
                            'day_id'               => $dayId,
                            'poli_id'              => (int) $this->poliId,
                            'dr_id'                => $doctor->dr_id,
                            'shift'                => $shift,
                            'mulai_praktek'        => $mulai,
                            'pelayanan_perp_asien' => null,
                            'no_urut'              => $maxNoUrut + 1,
                            'kuota'                => $cell['kapasitas'],
                            'selesai_praktek'      => $selesai,
                        ]);
                        $inserted++;
                        $perDokterIns++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->applyLog[] = "[ERROR] {$doctor->dr_name} {$hari} {$mulai}-{$selesai}: " . $e->getMessage();
                }
            }

            if ($perDokterIns + $perDokterUpd > 0) {
                $this->applyLog[] = "[OK] {$doctor->dr_name} → INSERT: {$perDokterIns}, UPDATE: {$perDokterUpd}";
            }
        }

        $this->applyResult = "INSERT: {$inserted}  ·  UPDATE: {$updated}  ·  SKIP: {$skipped}  ·  ERROR: {$errors}";
        $this->isApplying = false;
        unset($this->localJadwal);  // refresh tabel lokal supaya nampilin data baru

        $this->dispatch(
            'toast',
            type: $errors > 0 ? 'warning' : 'success',
            message: 'Apply selesai — ' . $this->applyResult
        );
    }
};
?>

<div>
    {{-- ═══════════ HEADER ═══════════ --}}
    <x-page-title
        title="Jadwal Mingguan Dokter (BPJS)"
        subtitle="Ambil jadwal dokter per poli dari BPJS Antrean RS untuk 7 hari ke depan." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">

            {{-- ═══════════ CARA PAKAI ═══════════ --}}
            <div x-data="{ open: false }"
                class="border border-blue-200 dark:border-blue-900/50 bg-blue-50/60 dark:bg-blue-900/20 rounded-2xl">

                <button type="button" @click="open = !open"
                    class="flex items-center justify-between w-full px-4 py-3 text-left">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-semibold text-blue-900 dark:text-blue-200">Cara Pakai</span>
                    </div>
                    <svg class="w-4 h-4 text-blue-700 dark:text-blue-300 transition-transform"
                        :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-collapse class="px-4 pb-4 text-sm text-blue-900/90 dark:text-blue-100/90 space-y-3">

                    <p class="text-blue-900/80 dark:text-blue-100/80">
                        Halaman ini ambil <span class="font-semibold">jadwal dokter dari BPJS</span> untuk 7 hari ke depan,
                        lalu disimpan ke sistem klinik. Setelah disimpan, jadwal otomatis dipakai saat petugas
                        checkin pasien di halaman <span class="font-semibold">Booking RJ</span>.
                    </p>

                    <div>
                        <p class="font-semibold mb-1">Cara pakai</p>
                        <ol class="list-decimal list-inside space-y-0.5 text-blue-900/80 dark:text-blue-100/80">
                            <li>Pilih <span class="font-semibold">Poli</span></li>
                            <li>Pilih <span class="font-semibold">Tanggal Mulai</span> (default hari ini)</li>
                            <li>Klik <span class="font-semibold">Ambil Jadwal 7 Hari</span> — tunggu beberapa detik</li>
                            <li>Lihat tabel hasil (baris = dokter, kolom = hari Senin–Minggu)</li>
                            <li>Klik <span class="font-semibold">Apply ke SCMST_SCPOLIS</span> untuk menyimpan</li>
                        </ol>
                    </div>

                    <div>
                        <p class="font-semibold mb-1">Kalau hasilnya kosong atau banyak yang ter-SKIP</p>
                        <ul class="list-disc list-inside space-y-0.5 text-blue-900/80 dark:text-blue-100/80">
                            <li>Pastikan <span class="font-semibold">Poli</span> yang dipilih sudah punya kode BPJS (atur di menu <span class="font-semibold">Master Poli</span>)</li>
                            <li>Pastikan <span class="font-semibold">Dokter</span> sudah punya kode BPJS dan masih aktif (atur di menu <span class="font-semibold">Master Dokter</span>)</li>
                            <li>Pastikan klinik & dokter sudah terdaftar di BPJS HFIS / Aplicares</li>
                        </ul>
                    </div>

                    <div>
                        <p class="font-semibold mb-1">Catatan</p>
                        <ul class="list-disc list-inside space-y-0.5 text-blue-900/80 dark:text-blue-100/80">
                            <li>Aman di-klik berulang — jadwal yang sudah ada otomatis diperbarui, tidak dobel</li>
                            <li>Kalau muncul pesan gagal koneksi atau kode error — hubungi admin IT</li>
                        </ul>
                    </div>

                </div>
            </div>

            {{-- ═══════════ FORM ═══════════ --}}
            <div class="p-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- LOV Poli --}}
                    <div class="w-full sm:w-80">
                        @if (empty($poliId))
                            <livewire:lov.poli.lov-poli target="jadwal-mingguan-poli" label="Pilih Poli (BPJS)"
                                placeholder="Ketik nama poli..." wire:key="lov-poli-jadwal-{{ $poliId }}" />
                        @else
                            <x-input-label value="Poli" />
                            <div class="flex gap-2 items-center mt-1">
                                <x-text-input :value="$poliDesc . ($kdPoliBpjs ? ' [BPJS: ' . $kdPoliBpjs . ']' : ' [tanpa kode BPJS]')"
                                    disabled class="flex-1" />
                                <button type="button" wire:click="clearPoli"
                                    class="shrink-0 px-2 py-2 text-gray-400 hover:text-red-500 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Start Date --}}
                    <div class="w-full sm:w-48">
                        <x-input-label value="Tanggal Mulai" />
                        <x-text-input type="date" wire:model="startDate" class="block w-full mt-1" />
                    </div>

                    {{-- Submit --}}
                    <div class="ml-auto">
                        <button type="button" wire:click="fetchJadwal" wire:loading.attr="disabled"
                            wire:target="fetchJadwal"
                            @class([
                                'inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition',
                                'bg-brand-green text-white hover:bg-brand-green/90' => !empty($kdPoliBpjs),
                                'bg-gray-300 text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-400' => empty($kdPoliBpjs),
                            ])
                            @disabled(empty($kdPoliBpjs))>
                            <span wire:loading.remove wire:target="fetchJadwal">
                                Ambil Jadwal 7 Hari
                            </span>
                            <span wire:loading wire:target="fetchJadwal" class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                </svg>
                                Mengambil dari BPJS...
                            </span>
                        </button>
                    </div>
                </div>

                @if (empty($kdPoliBpjs) && !empty($poliId))
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                        Poli ini belum punya kode BPJS — set dulu di Master Poli.
                    </p>
                @endif
            </div>

            {{-- ═══════════ JADWAL LOKAL (SCMST_SCPOLIS) ═══════════ --}}
            @if (!empty($poliId))
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Jadwal Lokal — {{ $poliDesc }}
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Data dari SCMST_SCPOLIS (yang dipakai validasi quota saat checkin)
                            </span>
                        </div>
                        <span class="text-xs font-mono text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                            {{ count($this->localJadwal) }} dokter
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border-collapse">
                            <thead>
                                <tr class="text-xs font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-gray-50 dark:bg-gray-800">
                                    <th class="px-3 py-2 sticky left-0 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 min-w-[240px]">
                                        Dokter
                                    </th>
                                    @foreach (['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'] as $h)
                                        <th class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 min-w-[140px]">
                                            {{ $h }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->localJadwal as $dr)
                                    <tr class="bg-white dark:bg-gray-900 hover:bg-emerald-50/40 dark:hover:bg-gray-800 transition">
                                        <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 align-top">
                                            <div class="font-semibold text-brand dark:text-white">
                                                {{ $dr['dr_name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                {{ $dr['dr_id'] }}
                                                @if (!empty($dr['kd_dr_bpjs']))
                                                    · BPJS: {{ $dr['kd_dr_bpjs'] }}
                                                @endif
                                            </div>
                                        </td>
                                        @for ($d = 1; $d <= 7; $d++)
                                            @php $slots = $dr['days'][$d] ?? []; @endphp
                                            <td class="px-3 py-2 border-b border-gray-100 dark:border-gray-800 align-top">
                                                @forelse ($slots as $slot)
                                                    <div class="font-mono text-xs text-gray-800 dark:text-gray-200">
                                                        {{ $slot['ket'] }}
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mb-1">
                                                        Kuota: {{ $slot['kuota'] }}
                                                    </div>
                                                @empty
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endforelse
                                            </td>
                                        @endfor
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                            Belum ada jadwal lokal untuk poli ini. Klik <span class="font-semibold">Ambil Jadwal 7 Hari</span> dari BPJS lalu Apply, atau input manual via SQL.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ═══════════ HASIL FETCH BPJS ═══════════ --}}
            @if ($hasResult)
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Hasil Fetch dari BPJS — {{ $poliDesc }}
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ count($jadwal) }} dokter · {{ count($tanggalKolom) }} hari (data dari server BPJS, belum disimpan)
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            @if (!empty($applyResult))
                                <span class="text-xs font-mono text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                    {{ $applyResult }}
                                </span>
                            @endif

                            <button type="button"
                                wire:click="applyKeSiklik"
                                wire:loading.attr="disabled"
                                wire:target="applyKeSiklik"
                                wire:confirm="Yakin apply hasil ini ke SCMST_SCPOLIS? Data existing akan di-UPDATE, baru di-INSERT, dokter tanpa kd_dr_bpjs di-SKIP."
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                                       bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 transition">
                                <span wire:loading.remove wire:target="applyKeSiklik" class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Apply ke SCMST_SCPOLIS
                                </span>
                                <span wire:loading wire:target="applyKeSiklik" class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                    </svg>
                                    Menyimpan...
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border-collapse">
                            <thead>
                                <tr class="text-xs font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-gray-50 dark:bg-gray-800">
                                    <th class="px-3 py-2 sticky left-0 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 min-w-[240px]">
                                        Dokter
                                    </th>
                                    @foreach ($tanggalKolom as $i => $tgl)
                                        <th class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 min-w-[140px]">
                                            <div class="font-bold">{{ $hariLabel[$i] }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 font-normal">
                                                {{ Carbon::parse($tgl)->format('d/m/Y') }}
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($jadwal as $kode => $dr)
                                    <tr class="bg-white dark:bg-gray-900 hover:bg-green-50 dark:hover:bg-gray-800 transition">
                                        <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 align-top">
                                            <div class="font-semibold text-brand dark:text-white">
                                                {{ $dr['namadokter'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                {{ $kode }}
                                            </div>
                                        </td>
                                        @foreach ($tanggalKolom as $tgl)
                                            @php $cell = $dr['days'][$tgl] ?? null; @endphp
                                            <td class="px-3 py-2 border-b border-gray-100 dark:border-gray-800 align-top">
                                                @if ($cell)
                                                    <div class="font-mono text-xs text-gray-800 dark:text-gray-200">
                                                        {{ $cell['jampraktek'] }}
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                        Kuota: {{ $cell['kapasitas'] }}
                                                    </div>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tanggalKolom) + 1 }}"
                                            class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada dokter dengan jadwal di poli ini selama 7 hari.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ═══════════ PROGRESS LOG (fetch BPJS) ═══════════ --}}
            @if (!empty($progressLog))
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Log Pengambilan Data (BPJS)
                        </h3>
                    </div>
                    <div class="px-4 py-3 max-h-48 overflow-y-auto">
                        <ul class="space-y-1 text-xs font-mono text-gray-700 dark:text-gray-300">
                            @foreach ($progressLog as $line)
                                <li @class([
                                    'text-red-600 dark:text-red-400' => str_contains($line, 'GAGAL'),
                                    'text-emerald-700 dark:text-emerald-400' => str_contains($line, 'OK'),
                                ])>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- ═══════════ APPLY LOG (UPSERT ke SCMST_SCPOLIS) ═══════════ --}}
            @if (!empty($applyLog))
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Log Apply ke SCMST_SCPOLIS
                        </h3>
                        @if (!empty($applyResult))
                            <span class="text-xs font-mono text-gray-600 dark:text-gray-400">
                                {{ $applyResult }}
                            </span>
                        @endif
                    </div>
                    <div class="px-4 py-3 max-h-64 overflow-y-auto">
                        <ul class="space-y-1 text-xs font-mono text-gray-700 dark:text-gray-300">
                            @foreach ($applyLog as $line)
                                <li @class([
                                    'text-red-600 dark:text-red-400'         => str_contains($line, '[ERROR]'),
                                    'text-amber-600 dark:text-amber-400'     => str_contains($line, '[SKIP]'),
                                    'text-emerald-700 dark:text-emerald-400' => str_contains($line, '[OK]'),
                                ])>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
