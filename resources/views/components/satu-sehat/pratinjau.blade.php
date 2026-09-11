{{--
    Pratinjau data yang AKAN dikirim ke SATUSEHAT untuk satu langkah.

    Dipakai bersama seluruh kartu kirim RJ supaya bentuknya seragam dan petugas
    tahu persis apa yang berangkat sebelum menekan tombol — sebelumnya kartu
    hanya menyebut jumlah, sehingga isi yang salah baru ketahuan setelah ditolak
    SATUSEHAT (atau tidak ketahuan sama sekali).

    BEDANYA satu hal dari panel lipat lain di repo ini, dan disengaja: buka-tutup
    memakai wire:click, BUKAN Alpine x-show. x-show mengharuskan isinya SELALU
    dirender di server, padahal tiap kartu menghitung pratinjaunya sendiri dengan
    membaca JSON EMR — belasan pembacaan per muat modal hanya untuk panel yang
    tertutup. Pemanggil karena itu mengirim $baris kosong selama $terbuka false.

    @param array $baris    [ ['label' => 'Diagnosa 1', 'nilai' => 'J06.9', 'ket' => '...'], ... ]
    @param bool $terbuka   status panel (properti $pratinjauTerbuka milik kartu)
    @param string $kosong  kalimat bila tak ada data yang akan dikirim
    @param string $aksi    method Livewire pembuka/penutup
--}}
@props([
    'baris' => [],
    'terbuka' => false,
    'kosong' => 'Tidak ada data yang akan dikirim untuk langkah ini.',
    'aksi' => 'togglePratinjau',
])

<div class="mt-3 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

    <button type="button" wire:click="{{ $aksi }}" wire:loading.attr="disabled" wire:target="{{ $aksi }}"
        class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
               hover:bg-surface-soft dark:hover:bg-gray-800
               focus:outline-none focus:ring-1 focus:ring-gray-300">

        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-body dark:text-gray-200">
                Data yang akan dikirim
            </div>
            <div class="text-xs text-muted dark:text-gray-400">
                @if ($terbuka)
                    {{ count($baris) }} baris dibaca dari sumber yang sama dengan tombol Kirim
                @else
                    Tinjau isinya sebelum menekan Kirim
                @endif
            </div>
        </div>

        <span class="hidden text-xs text-muted sm:inline dark:text-gray-400">
            {{ $terbuka ? 'Sembunyikan' : 'Lihat detail' }}
        </span>
        <svg class="w-4 h-4 transition-transform duration-200 text-muted-soft shrink-0 {{ $terbuka ? 'rotate-180' : '' }}"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    @if ($terbuka)
        <div class="px-4 pb-4 border-t border-hairline dark:border-gray-700">
            @if (empty($baris))
                <p class="pt-3 text-xs text-muted dark:text-gray-400">{{ $kosong }}</p>
            @else
                <table class="w-full mt-3 text-xs">
                    <tbody>
                        @foreach ($baris as $satu)
                            <tr class="border-b border-hairline last:border-0 dark:border-gray-700">
                                <td class="px-1 py-1.5 align-top text-muted dark:text-gray-400 w-2/5">
                                    {{ $satu['label'] ?? '-' }}
                                </td>
                                <td class="px-1 py-1.5 align-top font-medium text-ink dark:text-gray-100">
                                    {{ $satu['nilai'] ?? '-' }}
                                    @if (!empty($satu['ket']))
                                        <span class="block font-normal text-muted-soft">{{ $satu['ket'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
