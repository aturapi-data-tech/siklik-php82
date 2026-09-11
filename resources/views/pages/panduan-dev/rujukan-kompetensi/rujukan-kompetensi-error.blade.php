{{-- Partial: Katalog error → penanganan. Sumber: docs/rujukan-kompetensi.md §6 + grup piloting. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">08 — Bila Bermasalah</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Katalog Error &rarr; Penanganan</h1>

<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    Panel menampilkan kalimat tindakan otomatis lewat <code>PcareSisruteTrait::sisruteHintKatalog()</code>.
    Bila pesan tidak dikenali, pesan asli BPJS tetap ditampilkan apa adanya — jangan disembunyikan.
</p>

<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2 w-1/3">Pesan mentah (potongan)</th>
                <th class="px-4 py-2">Artinya &amp; tindakan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ([
        ['Unauthorized! You are not registered for this service!', 'Cons ID belum terdaftar untuk <code>pcare-sisrute-rest</code>. Ajukan akses service ke KC BPJS / IT Wilayah — bukan bug aplikasi.'],
        ['Unauthorized! Consumer ID is expired!', 'Cons ID dev kedaluwarsa. Minta perpanjangan ke IT Wilayah; tidak ada yang perlu diubah di kode.'],
        ['Signature Service Tidak Sesuai', '<code>SISRUTE_CONS_ID</code> dan <code>SISRUTE_SECRET_KEY</code> tidak sepasang, atau jam server melenceng (timestamp UTC).'],
        ['Connection timed out / refused / Timeout was reached', 'IP belum di-whitelist BPJS (ITSM), URL dev pindah, atau proxy egress salah.'],
        ['timeout akses ke API Sisrute/Satu Sehat', 'BPJS tersambung, SATUSEHAT-nya yang timeout. Bukan salah isian; ulangi lalu laporkan dengan trace-id.'],
        ['hanya boleh mengisi salah satu dari Terapi, Tindakan Medis, atau Upaya Diagnosis', 'Kirim <b>tepat satu</b> item kriteria. Validasi ini diketatkan sejak Juli 2026.'],
        ['tidak mengandung Kriteria Rujukan dan Jejaring Wilayah', 'SATUSEHAT tidak punya kriteria untuk diagnosa itu. Pakai ICD-10 lebih spesifik (<code>A02.9</code>, bukan <code>A02</code>); bila tetap kosong, laporkan.'],
        ['tidak mengandung Faskes Rujukan / Data Faskes Rujukan di Sisrute Tidak ditemukan', 'Tidak ada kandidat untuk kombinasi diagnosa + subspesialis + wilayah + tanggal. Ubah salah satunya.'],
        ['Gagal mendapatkan nomor Rujukan Satu Sehat', '<b>Bug sisi pusat — rujukan mungkin sudah terbentuk.</b> JANGAN kirim ulang; periksa <code>identifier[]</code> di response lebih dulu. Kirim ulang berisiko rujukan ganda atau pendaftaran PCare terhapus.'],
        ['Pendaftaran tidak valid', 'Pendaftaran PCare sudah terhapus (umumnya efek <code>deleteKunjungan</code> atau percobaan kirim ulang). Daftarkan ulang pasien di PCare.'],
        ['Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK Dirujuk', '<code>kdppk</code> dan <code>kdppkSatuSehatTujuanRujukan</code> berasal dari faskes berbeda. Ambil keduanya dari satu baris kandidat.'],
        ['PPK Rujuk tidak ditemukan di pemetaan Satu Sehat', 'Faskes tujuan belum dipetakan BPJS&harr;SATUSEHAT di pusat. Pilih kandidat lain atau laporkan pemetaannya.'],
        ['dokter tidak valid', 'Yang divalidasi <b>kdDokter BPJS</b>, bukan kode SATUSEHAT. Lengkapi <code>kd_dr_bpjs</code> di Master Dokter.'],
        ['kodeSubSpesialis tidak valid', 'Ambil dari referensi PCare (<code>getSpesialis</code> &rarr; <code>getReferensiSubSpesialis</code>), jangan diketik manual.'],
        ['kodePropinsi ... harus 2 digit angka', 'Isi <code>"35"</code>, bukan <code>"3504"</code> atau nama provinsi.'],
        ['Format json tidak valid', 'Cek header <code>Content-Type</code> (server dev menolaknya) dan bentuk <code>kriteriaRujukan</code> (objek <code>{item:[…]}</code>).'],
        ['No Mapping Rule matched', 'Path atau verb salah di gateway — <code>deleteKunjungan</code> memakai verb DELETE.'],
        ['HTTP 429 / Rate limit quota violation', 'Kuota SATUSEHAT habis. Berhenti mengirim ulang; tunggu kuota diperpanjang.'],
    ] as [$pesan, $tindakan])
                <tr>
                    <td class="px-4 py-2 font-mono text-xs align-top">{{ $pesan }}</td>
                    <td class="px-4 py-2 align-top">{!! $tindakan !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="max-w-3xl space-y-3 text-sm">
    <div
        class="p-4 border rounded-xl border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-200">
        <div class="mb-1 font-semibold">Kesalahan paling mahal: mengirim ulang setelah &ldquo;gagal&rdquo;</div>
        <p>
            Pesan <code>Gagal mendapatkan nomor Rujukan Satu Sehat</code> sering datang bersama resource ServiceRequest
            yang <b>sudah terbentuk</b>. Nomornya menempel di <code>identifier[]</code>
            (<code>referral-number-pcare</code>, <code>referral-number-satusehat</code>) dan dibaca otomatis oleh
            <code>sisruteBacaNomorRujukan()</code>. Kirim ulang tanpa memeriksa itu bisa menghasilkan rujukan ganda,
            atau justru menghapus pendaftaran PCare sehingga percobaan berikutnya dibalas
            <code>Pendaftaran tidak valid</code>.
        </p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Error identik di dua endpoint berbeda</div>
        <p>Hampir selalu gangguan sisi pusat, bukan payload. Jangan membongkar isian — tunggu, lalu ulangi;
            state form sudah tersimpan di node JSON.</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Melapor kendala</div>
        <p>Sertakan payload + response mentah (otomatis terekam di <code>WEB_LOG_STATUS</code> — menu
            Sistem &rarr; Log BPJS API), kode faskes, cons ID, trace-id, dan waktu kejadian. Laporan masuk ke Issue
            Tracker resmi; error yang serentak dialami banyak faskes cukup dipantau di grup.</p>
    </div>
</div>
