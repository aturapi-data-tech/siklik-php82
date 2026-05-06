<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\AntrianTrait;

/**
 * Booking RJ — admin/loket UI untuk manage Mobile JKN bookings.
 *
 * Adaptasi dari sirus-php82, drop:
 *   - AntrianTrait (outbound BPJS push) — siklik klinik pratama murni inbound
 *   - EmrRJTrait (datadaftarpolirj_json management) — bisa null dulu
 *   - cekStatusBpjs() + modal-nya — depend on outbound trait
 *
 * Flow:
 *   1. BPJS Mobile JKN push booking → table REFERENSI_MOBILEJKN_BPJS (status='Belum')
 *   2. Petugas loket buka /rawat-jalan/booking → lihat list, filter, search
 *   3. Saat pasien datang: klik Checkin → insert RSTXN_RJHDRS + update status='Checkin'
 *   4. Atau: klik Batal → update status='Batal' + keterangan
 */
new class extends Component {
    use WithPagination, WithRenderVersioningTrait, AntrianTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['booking-rj-toolbar'];

    /* ─── Filter ─── */
    public string $filterMode = 'tanggal'; // 'semua' | 'tanggal' | 'bulan'
    public string $filterBulan = '';
    public string $filterTanggal = '';
    public string $filterStatus = '';
    public string $filterDrId = '';
    public string $filterDrName = '';
    public string $filterKdDrBpjs = '';
    public string $searchKeyword = '';
    public int $itemsPerPage = 100;

    /* ─── Status options ─── */
    public array $statusOptions = [
        ['id' => '',        'label' => 'Semua'],
        ['id' => 'Belum',   'label' => 'Belum'],
        ['id' => 'Checkin', 'label' => 'Checkin'],
        ['id' => 'Batal',   'label' => 'Batal'],
    ];

    /* ─── Batal modal ─── */
    public string $batalNobooking = '';
    public string $batalKeterangan = '';

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
        $this->filterTanggal = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    /* ─── Reset page on filter change ─── */
    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterMode(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }
    public function updatedFilterTanggal(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    /* ===============================
     | LOV DOKTER
     =============================== */
    #[On('lov.selected.booking-rj-dokter')]
    public function onDokterSelected(?array $payload): void
    {
        if (!$payload) {
            $this->filterDrId = '';
            $this->filterDrName = '';
            $this->filterKdDrBpjs = '';
        } else {
            $this->filterDrId = $payload['dr_id'] ?? '';
            $this->filterDrName = $payload['dr_name'] ?? '';
            $this->filterKdDrBpjs = $payload['kd_dr_bpjs'] ?? '';
        }
        $this->resetPage();
    }

    public function clearDokter(): void
    {
        $this->filterDrId = '';
        $this->filterDrName = '';
        $this->filterKdDrBpjs = '';
        $this->resetPage();
        $this->incrementVersion('booking-rj-toolbar');
    }

    /* ===============================
     | RESET FILTERS
     =============================== */
    public function resetFilters(): void
    {
        $this->filterMode = 'tanggal';
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
        $this->filterTanggal = Carbon::now(config('app.timezone'))->format('d/m/Y');
        $this->filterStatus = '';
        $this->filterDrId = '';
        $this->filterDrName = '';
        $this->filterKdDrBpjs = '';
        $this->searchKeyword = '';
        $this->resetPage();
        $this->incrementVersion('booking-rj-toolbar');
    }

    /* ===============================
     | SET STATUS — manual override (Belum / Checkin / Batal)
     =============================== */
    public function setStatus(string $nobooking, string $status): void
    {
        DB::table('referensi_mobilejkn_bpjs')
            ->where('nobooking', $nobooking)
            ->update(['status' => $status]);

        $this->dispatch('toast', type: 'success', message: "Status booking diubah ke {$status}.");
        unset($this->bookingData);
    }

    /* ===============================
     | CHECKIN — insert RSTXN_RJHDRS + update status booking
     |
     | Flow:
     |   1. Validasi tanggal hari ini, status belum
     |   2. Validasi waktu checkin (1 jam sebelum mulai s/d selesai pelayanan)
     |   3. Cek jadwal dokter di SCVIEW_SCPOLIS (quota check)
     |   4. Cek pendaftar via RSVIEW_RJKASIR (sisa quota)
     |   5. Idempotency: kalau RSTXN_RJHDRS already exists, skip
     |   6. Atomic transaction: insert RJHDRS + update referensi
     =============================== */
    public function prosesCheckin(string $nobooking): void
    {
        $now = Carbon::now(config('app.timezone'));

        $row = DB::table('referensi_mobilejkn_bpjs')->where('nobooking', $nobooking)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data booking tidak ditemukan.');
            return;
        }
        if ($row->status === 'Batal') {
            $this->dispatch('toast', type: 'error', message: 'Antrian telah dibatalkan sebelumnya.');
            return;
        }
        if ($row->status === 'Checkin') {
            $this->dispatch('toast', type: 'error', message: 'Sudah checkin pada ' . $row->validasi);
            return;
        }
        if (!Carbon::parse($row->tanggalperiksa)->isToday()) {
            $this->dispatch('toast', type: 'error', message: "Tanggal periksa bukan hari ini, tetapi tgl {$row->tanggalperiksa}");
            return;
        }

        // Parse jampraktek "HH:mm-HH:mm"
        [$jammulai, $jamselesai] = explode('-', $row->jampraktek);
        $jammulai = trim($jammulai);
        $jamselesai = trim($jamselesai);

        $tanggalperiksaMulai   = $row->tanggalperiksa . ' ' . $jammulai . ':00';
        $tanggalperiksaSelesai = $row->tanggalperiksa . ' ' . $jamselesai . ':00';

        $startCheckinAllowed = Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksaMulai, config('app.timezone'))->subHour();
        $endCheckinAllowed   = Carbon::createFromFormat('Y-m-d H:i:s', $tanggalperiksaSelesai, config('app.timezone'));

        if ($now->lt($startCheckinAllowed)) {
            $this->dispatch('toast', type: 'error', message: "Checkin minimal 1 jam sebelum pelayanan. Pelayanan dimulai pada: {$tanggalperiksaMulai}");
            return;
        }
        if ($now->gt($endCheckinAllowed)) {
            $this->dispatch('toast', type: 'error', message: "Checkin sudah expired, pelayanan telah berakhir pada: {$tanggalperiksaSelesai}");
            return;
        }

        // Hari Indonesia untuk join SCVIEW_SCPOLIS
        $hariMap = [
            'Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU',
        ];
        $hari = $hariMap[Carbon::parse($row->tanggalperiksa)->dayName] ?? strtoupper(Carbon::parse($row->tanggalperiksa)->dayName);

        // Cek jadwal & quota
        $cekQuota = DB::table('scview_scpolis')
            ->select('kuota', 'mulai_praktek', 'selesai_praktek', 'poli_id', 'dr_id', 'poli_desc', 'dr_name', 'shift')
            ->where('kd_poli_bpjs', $row->kodepoli)
            ->where('kd_dr_bpjs', $row->kodedokter)
            ->where('day_desc', $hari)
            ->where('mulai_praktek', $jammulai . ':00')
            ->where('selesai_praktek', $jamselesai . ':00')
            ->first();

        if (!$cekQuota) {
            $this->dispatch('toast', type: 'error', message: 'Jadwal dokter di poli tersebut tidak ditemukan.');
            return;
        }

        $cekDaftar = DB::table('rsview_rjkasir')
            ->where('kd_poli_bpjs', $row->kodepoli)
            ->where('kd_dr_bpjs', $row->kodedokter)
            ->where('rj_status', '!=', 'F')
            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), Carbon::parse($row->tanggalperiksa)->format('d/m/Y'))
            ->count();

        if ($cekQuota->kuota - $cekDaftar <= 0) {
            $this->dispatch('toast', type: 'error', message: "Quota Poli {$cekQuota->poli_desc} Dokter {$cekQuota->dr_name} penuh.");
            return;
        }

        // Idempotency: cegah double-checkin
        $existingRj = DB::table('rstxn_rjhdrs')->where('nobooking', $nobooking)->first();
        if ($existingRj) {
            $this->dispatch('toast', type: 'warning', message: "Booking sudah pernah di-checkin (rj_no: {$existingRj->rj_no}).");
            if ($row->status !== 'Checkin') {
                DB::table('referensi_mobilejkn_bpjs')->where('nobooking', $nobooking)->update([
                    'status'   => 'Checkin',
                    'validasi' => $now->format('Y-m-d H:i:s'),
                ]);
                unset($this->bookingData);
            }
            return;
        }

        $noAntrian    = (int) $row->angkaantrean;
        $nomorAntrean = (string) $row->nomorantrean;
        $rjDateStr    = $now->format('Y-m-d H:i:s');

        // Shift dari RSTXN_SHIFTCTLS berdasarkan jam realtime
        $shiftRow = DB::table('rstxn_shiftctls')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();
        $shift = (string) ($shiftRow->shift ?? $cekQuota->shift);

        try {
            DB::transaction(function () use ($nobooking, $row, $cekQuota, $noAntrian, $nomorAntrean, $rjDateStr, $shift, $now) {
                // Re-check status di dalam transaction (race-safe)
                $freshRow = DB::table('referensi_mobilejkn_bpjs')
                    ->where('nobooking', $nobooking)
                    ->lockForUpdate()
                    ->first();
                if (!$freshRow || $freshRow->status !== 'Belum') {
                    throw new \RuntimeException('Status booking sudah berubah (mungkin sudah di-checkin atau dibatalkan oleh proses lain).');
                }

                if (DB::table('rstxn_rjhdrs')->where('nobooking', $nobooking)->exists()) {
                    throw new \RuntimeException('Booking sudah di-checkin oleh proses lain.');
                }

                $rjNoNew = DB::table('rstxn_rjhdrs')->selectRaw('nvl(max(rj_no) + 1, 1) as rjno_max')->value('rjno_max');

                // Catatan: kolom siklik beda dari sirus — kunjungan_internal_status
                // tidak ada di RSTXN_RJHDRS siklik (per memory schema).
                DB::table('rstxn_rjhdrs')->insert([
                    'rj_no'                 => $rjNoNew,
                    'rj_date'               => DB::raw("to_date('" . $rjDateStr . "', 'yyyy-mm-dd hh24:mi:ss')"),
                    'reg_no'                => strtoupper($row->norm),
                    'nobooking'             => $nobooking,
                    'no_antrian'            => $noAntrian,
                    'klaim_id'              => 'JM',
                    'poli_id'               => $cekQuota->poli_id,
                    'dr_id'                 => $cekQuota->dr_id,
                    'shift'                 => $shift,
                    'txn_status'            => 'A',
                    'rj_status'             => 'A',
                    'erm_status'            => 'A',
                    'pass_status'           => 'O',
                    'cek_lab'               => '0',
                    'sl_codefrom'           => '02',
                    'waktu_masuk_pelayanan' => DB::raw("to_date('" . $rjDateStr . "', 'yyyy-mm-dd hh24:mi:ss')"),
                ]);

                DB::table('referensi_mobilejkn_bpjs')
                    ->where('nobooking', $nobooking)
                    ->update([
                        'status'       => 'Checkin',
                        'validasi'     => $now->format('Y-m-d H:i:s'),
                        'nomorantrean' => $nomorAntrean,
                        'angkaantrean' => $noAntrian,
                    ]);
            });

            // ── Push outbound BPJS panggil (status hadir) ──
            // tambah_antrean udah otomatis saat Mobile JKN push ke /api/antrean
            // (Mobile JKN itself adalah tambah_antrean). Di sini pasien sudah
            // hadir di klinik, jadi cuma update status panggil = Hadir.
            $bpjsMsg = '';
            if (!empty($row->nomorkartu)) {
                $panggil = self::panggil_antrean(
                    $row->tanggalperiksa,
                    (string) $row->kodepoli,
                    (string) $row->nomorkartu,
                    1,                      // 1=Hadir
                    $now->valueOf(),        // ms timestamp
                );
                if (!$panggil['ok']) {
                    $bpjsMsg = " · BPJS panggil: {$panggil['code']}/{$panggil['msg']}";
                }
            }

            $this->dispatch('toast',
                type: $bpjsMsg ? 'warning' : 'success',
                message: "Checkin berhasil. Antrian: {$nomorAntrean}" . $bpjsMsg
            );
            unset($this->bookingData);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal checkin: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL — local update (tanpa push BPJS outbound)
     =============================== */
    public function openBatalModal(string $nobooking): void
    {
        $this->batalNobooking = $nobooking;
        $this->batalKeterangan = '';
        $this->dispatch('open-modal', name: 'batal-booking');
    }

    public function konfirmasiBatal(): void
    {
        $this->validate(['batalKeterangan' => 'required|min:3'], [], ['batalKeterangan' => 'Keterangan Batal']);

        try {
            DB::table('referensi_mobilejkn_bpjs')
                ->where('nobooking', $this->batalNobooking)
                ->update([
                    'status'           => 'Batal',
                    'keterangan_batal' => $this->batalKeterangan,
                ]);

            $this->dispatch('close-modal', name: 'batal-booking');
            $this->dispatch('toast', type: 'success', message: "Booking {$this->batalNobooking} berhasil dibatalkan.");
            $this->batalNobooking = '';
            $this->batalKeterangan = '';
            unset($this->bookingData);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /* ===============================
     | QUERY (Computed)
     =============================== */
    #[Computed]
    public function bookingData()
    {
        $query = DB::table('referensi_mobilejkn_bpjs as b')
            ->join('rsmst_pasiens as p', DB::raw('UPPER(b.norm)'), '=', 'p.reg_no')
            ->select([
                'b.nobooking',
                'b.norm',
                'b.nomorkartu',
                'b.nik',
                'b.nohp',
                'b.kodepoli',
                DB::raw('(SELECT poli_desc FROM rsmst_polis WHERE kd_poli_bpjs = b.kodepoli AND ROWNUM = 1) AS poli_desc'),
                'b.pasienbaru',
                'b.kodedokter',
                DB::raw('(SELECT dr_name FROM rsmst_doctors WHERE kd_dr_bpjs = b.kodedokter AND ROWNUM = 1) AS dr_name'),
                DB::raw("TO_CHAR(TO_DATE(b.tanggalperiksa,'yyyy-mm-dd'),'dd/mm/yyyy') AS tanggalperiksa"),
                'b.jampraktek',
                'b.jeniskunjungan',
                'b.nomorreferensi',
                'b.nomorantrean',
                'b.angkaantrean',
                'b.estimasidilayani',
                'b.sisakuotajkn',
                'b.kuotajkn',
                'b.sisakuotanonjkn',
                'b.kuotanonjkn',
                'b.status',
                'b.validasi',
                'b.keterangan_batal',
                DB::raw("TO_CHAR(TO_DATE(b.tanggalbooking,'yyyy-mm-dd hh24:mi:ss'),'dd/mm/yyyy hh24:mi') AS tanggalbooking"),
                'b.daftardariapp',
                'p.reg_name',
                'p.address',
            ])
            ->whereIn(DB::raw('b.ROWID'), function ($sub) {
                $sub->from('referensi_mobilejkn_bpjs')->selectRaw('MIN(ROWID)')->groupBy('nobooking');
            });

        if (!empty($this->filterStatus)) {
            $query->where('b.status', $this->filterStatus);
        }

        if ($this->filterMode === 'bulan' && !empty($this->filterBulan)) {
            $query->where(DB::raw("TO_CHAR(TO_DATE(b.tanggalperiksa,'yyyy-mm-dd'),'mm/yyyy')"), $this->filterBulan);
        } elseif ($this->filterMode === 'tanggal' && !empty($this->filterTanggal)) {
            $query->where(DB::raw("TO_CHAR(TO_DATE(b.tanggalperiksa,'yyyy-mm-dd'),'dd/mm/yyyy')"), $this->filterTanggal);
        }

        if (!empty($this->filterKdDrBpjs)) {
            $query->where('b.kodedokter', $this->filterKdDrBpjs);
        }

        if (!empty($this->searchKeyword)) {
            $kw = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($kw) {
                $q->where(DB::raw('UPPER(b.nobooking)'), 'LIKE', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(b.norm)'), 'LIKE', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(b.nik)'), 'LIKE', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'LIKE', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(b.nomorkartu)'), 'LIKE', "%{$kw}%");
            });
        }

        $query->orderBy('b.tanggalperiksa', 'asc')
            ->orderBy('b.kodedokter', 'asc')
            ->orderBy('b.tanggalbooking', 'asc');

        return $query->paginate($this->itemsPerPage);
    }
};
?>

<div>
    {{-- ═══════════ PAGE HEADER ═══════════ --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Booking Rawat Jalan
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-400">
                Daftar pasien booking layanan rawat jalan via Mobile JKN
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- ═══════════ TOOLBAR ═══════════ --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700"
                wire:key="{{ $this->renderKey('booking-rj-toolbar', []) }}">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- Pencarian --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No Booking / No MR / NIK / Nama / No Kartu..." />
                        </div>
                    </div>

                    {{-- Mode Filter --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Filter" />
                        <x-select-input wire:model.live="filterMode" class="w-full mt-1 sm:w-32">
                            <option value="semua">Semua</option>
                            <option value="tanggal">Per Tanggal</option>
                            <option value="bulan">Per Bulan</option>
                        </x-select-input>
                    </div>

                    @if ($filterMode === 'bulan')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan Periksa" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input wire:model.live="filterBulan" class="block w-full pl-10 sm:w-44" placeholder="mm/yyyy" />
                            </div>
                        </div>
                    @elseif ($filterMode === 'tanggal')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Tanggal Periksa" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input wire:model.live="filterTanggal" class="block w-full pl-10 sm:w-44" placeholder="dd/mm/yyyy" />
                            </div>
                        </div>
                    @endif

                    {{-- Status --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            @foreach ($statusOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- Filter Dokter --}}
                    <div class="w-full sm:w-auto">
                        <div class="w-72">
                            @if (empty($filterDrId))
                                <livewire:lov.dokter.lov-dokter target="booking-rj-dokter" label="Filter Dokter"
                                    placeholder="Ketik nama dokter..."
                                    wire:key="lov-dokter-booking-{{ $renderVersions['booking-rj-toolbar'] ?? 0 }}" />
                            @else
                                <div>
                                    <x-input-label value="Filter Dokter" />
                                    <div class="flex gap-2 items-center mt-1">
                                        <x-text-input :value="$filterDrName" disabled class="flex-1" />
                                        <button type="button" wire:click="clearDokter"
                                            class="shrink-0 px-2 py-2 text-gray-400 hover:text-red-500 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </x-secondary-button>

                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                @foreach ([100, 200, 500, 1000] as $n)
                                    <option value="{{ $n }}">{{ $n }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ═══════════ TABLE ═══════════ --}}
            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm border-collapse">

                        <thead>
                            <tr class="text-xs font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="sticky top-0 z-30 px-3 py-2 w-14 text-center bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">No</th>
                                <th class="sticky top-0 z-30 px-3 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">Pasien</th>
                                <th class="sticky top-0 z-30 px-3 py-2 w-32 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">Jam</th>
                                <th class="sticky top-0 z-30 px-3 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">Booking</th>
                                <th class="sticky top-0 z-30 px-3 py-2 text-center w-56 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                $prevDate = null;
                                $prevDokter = null;
                            @endphp
                            @forelse ($this->bookingData as $i => $row)
                                @php
                                    $statusVariant = match ($row->status) {
                                        'Checkin' => 'success',
                                        'Batal'   => 'danger',
                                        default   => 'gray',
                                    };
                                    $dateChanged = $prevDate !== $row->tanggalperiksa;
                                    $dokterChanged = $dateChanged || $prevDokter !== $row->kodedokter;
                                @endphp

                                @if ($dateChanged)
                                    <tr>
                                        <td colspan="5"
                                            class="sticky top-[34px] z-20 px-3 py-1.5 text-sm font-bold text-gray-800 dark:text-gray-100 bg-gray-200 dark:bg-gray-700 border-y border-gray-300 dark:border-gray-600">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                {{ $row->tanggalperiksa }}
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @if ($dokterChanged)
                                    <tr>
                                        <td colspan="5"
                                            class="sticky top-[64px] z-10 px-3 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-200 bg-emerald-50 dark:bg-emerald-900/40 border-b border-emerald-200 dark:border-emerald-800">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ $row->dr_name ?? $row->kodedokter }}
                                                <span class="text-gray-500 dark:text-gray-400 font-normal">·
                                                    {{ $row->poli_desc ?? $row->kodepoli }}</span>
                                                @if (!empty($row->jampraktek))
                                                    <span class="text-gray-500 dark:text-gray-400 font-normal">·
                                                        {{ $row->jampraktek }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                <tr class="transition bg-white dark:bg-gray-900 hover:bg-green-50 dark:hover:bg-gray-800">

                                    {{-- NO ANTREAN --}}
                                    <td class="px-3 py-1.5 text-center align-middle">
                                        <div class="text-xl font-bold text-gray-700 dark:text-gray-200">
                                            {{ $row->angkaantrean ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- PASIEN --}}
                                    <td class="px-3 py-1.5 align-middle">
                                        <div class="font-semibold text-brand dark:text-white truncate">
                                            {{ $row->reg_name }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                            {{ $row->norm }}
                                            @if (!empty($row->nik))
                                                · {{ $row->nik }}
                                            @endif
                                            @if ($row->pasienbaru === '1')
                                                · <span class="text-amber-600 dark:text-amber-400 font-semibold">BARU</span>
                                            @endif
                                            @if ($row->jeniskunjungan == 1)
                                                · <span class="text-brand dark:text-emerald-400 font-semibold">RUJUKAN</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- JAM --}}
                                    <td class="px-3 py-1.5 align-middle text-xs text-gray-700 dark:text-gray-300">
                                        {{ $row->jampraktek }}
                                    </td>

                                    {{-- BOOKING --}}
                                    <td class="px-3 py-1.5 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row->nobooking }}</span>
                                            <x-badge :variant="$statusVariant">{{ $row->status }}</x-badge>
                                            @if (!empty($row->nomorantrean))
                                                <x-badge variant="alternative">{{ $row->nomorantrean }}</x-badge>
                                            @endif
                                            @if ($row->status === 'Batal' && !empty($row->keterangan_batal))
                                                <span class="text-xs text-red-500 dark:text-red-400 italic truncate max-w-[200px]">{{ $row->keterangan_batal }}</span>
                                            @endif
                                        </div>
                                        @if (!empty($row->tanggalbooking))
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                Daftar: <span class="font-mono">{{ $row->tanggalbooking }}</span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-3 py-1.5 align-middle">
                                        <div class="flex items-center justify-center gap-1">

                                            @if ($row->status !== 'Belum')
                                                <button type="button"
                                                    wire:click="setStatus('{{ $row->nobooking }}', 'Belum')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="setStatus('{{ $row->nobooking }}', 'Belum')"
                                                    title="Set Belum"
                                                    class="inline-flex items-center justify-center px-2 py-1 rounded-md text-xs font-medium
                                                           bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 transition">
                                                    Reset
                                                </button>
                                            @endif

                                            @if ($row->status === 'Belum')
                                                <button type="button"
                                                    wire:click="prosesCheckin('{{ $row->nobooking }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="prosesCheckin('{{ $row->nobooking }}')"
                                                    title="Checkin"
                                                    class="inline-flex items-center justify-center gap-1 px-2 py-1 rounded-md text-xs font-medium
                                                           bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition">
                                                    <span wire:loading.remove wire:target="prosesCheckin('{{ $row->nobooking }}')">Checkin</span>
                                                    <span wire:loading wire:target="prosesCheckin('{{ $row->nobooking }}')">...</span>
                                                </button>
                                            @endif

                                            @if ($row->status === 'Belum')
                                                <button type="button"
                                                    wire:click="openBatalModal('{{ $row->nobooking }}')"
                                                    title="Batal"
                                                    class="inline-flex items-center justify-center px-2 py-1 rounded-md text-xs font-medium
                                                           bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition">
                                                    Batal
                                                </button>
                                            @endif

                                        </div>
                                    </td>

                                </tr>
                                @php
                                    $prevDate = $row->tanggalperiksa;
                                    $prevDokter = $row->kodedokter;
                                @endphp
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data booking
                                        <span class="font-semibold">{{ $filterStatus ?: 'Semua Status' }}</span>
                                        @if ($filterMode === 'bulan')
                                            untuk bulan <span class="font-semibold font-mono">{{ $filterBulan }}</span>
                                        @elseif ($filterMode === 'tanggal')
                                            untuk tanggal <span class="font-semibold font-mono">{{ $filterTanggal }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                {{-- Pagination --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->bookingData->links() }}
                </div>

            </div>

        </div>
    </div>

    {{-- ═══════════ MODAL: BATAL BOOKING ═══════════ --}}
    <x-modal name="batal-booking" maxWidth="md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex items-center justify-center w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/40">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Batalkan Booking</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $batalNobooking }}</p>
                </div>
            </div>

            <div class="mb-4">
                <x-input-label value="Keterangan Batal" class="mb-1" />
                <textarea wire:model="batalKeterangan" rows="3" placeholder="Tulis alasan pembatalan..."
                    class="block w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800
                           text-sm text-gray-700 dark:text-gray-200 px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                @error('batalKeterangan')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-secondary-button wire:click="$dispatch('close-modal', { name: 'batal-booking' })">
                    Batal
                </x-secondary-button>
                <button type="button" wire:click="konfirmasiBatal" wire:loading.attr="disabled"
                    wire:target="konfirmasiBatal"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 transition">
                    <span wire:loading.remove wire:target="konfirmasiBatal">Konfirmasi Batal</span>
                    <span wire:loading wire:target="konfirmasiBatal">Membatalkan...</span>
                </button>
            </div>
        </div>
    </x-modal>

</div>
