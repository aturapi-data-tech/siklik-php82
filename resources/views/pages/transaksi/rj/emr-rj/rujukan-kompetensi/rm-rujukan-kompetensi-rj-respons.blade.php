{{-- "Respons terakhir" — jawaban mentah gateway pada panggilan terakhir.

    Default tertutup dan tidak berwarna: ini bukan pesan untuk petugas, melainkan
    bahan lapor ke BPJS/SATUSEHAT saat pusat bermasalah. Pesan yang perlu dibaca
    petugas sudah muncul sebagai toast beserta petunjuk tindakannya.

    Partial ini di-@include dari ⚡rm-rujukan-kompetensi-rj-actions.blade.php.
--}}

@if ($responsMentah !== '')
    <details class="text-sm border rounded-lg bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700">
        <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
            Respons terakhir
            <span class="font-normal text-muted dark:text-gray-400">— {{ $responsJudul }}</span>
        </summary>
        <div class="px-3 pb-3">
            <p class="mb-1 text-xs text-muted-soft">
                Salin isi ini bila perlu melapor ke BPJS/SATUSEHAT. Dipotong di 8.000 karakter.
            </p>
            <pre class="p-2 overflow-auto font-mono text-xs whitespace-pre-wrap rounded max-h-72 bg-gray-50 text-ink dark:bg-gray-900 dark:text-gray-200">{{ $responsMentah }}</pre>
        </div>
    </details>
@endif
