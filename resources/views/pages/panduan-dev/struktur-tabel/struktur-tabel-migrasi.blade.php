{{-- Partial: Riwayat Migrasi Skema & Data — merender docs/migrasi-skema-data.md (satu sumber, dirawat di repo). --}}
<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">07 — Operasional</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Riwayat Migrasi Skema &amp; Data</h1>
<p class="max-w-3xl mb-4 text-sm">
    Sumber: <code>docs/migrasi-skema-data.md</code> di repo. Tabel di bawah menyebut skrip yang harus dijalankan di
    server lain dan status per lingkungan; log eksekusi migrasi data ditulis otomatis oleh
    <code>php artisan siklik:migrasi-json-emr</code>.
</p>

<div class="md-doc">
    {!! $this->riwayatMigrasiHtml !!}
</div>
