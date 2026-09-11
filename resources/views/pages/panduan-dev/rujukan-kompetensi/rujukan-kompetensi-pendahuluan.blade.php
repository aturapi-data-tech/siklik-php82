{{-- Partial: Pendahuluan & dasar hukum. Dipanggil dari ⚡rujukan-kompetensi.blade.php --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">01 — Mulai</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Rujukan Berbasis Kompetensi (SRBK) — versi FKTP</h1>

<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    SRBK mengganti kebiasaan lama &ldquo;klinik menunjuk sendiri rumah sakit tujuan&rdquo; dengan alur
    ber-<b>kandidat</b>: sistem pusat menilai <b>diagnosa (ICD-10) + kriteria rujukan</b> yang dipilih dokter,
    lalu mengembalikan <b>daftar faskes yang dinilai kompeten</b> menangani kasus itu — lengkap dengan kode
    <code>kdppk</code>, strata, kelas, jarak, jadwal praktik, dan kapasitas. Klinik tinggal memilih satu
    dari kandidat tersebut. Nomor rujukan diterbitkan pusat, bukan dikarang SIM.
</p>

<div class="px-4 py-3 mb-6 text-sm border rounded-lg bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-900/20 dark:border-emerald-900/50 dark:text-emerald-200">
    <b>Prinsip nomor satu.</b> Tujuan rujukan hanya boleh diambil dari daftar kandidat yang dikembalikan
    <code>GetFaskesRujukan</code>. <code>kdppk</code> dan <code>kodeFaskesSatuSehat</code> wajib berasal dari
    <b>baris yang sama</b> — memasangkan kode dari dua baris berbeda ditolak pusat dengan pesan
    &ldquo;Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK Dirujuk&rdquo;.
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Dasar hukum &amp; status penerapan</h2>
<div class="max-w-3xl mb-6 space-y-3 text-sm">
    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Permenkes 16/2024 — Sistem Rujukan Terintegrasi</div>
        <p>
            Mengatur rujukan berjenjang berbasis kompetensi dan rujukan elektronik. <b>Pasal 17</b> menyebut isi
            minimal surat rujukan elektronik: identitas pasien, identitas fasyankes &amp; unit layanan penerima,
            rekam medis (resume klinis), dan alasan rujukan; surat itu <b>dapat dicetak</b> sesuai kebutuhan pasien.
            Format cetak di siklik (Surat Pengantar Rujukan + Resume Klinis) mengikuti pasal ini.
        </p>
    </div>
    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Surat himbauan 13 Mei 2026</div>
        <p>
            Kemkes &amp; BPJS Kesehatan mengedarkan himbauan agar fasyankes dan vendor SIM segera menyiapkan
            bridging SRBK — pengajuan kredensial, uji coba di lingkungan dev, lalu UAT. Sejak itu grup resmi
            (SATUSEHAT Rujukan &times; PCare &times; VClaim) menjadi kanal tanya-jawab teknis dan pelaporan kendala.
        </p>
    </div>
    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Piloting empat wilayah</div>
        <p class="mb-2">
            Penerapan awal dibatasi pada empat wilayah. Klinik ini berada di salah satunya, karena itu
            pengajuan kredensial diarahkan ke Kantor Cabang setempat.
        </p>
        <ul class="space-y-1 list-disc list-inside">
            <li>Kota Bandung</li>
            <li>Kota Makassar</li>
            <li><b>Kabupaten Tulungagung</b> — wilayah klinik ini</li>
            <li>Kabupaten Muara Enim</li>
        </ul>
    </div>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Cakupan di siklik</h2>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
    <table class="min-w-full text-sm">
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="w-56 px-4 py-2 font-semibold whitespace-nowrap">Jalur pelayanan</td>
                <td class="px-4 py-2">Rawat Jalan saja — siklik adalah klinik pratama, tidak ada UGD 24 jam
                    maupun rawat inap.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold whitespace-nowrap">Arah rujukan</td>
                <td class="px-4 py-2">Keluar saja (FKTP &rarr; FKRTL). Tidak ada layar &ldquo;rujukan masuk&rdquo;:
                    klinik pratama tidak menerima rujukan berjenjang.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold whitespace-nowrap">Persetujuan faskes tujuan</td>
                <td class="px-4 py-2">Tidak ada. Untuk rawat jalan rujukan terbit seketika ketika
                    <code>postKunjungan</code> berhasil — tidak ada tahap terima/tolak seperti rujukan ranap FKRTL.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold whitespace-nowrap">Pembawa pesan ke SATUSEHAT</td>
                <td class="px-4 py-2"><b>BPJS</b>. Klinik tidak mengirim bundle FHIR sendiri.</td>
            </tr>
        </tbody>
    </table>
</div>
