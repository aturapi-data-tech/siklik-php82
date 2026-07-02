<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Rj\EmrCompletenessRJTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrCompletenessRJTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-rj-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     | Daftar RJ = view PENDAFTARAN (Mr/Admin). Filter Status pakai rj_status.
     | Pelayanan EMR (Dokter/Perawat) dipisah ke /rawat-jalan/pelayanan.
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A'; // rj_status: A=Antrian, L=Selesai, F=Batal, I=Rujuk
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM' — pakai klaim_status di rsmst_klaimtypes (JM dianggap BPJS)
    public string $filterPoli = '';
    public string $filterDokter = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        // Tidak incrementVersion — wire:key remount toolbar di tengah ketik bikin
        // search input kehilangan focus, backspace berikutnya memicu browser back.
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterPoli', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-rj-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('daftar-rj.create.open');
    }

    public function openEdit(string $rjNo): void
    {
        $this->dispatch('daftar-rj.edit.open', rjNo: $rjNo);
    }

    public function openSatuSehat(string $rjNo): void
    {
        $this->dispatch('daftar-rj.satu-sehat.open', rjNo: $rjNo);
    }

    /* -------------------------
     | Request Delete
     * ------------------------- */
    public function requestDelete(string $rjNo): void
    {
        $this->dispatch('toast', type: 'warning', message: 'Modul Rawat Jalan - Dalam Pengembangan');
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('refresh-after-rj.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-rj-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries — Daftar RJ (pendaftaran): status pakai rj_status
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $statusColumn = DB::raw("NVL(h.rj_status, 'A')");

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'RJ')->where('checkup_status', '!=', 'B')->groupBy('ref_no');

        $radSub = DB::table('rstxn_rjrads')->select('rj_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rj_no');

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.erm_status', 'h.vno_sep', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status'), 'h.datadaftarpolirj_json', 'k.klaim_desc', 'k.klaim_status'])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('d.dr_name', 'desc')
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.no_antrian', 'asc');

        if ($this->filterStatus !== '') {
            $query->where($statusColumn, $this->filterStatus);
        }

        // Filter Klaim BPJS / UMUM
        // BPJS = klaim_status='BPJS' (di rsmst_klaimtypes) ATAU klaim_id='JM' (JKN Mobile)
        // UMUM = bukan keduanya
        if ($this->filterKlaim === 'BPJS') {
            $query->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            });
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where(function ($q) {
                $q->where(function ($w) {
                    $w->where('k.klaim_status', '!=', 'BPJS')->orWhereNull('k.klaim_status');
                })->where('h.klaim_id', '!=', 'JM');
            });
        }

        if ($this->filterPoli !== '') {
            $query->where('h.poli_id', $this->filterPoli);
        }

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rj_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.rj_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    /* -------------------------
     | Query pending bookings (terpisah, tidak UNION)
     * ------------------------- */
    private function queryPendingBookings(string $search): \Illuminate\Support\Collection
    {
        if (!in_array($this->filterStatus, ['A', ''])) {
            return collect();
        }

        $q = DB::table('referensi_mobilejkn_bpjs as b')
            ->leftJoin('rsmst_pasiens as p', DB::raw('UPPER(p.reg_no)'), '=', DB::raw('UPPER(b.norm)'))
            ->leftJoin('rsmst_polis as pol', 'pol.kd_poli_bpjs', '=', 'b.kodepoli')
            ->leftJoin('rsmst_doctors as d', 'd.kd_dr_bpjs', '=', 'b.kodedokter')
            ->select(['b.nobooking as rj_no', DB::raw("TO_CHAR(TO_DATE(b.tanggalperiksa,'yyyy-mm-dd'),'dd/mm/yyyy') || ' ' || SUBSTR(b.jampraktek,1,5) || ':00' as rj_date_display"), DB::raw('UPPER(b.norm) as reg_no'), 'p.reg_name', 'p.sex', 'p.address', DB::raw("TO_CHAR(p.birth_date,'dd/mm/yyyy') AS birth_date"), 'b.angkaantrean as no_antrian', 'b.nomorantrean', 'pol.poli_desc', 'd.dr_name'])
            ->where('b.tanggalperiksa', $this->filterTanggalCarbon()->format('Y-m-d'))
            ->where('b.status', 'Belum');

        if ($this->filterDokter !== '') {
            $kdDrBpjs = DB::table('rsmst_doctors')->where('dr_id', $this->filterDokter)->value('kd_dr_bpjs');
            $kdDrBpjs ? $q->where('b.kodedokter', $kdDrBpjs) : $q->whereRaw('1=0');
        }
        if ($this->filterPoli !== '') {
            $kdPoliBpjs = DB::table('rsmst_polis')->where('poli_id', $this->filterPoli)->value('kd_poli_bpjs');
            $kdPoliBpjs ? $q->where('b.kodepoli', $kdPoliBpjs) : $q->whereRaw('1=0');
        }
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $q->where(function ($qb) use ($kw) {
                $qb->where(DB::raw('UPPER(b.nobooking)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(b.norm)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $q->orderBy(DB::raw('TO_NUMBER(b.angkaantrean)'), 'asc')->get();
    }

    private function dateRange(): array
    {
        $d = $this->filterTanggalCarbon()->startOfDay();
        return [$d, (clone $d)->endOfDay()];
    }

    /**
     * Parse filterTanggal (d/m/Y) jadi Carbon. Fallback ke hari ini bila kosong /
     * format belum lengkap (mis. user mid-typing di input). Cegah Carbon throw.
     */
    private function filterTanggalCarbon(): Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y', trim((string) $this->filterTanggal))->startOfDay();
        } catch (\Throwable $e) {
            return now()->startOfDay();
        }
    }

    #[Computed]
    public function rows()
    {
        $search = trim($this->searchKeyword);

        // ── 1. Fetch & transform rstxn_rjhdrs ────────────────────────────
        $rjRows = $this->baseQuery()
            ->get()
            ->map(function ($row) {
                $row->is_booking_pending = false;

                // Oracle CLOB bisa di-fetch sebagai OCILob/resource alih-alih string — normalize dulu.
                $jsonRaw = $row->datadaftarpolirj_json ?? '{}';
                if (is_object($jsonRaw) && method_exists($jsonRaw, 'load')) {
                    $jsonRaw = $jsonRaw->load();
                } elseif (is_resource($jsonRaw)) {
                    $jsonRaw = stream_get_contents($jsonRaw);
                }
                $json = json_decode($jsonRaw ?: '{}', true) ?? [];

                // EMR completeness — weighted S15/O25/A25/P25/N10. Logic ada di EmrCompletenessRJTrait.
                $pct = $this->calculateEmrPercentRJ($json);
                $row->emr_percent = $pct['emr'];
                $row->emr_sections = $pct['sections'];
                $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;
                $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
                $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
                $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;
                $row->pcare_pendaftaran_code = (int) ($json['taskIdPelayanan']['pcarePendaftaran']['code'] ?? 0);
                $row->pcare_kunjungan_code   = (int) ($json['taskIdPelayanan']['pcareKunjungan']['code'] ?? 0);
                $row->no_referensi = $json['noReferensi'] ?? null;

                $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';
                $row->administrasi_detail = $json['AdministrasiRj'] ?? null;
                $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
                $row->tindak_lanjut_detail = $json['perencanaan']['tindakLanjut'] ?? null;
                $row->tgl_kontrol = $json['kontrol']['tglKontrol'] ?? '-';
                $row->kontrol_detail = $json['kontrol'] ?? null;

                $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode('# ', array_column($json['diagnosis'], 'icdX')) : '-';
                $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';
                $row->diagnosis_detail = $json['diagnosis'] ?? null;
                $row->procedure = isset($json['procedure']) && is_array($json['procedure']) ? implode('# ', array_column($json['procedure'], 'procedureId')) : '-';
                $row->procedure_free_text = $json['procedureFreeText'] ?? '-';
                $row->procedure_detail = $json['procedure'] ?? null;

                $row->status_resep = $json['statusResep']['status'] ?? null;
                $row->status_resep_label = $row->status_resep === 'DITUNGGU' ? 'Ditunggu' : ($row->status_resep === 'DITINGGAL' ? 'Ditinggal' : '-');
                $row->status_resep_color = $row->status_resep === 'DITUNGGU' ? 'green' : ($row->status_resep === 'DITINGGAL' ? 'yellow' : 'gray');
                $row->no_booking = $json['noBooking'] ?? ($row->nobooking ?? '-');
                $row->rj_no_json = $json['rjNo'] ?? '-';
                $row->is_json_valid = $row->rj_no == $row->rj_no_json;
                $row->bg_check_json = $row->is_json_valid ? 'bg-green-100' : 'bg-red-100';

                if (!empty($row->birth_date)) {
                    try {
                        $tglLahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                        $diff = $tglLahir->diff(now());
                        $row->umur_format = "{$row->birth_date} ({$diff->y} Thn {$diff->m} Bln {$diff->d} Hr)";
                    } catch (\Exception $e) {
                        $row->umur_format = '-';
                    }
                } else {
                    $row->umur_format = '-';
                }

                $row->status_text = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Rujuk'][$row->rj_status] ?? 'Pelayanan';
                $row->status_variant = ['A' => 'warning', 'L' => 'success', 'F' => 'danger', 'I' => 'brand'][$row->rj_status] ?? 'gray';

                return $row;
            });

        // ── 2. Fetch & transform pending bookings ─────────────────────────
        $pendingRows = $this->queryPendingBookings($search)->map(function ($row) {
            $row->is_booking_pending = true;
            $row->no_antrian = (int) $row->no_antrian; // VARCHAR2 → int untuk sort
            $row->emr_percent = 0;
            $row->eresep_percent = 0;
            $row->task_id3 = null;
            $row->task_id4 = null;
            $row->task_id5 = null;
            $row->no_referensi = null;
            $row->admin_user = '-';
            $row->administrasi_detail = null;
            $row->tindak_lanjut = '-';
            $row->tindak_lanjut_detail = null;
            $row->tgl_kontrol = '-';
            $row->kontrol_detail = null;
            $row->diagnosis = '-';
            $row->diagnosis_free_text = '-';
            $row->diagnosis_detail = null;
            $row->procedure = '-';
            $row->procedure_free_text = '-';
            $row->procedure_detail = null;
            $row->status_resep = null;
            $row->status_resep_label = '-';
            $row->status_resep_color = 'gray';
            $row->no_booking = $row->rj_no;
            $row->rj_no_json = '-';
            $row->is_json_valid = true;
            $row->bg_check_json = '';
            $row->status_text = 'Menunggu Checkin';
            $row->status_variant = 'warning';
            $row->klaim_id = 'JM';
            $row->klaim_desc = 'JKN Mobile';
            $row->klaim_status = 'BPJS';
            $row->vno_sep = '-';
            $row->rj_status = 'PENDING';
            $row->erm_status = 'A';
            $row->lab_status = 0;
            $row->rad_status = 0;
            $row->shift = '-';
            $row->rj_date_display = $row->rj_date_display ?? '-';

            if (!empty($row->birth_date)) {
                try {
                    $d = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                    $diff = $d->diff(now());
                    $row->umur_format = "{$row->birth_date} ({$diff->y} Thn {$diff->m} Bln {$diff->d} Hr)";
                } catch (\Exception) {
                    $row->umur_format = '-';
                }
            } else {
                $row->umur_format = '-';
            }

            return $row;
        });

        // ── 3. Merge, sort: dr_name DESC → no_antrian ASC ─────────────────
        $allRows = $rjRows
            ->merge($pendingRows)
            ->sort(function ($a, $b) {
                $drCmp = strcmp($b->dr_name ?? '', $a->dr_name ?? '');
                if ($drCmp !== 0) {
                    return $drCmp;
                }
                return (int) ($a->no_antrian ?? 0) - (int) ($b->no_antrian ?? 0);
            })
            ->values();

        // ── 4. Manual paginate ─────────────────────────────────────────────
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage = $this->itemsPerPage;

        return new \Illuminate\Pagination\LengthAwarePaginator($allRows->slice(($page - 1) * $perPage, $perPage)->values(), $allRows->count(), $perPage, $page, ['path' => request()->url()]);
    }

    /* -------------------------
     | Master data for filters
     * ------------------------- */
    #[Computed]
    public function poliList()
    {
        return DB::table('rsmst_polis')->select('poli_id', 'poli_desc')->orderBy('poli_desc')->get();
    }

    #[Computed]
    public function dokterList()
    {
        // ✅ Tanpa cache()->remember() — langsung query agar selalu fresh saat filter berubah
        $query = DB::table('rstxn_rjhdrs')->select('rstxn_rjhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), 'rstxn_rjhdrs.poli_id', DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'), DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'))->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_rjhdrs.poli_id')->where(DB::raw("to_char(rstxn_rjhdrs.rj_date, 'dd/mm/yyyy')"), '=', $this->filterTanggal);

        if (!empty($this->filterStatus)) {
            $query->where('rstxn_rjhdrs.rj_status', $this->filterStatus);
        }

        if (!empty($this->searchKeyword) && strlen($this->searchKeyword) >= 2) {
            $keyword = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($keyword) {
                $q->where(DB::raw('UPPER(rsmst_doctors.dr_name)'), 'LIKE', "%{$keyword}%")->orWhere(DB::raw('UPPER(rsmst_polis.poli_desc)'), 'LIKE', "%{$keyword}%");
            });
        }

        return $query->groupBy('rstxn_rjhdrs.dr_id', 'rstxn_rjhdrs.poli_id')->orderBy('poli_desc')->orderBy('dr_name')->get();
    }

    #[Computed]
    public function klaimList()
    {
        return DB::table('rsmst_klaims')->select('klaim_id', 'klaim_name')->where('active_status', '1')->orderBy('klaim_name')->get();
    }

    public function cetakEtiket(string $regNo): void
    {
        $this->dispatch('cetak-etiket.open', regNo: $regNo);
    }
};
?>

{{-- ✅ wire:key di level paling atas — seluruh halaman re-render saat filter berubah --}}
{{-- Child components aman karena punya static wire:key masing-masing              --}}
<div>
    <x-page-title
        title="Daftar Rawat Jalan"
        subtitle="Kelola pendaftaran pasien rawat jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('daftar-rj-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RJ / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- FILTER STATUS — rj_status (pendaftaran) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Rujuk</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM — BPJS / UMUM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Pendaftaran Rawat Jalan
                        </x-primary-button>

                        <x-toolbar-refresh-reset :label="null" />
                    </div>

                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Poli</th>
                                <th class="px-6 py-3">Status Layanan</th>
                                <th class="px-6 py-3">Tindak Lanjut</th>
                                <th class="px-6 py-3 text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr
                                    class="transition rounded-2xl
                                    {{ $row->is_booking_pending
                                        ? 'bg-amber-50 dark:bg-amber-900/10 hover:shadow-md hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400'
                                        : 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800' }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-top">
                                        <div class="flex items-start gap-4">
                                            <div class="text-5xl font-bold text-gray-700 dark:text-gray-200">
                                                {{ $row->no_antrian ?? '-' }}
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $row->reg_no ?? '-' }}
                                                </div>
                                                <div class="text-lg font-semibold text-brand dark:text-white">
                                                    {{ $row->reg_name ?? '-' }} /
                                                    ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>
                                                <div class="text-base text-gray-700 dark:text-gray-400">
                                                    {{ $row->umur_format ?? '-' }}
                                                </div>
                                                <div class="text-base text-gray-600 dark:text-gray-400">
                                                    {{ $row->address ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- POLI --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="font-semibold text-brand dark:text-emerald-400">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>
                                        <div class="text-base text-gray-600 dark:text-gray-400">
                                            {{ $row->dr_name ?? '-' }} / {{ $row->klaim_desc ?? '-' }}
                                        </div>
                                        <div class="font-mono text-base text-gray-700 dark:text-gray-300">
                                            {{ $row->vno_sep ?? '-' }}
                                        </div>
                                        <div class="text-xs text-gray-700 dark:text-gray-400">
                                            No Booking: {{ $row->no_booking ?? '-' }}
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if ($row->lab_status)
                                                <x-badge variant="alternative">Laborat</x-badge>
                                            @endif
                                            @if ($row->rad_status)
                                                <x-badge variant="brand">Radiologi</x-badge>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            {{ $row->rj_date_display ?? '-' }} | Shift : {{ $row->shift ?? '-' }}
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        @if (!$row->is_booking_pending)
                                            {{-- EMR progress --}}
                                            <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                                <div class="h-1.5 rounded-full transition-all duration-500
                                                    {{ $row->emr_percent >= 80
                                                        ? 'bg-emerald-500/80 dark:bg-emerald-400'
                                                        : ($row->emr_percent >= 50
                                                            ? 'bg-amber-400/80 dark:bg-amber-400'
                                                            : 'bg-rose-400/80 dark:bg-rose-400') }}"
                                                    style="width: {{ $row->emr_percent ?? 0 }}%">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div
                                                    class="flex items-center gap-1 text-base text-gray-700 dark:text-gray-400">
                                                    <span>EMR : {{ $row->emr_percent ?? 0 }}%</span>
                                                    <button type="button"
                                                        x-on:click.stop="$dispatch('open-info-kelengkapan-emr-rj', { rjNo: {{ $row->rj_no }} })"
                                                        class="inline-flex items-center justify-center w-4 h-4 text-gray-400 transition rounded-full hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300"
                                                        title="Lihat status & kriteria kelengkapan EMR">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="text-base text-gray-700 dark:text-gray-400">
                                                    E-Resep : {{ $row->eresep_percent ?? 0 }}%
                                                </div>
                                            </div>

                                            @if ($row->status_resep)
                                                <x-badge :variant="$row->status_resep_color">
                                                    Status Resep: {{ $row->status_resep_label }}
                                                </x-badge>
                                            @endif

                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                            </div>

                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-semibold">Procedure:</span><br>
                                                {{ $row->procedure }} / {{ $row->procedure_free_text }}
                                            </div>

                                            @if (!empty($row->no_referensi))
                                                <div class="text-base text-gray-700 dark:text-gray-400">
                                                    No Ref : {{ $row->no_referensi }}
                                                </div>
                                            @endif

                                            <div
                                                class="text-xs p-1 rounded {{ $row->bg_check_json }} dark:bg-opacity-20">
                                                <span class="font-semibold">Validasi Data:</span><br>
                                                RJ No: {{ $row->rj_no }} / {{ $row->rj_no_json }}
                                                @if (!$row->is_json_valid)
                                                    <span class="text-red-600 dark:text-red-400">(Tidak Sinkron)</span>
                                                @endif
                                            </div>
                                        @else
                                            {{-- Pending booking — hanya info no booking --}}
                                            <div class="text-xs text-amber-700 dark:text-amber-400 font-mono mt-1">
                                                No Booking: {{ $row->rj_no }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Administrasi :
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ $row->admin_user ?? '-' }}
                                            </span>
                                        </div>

                                        @if ($row->administrasi_detail)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Waktu: {{ $row->administrasi_detail['waktu'] ?? '-' }}<br>
                                                Log: {{ $row->administrasi_detail['userLog'] ?? '-' }}
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-1 space-y-1">
                                            @if ($row->task_id3)
                                                <x-badge variant="success">TaskId3 {{ $row->task_id3 }}</x-badge>
                                            @endif
                                            @if ($row->task_id4)
                                                <x-badge variant="brand">TaskId4 {{ $row->task_id4 }}</x-badge>
                                            @endif
                                            @if ($row->task_id5)
                                                <x-badge variant="warning">TaskId5 {{ $row->task_id5 }}</x-badge>
                                            @endif
                                        </div>

                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            Tindak Lanjut : {{ $row->tindak_lanjut ?? '-' }}
                                        </div>

                                        @if ($row->tindak_lanjut_detail && ($row->tindak_lanjut_detail['tindakLanjut'] ?? null))
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Dokter: {{ $row->tindak_lanjut_detail['drPemeriksa'] ?? '-' }}
                                            </div>
                                        @endif

                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            Tanggal Kontrol : {{ $row->tgl_kontrol ?? '-' }}
                                        </div>

                                        @if ($row->kontrol_detail)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Poli Kontrol: {{ $row->kontrol_detail['poliKontrol'] ?? '-' }}<br>
                                                Dokter Kontrol: {{ $row->kontrol_detail['dokterKontrol'] ?? '-' }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-top">
                                        @if ($row->is_booking_pending)
                                            {{-- Pending: hanya info, belum bisa diakses --}}
                                            <div class="flex flex-col items-center gap-2 text-center">
                                                <div class="text-amber-500 dark:text-amber-400">
                                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                                                    Belum Checkin
                                                </span>
                                                <span class="text-xs text-gray-400">
                                                    Aksi tersedia<br>setelah checkin
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-4">

                                                {{-- Cetak Etiket --}}
                                                <x-secondary-button wire:click="cetakEtiket('{{ $row->reg_no }}')"
                                                    wire:loading.attr="disabled" wire:target="cetakEtiket">
                                                    <span wire:loading.remove wire:target="cetakEtiket"
                                                        class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                        </svg>
                                                        Etiket
                                                    </span>
                                                    <span wire:loading wire:target="cetakEtiket"
                                                        class="flex items-center gap-1">
                                                        <x-loading />
                                                        Mencetak...
                                                    </span>
                                                </x-secondary-button>

                                                {{-- Dropdown Aksi --}}
                                                <x-dropdown position="left" width="w-[500px]">
                                                    <x-slot name="trigger">
                                                        <x-secondary-button type="button" class="p-2">
                                                            <svg class="w-5 h-5" fill="currentColor"
                                                                viewBox="0 0 20 20">
                                                                <path
                                                                    d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                            </svg>
                                                        </x-secondary-button>
                                                    </x-slot>

                                                    <x-slot name="content">
                                                        <div class="p-2 space-y-2">

                                                            {{-- Task ID — Admin & Perawat selalu tampil, Mr hanya kalau lab/rad true --}}
                                                            @if (auth()->user()->hasAnyRole(['Admin', 'Perawat']) ||
                                                                    (auth()->user()->hasRole('Mr') && ($row->lab_status || $row->rad_status)))
                                                                <div class="flex space-x-1">
                                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-4
                                                                        :rjNo="$row->rj_no"
                                                                        wire:key="'taskid4--'.{{ $row->rj_no }}" />
                                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-5
                                                                        :rjNo="$row->rj_no"
                                                                        wire:key="'taskid5--'.{{ $row->rj_no }}" />
                                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.get-task-id
                                                                        :rjNo="$row->rj_no"
                                                                        wire:key="'gettaskid--'.{{ $row->rj_no }}" />
                                                                </div>
                                                            @endif

                                                            {{-- GRID 2 KOLOM --}}
                                                            <div class="grid grid-cols-2 gap-1">

                                                                {{-- Ubah — Mr & Admin --}}
                                                                @hasanyrole('Mr|Admin')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openEdit('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                                                            </svg>
                                                                            <span>
                                                                                Pendaftaran Ubah <br>
                                                                                <span
                                                                                    class="font-semibold">{{ $row->reg_name }}</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Kirim Satu Sehat — Admin --}}
                                                                @role('Admin')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openSatuSehat('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-teal-50 hover:bg-teal-100 dark:bg-teal-900/20 dark:hover:bg-teal-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                            </svg>
                                                                            <span>
                                                                                Kirim Satu Sehat <br>
                                                                                <span
                                                                                    class="font-semibold">{{ $row->reg_name }}</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endrole

                                                                {{-- Kirim BPJS PCare — kirim Pendaftaran (RJTP klinik pratama) --}}
                                                                @hasanyrole('Admin|Mr|Perawat')
                                                                    @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM'))
                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="$dispatch('rj.pcare.push-pendaftaran', { rjNo: '{{ $row->rj_no }}' })"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                                                </svg>
                                                                                <span>
                                                                                    Kirim Pendaftaran BPJS <br>
                                                                                    <span class="font-semibold">{{ $row->reg_name }}</span>
                                                                                </span>
                                                                            </div>
                                                                        </x-dropdown-link>
                                                                    @endif
                                                                @endhasanyrole

                                                                {{-- Kirim BPJS PCare — kirim Kunjungan (setelah dokter selesai diagnosa+terapi) --}}
                                                                @hasanyrole('Admin|Dokter')
                                                                    @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM') && $row->rj_status === 'L')
                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="$dispatch('rj.pcare.push-kunjungan', { rjNo: '{{ $row->rj_no }}' })"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:hover:bg-indigo-900/40">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                                                </svg>
                                                                                <span>
                                                                                    Kirim Kunjungan BPJS <br>
                                                                                    <span class="font-semibold">{{ $row->reg_name }}</span>
                                                                                </span>
                                                                            </div>
                                                                        </x-dropdown-link>
                                                                    @endif
                                                                @endhasanyrole

                                                                {{-- Riwayat Kunjungan BPJS — semua role medis, asalkan pasien BPJS --}}
                                                                @hasanyrole('Admin|Dokter|Mr|Perawat')
                                                                    @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM'))
                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="$dispatch('rj.pcare.riwayat-kunjungan', { rjNo: '{{ $row->rj_no }}' })"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/40">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                </svg>
                                                                                <span>
                                                                                    Riwayat Kunjungan BPJS <br>
                                                                                    <span class="font-semibold">{{ $row->reg_name }}</span>
                                                                                </span>
                                                                            </div>
                                                                        </x-dropdown-link>
                                                                    @endif
                                                                @endhasanyrole

                                                                {{-- Edit Kunjungan BPJS — hanya kalau pcareKunjungan sudah berhasil --}}
                                                                @hasanyrole('Admin|Dokter')
                                                                    @php
                                                                        $kunjCode = data_get($row, 'pcare_kunjungan_code')
                                                                            ?? data_get(json_decode($row->datadaftarpolirj_json ?? '{}', true), 'taskIdPelayanan.pcareKunjungan.code', 0);
                                                                    @endphp
                                                                    @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM') && in_array((int) $kunjCode, [200, 201], true))
                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="$dispatch('rj.pcare.edit-kunjungan', { rjNo: '{{ $row->rj_no }}' })"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/40">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                                </svg>
                                                                                <span>
                                                                                    Edit Kunjungan BPJS <br>
                                                                                    <span class="font-semibold">{{ $row->reg_name }}</span>
                                                                                </span>
                                                                            </div>
                                                                        </x-dropdown-link>

                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="$dispatch('rj.pcare.delete-kunjungan', { rjNo: '{{ $row->rj_no }}' })"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/20 dark:hover:bg-rose-900/40">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        d="M6 7h12M9 7V5a3 3 0 016 0v2m-9 0l1 12h8l1-12" />
                                                                                </svg>
                                                                                <span>
                                                                                    Hapus Kunjungan BPJS <br>
                                                                                    <span class="font-semibold">{{ $row->reg_name }}</span>
                                                                                </span>
                                                                            </div>
                                                                        </x-dropdown-link>
                                                                    @endif
                                                                @endhasanyrole

                                                            </div>

                                                            {{-- DIVIDER --}}
                                                            <div
                                                                class="my-1 border-t border-gray-200 dark:border-gray-700">
                                                            </div>

                                                            {{-- Batal Antrean (Task ID 99) — Admin only --}}
                                                            @role('Admin')
                                                                <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-99
                                                                    :rjNo="$row->rj_no"
                                                                    wire:key="taskid99-{{ $row->rj_no }}" />
                                                            @endrole

                                                            {{-- Hapus — Admin only --}}
                                                            @role('Admin')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="requestDelete('{{ $row->rj_no }}')"
                                                                    class="w-full px-3 py-2 text-sm font-semibold text-red-600 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50">
                                                                    <div class="flex items-center justify-center gap-2">
                                                                        <svg class="w-5 h-5" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24"
                                                                            stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M6 7h12M9 7V5a3 3 0 016 0v2m-9 0l1 12h8l1-12" />
                                                                        </svg>
                                                                        <span>Hapus</span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endrole

                                                        </div>
                                                    </x-slot>
                                                </x-dropdown>

                                            </div>
                                        @endif
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-gray-700 dark:text-gray-400">
                                        Belum ada data
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>

            </div>

            {{-- Child components — static wire:key agar tidak ikut re-mount saat filter berubah --}}
            {{-- Daftar RJ = pendaftaran only; EMR/Modul Dokumen/Administrasi pindah ke /rawat-jalan/pelayanan --}}
            <livewire:pages::transaksi.rj.daftar-rj.daftar-rj-actions wire:key="daftar-rj-actions" />

            {{-- Modal Satu Sehat (sibling, listen ke event daftar-rj.satu-sehat.open) --}}
            <livewire:pages::transaksi.rj.daftar-rj.satu-sehat-rj-actions wire:key="satu-sehat-rj-actions" />

            {{-- Cetak Etiket --}}
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-pasien" />

            {{-- Info Kelengkapan EMR (modal kriteria & status per-pasien) --}}
            <livewire:pages::transaksi.rj.daftar-rj.info-kelengkapan-emr wire:key="info-kelengkapan-emr-rj" />

        </div>
    </div>
</div>
