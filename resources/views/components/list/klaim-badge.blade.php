@props([
    'status' => null,   // skmst_klaimtypes.klaim_status: BPJS | UMUM | KRONIS | DOKEL (kategori → warna)
    'desc' => null,     // skmst_klaimtypes.klaim_desc: nama asli DB (mis. "JKN MANDIRI") → label badge
    'id' => null,       // skmst_rjhdrs.klaim_id: fallback label bila desc kosong
    'prefix' => '',     // opsional teks di depan (mis. "Klaim: ")
])

{{--
    Badge cara bayar / klaim untuk list transaksi. Data tetap dari MODEL KLAIM
    (skmst_klaimtypes): label = klaim_desc asli (bukan disederhanakan jadi UMUM/BPJS),
    warna = kategori klaim_status — robust untuk SEMUA jenis klaim, tak seperti match
    klaim_id yang menjatuhkan jenis lain ke "Asuransi Lain".
      BPJS → success · UMUM → alternative · KRONIS → warning · DOKEL → purple · lainnya → gray

    Khas siklik: klaim_id 'JM' (JKN Mobile) dihitung BPJS walau klaim_status-nya kosong —
    aturan yang sama dipakai filter Klaim di list RJ.

    Pakai di kolom Klaim/cara bayar semua list (pelayanan/daftar/kasir/apotek).
--}}
@php
    $kategoriKlaim = strtoupper(trim((string) $status));
    if ($kategoriKlaim === '' && strtoupper(trim((string) $id)) === 'JM') {
        $kategoriKlaim = 'BPJS';
    }
    $variant = match ($kategoriKlaim) {
        'BPJS' => 'success',
        'UMUM' => 'alternative',
        'KRONIS' => 'warning',
        'DOKEL' => 'purple',
        default => 'gray',
    };
    $descKlaim = trim((string) $desc);
    // Label: "KATEGORI · desc" sejajar (mis. "BPJS · JKN MANDIRI"); dedupe bila desc = kategori.
    $namaKlaim = filled($descKlaim) ? $descKlaim : (filled($id) ? $id : '-');
    $labelKlaim =
        $kategoriKlaim !== '' && strtoupper($namaKlaim) !== $kategoriKlaim
            ? $kategoriKlaim . ' · ' . $namaKlaim
            : ($kategoriKlaim !== '' ? $kategoriKlaim : $namaKlaim);
@endphp
<x-badge :variant="$variant" {{ $attributes }}>{{ $prefix }}{{ $labelKlaim }}</x-badge>
