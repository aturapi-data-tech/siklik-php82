@props([
    'regNo' => null,      // skmst_pasiens.reg_no (No. RM)
    'nama' => null,       // skmst_pasiens.reg_name
    'sex' => null,        // skmst_pasiens.sex → 'L' | 'P' | lainnya
    'jalur' => null,      // 'Rawat Jalan' | 'Kasir' | 'Apotek' — opsional
])

{{--
    Identitas pasien sebagai KEPALA menu aksi (tombol titik-tiga) di list transaksi.
    Menjawab satu pertanyaan: menu ini berlaku untuk pasien yang mana.

    Nama pasien disebut SEKALI di kepala menu, lalu semua butir menu di bawahnya
    berbunyi seragam (tanpa menempelkan nama pasien di sebagian butir saja).

    Ringkas (2 baris) karena hidup di dalam dropdown, bukan di kolom tabel. Untuk
    kolom PASIEN pada list-nya pakai <x-list.identitas-pasien> (4 baris + alamat).
--}}
<div
    class="flex items-center gap-2 px-3 py-2 border-b rounded-lg bg-surface-soft border-hairline dark:bg-gray-800 dark:border-gray-700">
    <div class="flex items-center justify-center rounded-lg w-7 h-7 shrink-0 bg-brand/10">
        <svg class="w-4 h-4 text-brand dark:text-brand-lime" fill="none" stroke="currentColor" viewBox="0 0 24 24"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
    </div>

    <div class="min-w-0">
        <div class="text-xs text-muted dark:text-gray-400">No. Rekam Medis {{ $regNo ?: '-' }}</div>
        <div class="flex items-center gap-1.5 min-w-0">
            <span class="text-sm font-semibold truncate text-ink dark:text-gray-100">{{ $nama ?: '-' }}</span>
            {{-- Simbol jenis kelamin sama dengan <x-list.identitas-pasien>: ♂ biru (L) / ♀ rose (P) --}}
            @if ($sex === 'L')
                <svg class="w-3.5 h-3.5 shrink-0 text-blue-500 dark:text-blue-400" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" role="img" aria-label="Laki-Laki">
                    <title>Laki-Laki</title>
                    <circle cx="9.5" cy="14.5" r="5.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l5-5m0 0h-5m5 0v5" />
                </svg>
            @elseif ($sex === 'P')
                <svg class="w-3.5 h-3.5 shrink-0 text-rose-500 dark:text-rose-400" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" role="img" aria-label="Perempuan">
                    <title>Perempuan</title>
                    <circle cx="12" cy="8.5" r="5.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3.5-3.5h7" />
                </svg>
            @endif
        </div>
    </div>

    @if ($jalur)
        <x-badge class="ml-auto shrink-0 whitespace-nowrap" variant="brand">{{ $jalur }}</x-badge>
    @endif
</div>
