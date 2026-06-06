<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\PcareTrait;

new class extends Component {
    use PcareTrait;

    /* -------------------------
     | Konfigurasi kategori cache (label → method PcareTrait + args)
     |
     | Pola contek dari siklik-lite. Tabel ref_bpjs_table simpan list
     | response BPJS PCare yg jarang berubah, dipakai oleh LOV
     | (alergi, prognosa, dll) supaya nggak hit API tiap kali.
     * ------------------------- */
    /*  Key naming = sama persis dengan siklik-lite (Title Case + suffix RJ/RI
        untuk Status Pulang). Lookup di-pakai case-insensitive (upper(...) = upper(?))
        supaya toleran terhadap variasi casing di klinik existing. */
    public array $categories = [
        ['key' => 'Kesadaran',        'desc' => 'Daftar kode kesadaran (Compos Mentis, Apatis, dll).'],
        ['key' => 'Spesialis',        'desc' => 'Daftar spesialisasi dokter BPJS.'],
        ['key' => 'Prognosa',         'desc' => 'Kode prognosa pasien (Sanam, Bonam, dll).'],
        ['key' => 'PoliFktp',         'desc' => 'Daftar poli BPJS untuk klinik pratama (FKTP).'],
        ['key' => 'Status Pulang RJ', 'desc' => 'Kode status pulang pasien rawat jalan (Berobat Jalan, Rujuk, dll).'],
        ['key' => 'Status Pulang RI', 'desc' => 'Kode status pulang pasien rawat inap (Sembuh, Meninggal, Rujuk, dll).'],
        ['key' => 'Sarana',           'desc' => 'Sarana penunjang BPJS.'],
        ['key' => 'Alergi Makanan',   'desc' => 'Daftar referensi alergi makanan BPJS.'],
        ['key' => 'Alergi Udara',     'desc' => 'Daftar referensi alergi udara BPJS.'],
        ['key' => 'Alergi Obat',      'desc' => 'Daftar referensi alergi obat BPJS.'],
        ['key' => 'Dokter',           'desc' => 'Master dokter terdaftar di BPJS PCare.'],
        ['key' => 'Provider',         'desc' => 'Daftar faskes provider rayonisasi.'],
    ];

    /* -------------------------
     | Update single kategori → call BPJS API → upsert ke ref_bpjs_table
     * ------------------------- */
    public function updateRef(string $key): void
    {
        try {
            $list = $this->fetchListByKey($key);
            if ($list === null) {
                $this->dispatch('toast', type: 'error',
                    message: "Kategori tidak dikenal: {$key}", title: 'Master Ref BPJS');
                return;
            }
            if (!is_array($list) || empty($list)) {
                $this->dispatch('toast', type: 'warning',
                    message: "BPJS tidak mengembalikan data untuk {$key}.",
                    title: 'Master Ref BPJS');
                return;
            }

            $jsonNew = json_encode($list, true);
            $jsonOld = (string) (DB::table('ref_bpjs_table')
                ->whereRaw('upper(ref_keterangan) = upper(?)', [$key])
                ->value('ref_json') ?? '');

            if ($jsonNew === $jsonOld) {
                $this->dispatch('toast', type: 'info',
                    message: "{$key}: data BPJS sudah sama dgn cache lokal.",
                    title: 'Master Ref BPJS');
                return;
            }

            // Schema siklik (existing): ref_keterangan PK + ref_json CLOB.
            // Pola siklik-lite: delete-then-insert (lebih aman utk Oracle CLOB
            // update lewat Eloquent). Pakai case-insensitive delete supaya
            // klinik existing dengan casing variasi (UPPERCASE/lowercase) ke-clean,
            // lalu insert ulang dgn key Title Case dari $categories.
            DB::table('ref_bpjs_table')
                ->whereRaw('upper(ref_keterangan) = upper(?)', [$key])
                ->delete();
            DB::table('ref_bpjs_table')->insert([
                'ref_keterangan' => $key,
                'ref_json'       => $jsonNew,
            ]);

            $this->dispatch('toast', type: 'success',
                message: "{$key}: cache lokal di-update (" . count($list) . " entries).",
                title: 'Master Ref BPJS', duration: 5000);

            unset($this->refRows);
        } catch (\Exception $e) {
            \Log::error('Master Ref BPJS update exception', ['key' => $key, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error',
                message: 'Error: ' . $e->getMessage(), title: 'BPJS Error');
        }
    }

    /* Mapping label → call PcareTrait. Return list (array) atau null kalau key tdk dikenal.
       Status Pulang split RJ vs RI (siklik-lite convention) — getStatusPulang('0') untuk RJ,
       getStatusPulang('1') untuk RI. */
    private function fetchListByKey(string $key): ?array
    {
        $resp = match ($key) {
            'Kesadaran'        => $this->getKesadaran()->getOriginalContent(),
            'Spesialis'        => $this->getSpesialis()->getOriginalContent(),
            'Prognosa'         => $this->getPrognosa()->getOriginalContent(),
            'PoliFktp'         => $this->getPoliFktp(0, 100)->getOriginalContent(),
            'Status Pulang RJ' => $this->getStatusPulang('0')->getOriginalContent(),
            'Status Pulang RI' => $this->getStatusPulang('1')->getOriginalContent(),
            'Sarana'           => $this->getSarana()->getOriginalContent(),
            'Alergi Makanan'   => $this->getAlergi('01')->getOriginalContent(),
            'Alergi Udara'     => $this->getAlergi('02')->getOriginalContent(),
            'Alergi Obat'      => $this->getAlergi('03')->getOriginalContent(),
            'Dokter'           => $this->getDokter(0, 200)->getOriginalContent(),
            'Provider'         => $this->getProviderRayonisasi(0, 100)->getOriginalContent(),
            default            => null,
        };
        if ($resp === null) return null;

        $code = $resp['metadata']['code'] ?? 0;
        if ($code != 200) {
            $msg = $resp['metadata']['message'] ?? "code {$code}";
            throw new \RuntimeException("BPJS {$key}: {$msg}");
        }

        return $resp['response']['list'] ?? $resp['response'] ?? [];
    }

    /* -------------------------
     | Cache row metadata (count + updated_at) per kategori
     * ------------------------- */
    #[Computed]
    public function refRows(): array
    {
        // KeyBy uppercase → toleran terhadap casing variasi di klinik existing
        // (e.g. 'STATUS PULANG RJ' uppercase dari siklik-lite vs 'Status Pulang RJ' Title Case).
        $rows = DB::table('ref_bpjs_table')
            ->select('ref_keterangan', 'ref_json')
            ->get()
            ->keyBy(fn($r) => strtoupper($r->ref_keterangan));

        $out = [];
        foreach ($this->categories as $cat) {
            $row = $rows->get(strtoupper($cat['key']));
            $items = [];
            if ($row && $row->ref_json) {
                $decoded = json_decode($row->ref_json, true);
                $items = is_array($decoded) ? $decoded : [];
            }
            $out[$cat['key']] = [
                'count' => count($items),
                'items' => $this->normalizeItems($items),
            ];
        }
        return $out;
    }

    /**
     * Normalisasi list BPJS jadi [['code' => ..., 'name' => ...], ...]
     * Auto-detect key kode (prefix kd / kode / suffix id) & nama
     * (prefix nm / nama / suffix desc / name). Fallback 2 key pertama.
     */
    private function normalizeItems(array $items): array
    {
        if (empty($items)) return [];

        $first = (array) $items[0];
        $codeKey = null;
        $nameKey = null;

        foreach (array_keys($first) as $k) {
            $lk = strtolower($k);
            if ($codeKey === null && (str_starts_with($lk, 'kd') || $lk === 'kode' || str_ends_with($lk, 'id'))) {
                $codeKey = $k;
            }
            if ($nameKey === null && (str_starts_with($lk, 'nm') || $lk === 'nama' || str_ends_with($lk, 'desc') || str_ends_with($lk, 'name'))) {
                $nameKey = $k;
            }
        }

        // Fallback: pakai 2 key pertama kalau auto-detect gagal
        $keys = array_keys($first);
        $codeKey ??= $keys[0] ?? null;
        $nameKey ??= $keys[1] ?? $codeKey;

        return array_map(function ($it) use ($codeKey, $nameKey) {
            $it = (array) $it;
            return [
                'code' => (string) ($it[$codeKey] ?? ''),
                'name' => (string) ($it[$nameKey] ?? ''),
            ];
        }, $items);
    }
};
?>

<div>
    <x-page-title
        title="Master Reference BPJS PCare"
        subtitle="Cache lokal untuk reference BPJS yg jarang berubah (alergi, kesadaran, prognosa, dll). Klik Update untuk sync per kategori." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6">
            <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                        <tr class="text-left">
                            <th class="px-4 py-3 font-semibold w-1/4">Kategori</th>
                            <th class="px-4 py-3 font-semibold">Deskripsi</th>
                            <th class="px-4 py-3 font-semibold w-24 text-right">Jumlah</th>
                            <th class="px-4 py-3 font-semibold w-64">Preview Cache</th>
                            <th class="px-4 py-3 font-semibold w-32 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                        @foreach ($categories as $cat)
                            @php $meta = $this->refRows[$cat['key']] ?? ['count' => 0, 'items' => []]; @endphp
                            <tr wire:key="ref-bpjs-{{ $cat['key'] }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3 font-semibold">{{ $cat['key'] }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $cat['desc'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">
                                    @if ($meta['count'] > 0)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            {{ $meta['count'] }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($meta['count'] > 0)
                                        <x-select-input title="Display only — preview entries cache">
                                            <option value="">— lihat {{ $meta['count'] }} entries —</option>
                                            @foreach ($meta['items'] as $it)
                                                <option value="{{ $it['code'] }}">
                                                    {{ $it['code'] }}
                                                    @if ($it['name'] !== '' && $it['name'] !== $it['code'])
                                                        — {{ $it['name'] }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </x-select-input>
                                    @else
                                        <span class="text-xs italic text-gray-400">belum di-sync</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-secondary-button type="button"
                                        wire:click="updateRef('{{ $cat['key'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="updateRef('{{ $cat['key'] }}')"
                                        class="px-3 py-1 text-xs">
                                        <span wire:loading.remove wire:target="updateRef('{{ $cat['key'] }}')">Update</span>
                                        <span wire:loading wire:target="updateRef('{{ $cat['key'] }}')">...</span>
                                    </x-secondary-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
