{{-- Partial: Pertanyaan umum + hal yang masih menggantung dari sumber lapangan. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">09 — Bila Bermasalah</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Pertanyaan Umum</h1>

<div class="max-w-3xl mb-8 space-y-3">
    @foreach ([
        ['Kenapa harus memilih dari kandidat? Tidak bisa menunjuk rumah sakit langsung?',
            'Itu inti SRBK: kandidat dihitung pusat dari diagnosa + kriteria + wilayah + kompetensi faskes. Menembak faskes di luar daftar menyalahi alur, dan pasangan kode BPJS/SATUSEHAT-nya pun tidak dijamin cocok. Panel sengaja mengunci pilihan ke daftar kandidat.'],
        ['Kandidat kosong padahal payload sudah benar — kenapa?',
            'Tiga kemungkinan: (1) diagnosa dinilai masih mampu ditangani FKTP sehingga memang tidak diberi kandidat; (2) kombinasi subspesialis/wilayah/tanggal tidak ada yang cocok; (3) gangguan pusat. Coba ubah subspesialis atau perluas wilayah lebih dulu sebelum melapor.'],
        ['Diagnosa tidak punya kriteria sama sekali?',
            'Pakai ICD-10 yang lebih spesifik. Kode tiga karakter seperti A02 sering kosong sementara A02.9 jalan. Jangan diam-diam mengganti diagnosa pasien — tampilkan sarannya, keputusan tetap di dokter.'],
        ['Pasien perlu dirujuk ke IGD atau rawat inap rumah sakit lain?',
            'Di FKTP tidak ada jalur itu lewat SRBK. Alur FHIR untuk IGD/ranap milik FKRTL. Rujukan gawat darurat dari klinik ditangani sebagai rujukan emergensi biasa di luar sistem ini.'],
        ['Rujukan sudah dikirim, ternyata salah faskes. Batalkan saja?',
            'Pembatalan memakai deleteKunjungan yang menghapus SAMPAI pendaftaran PCare — bukan hanya rujukannya, dan tidak ada work-around. Setelah itu pasien harus didaftarkan ulang dari PCare. Pertimbangkan dampaknya sebelum menekan tombol batal.'],
        ['Muncul "Gagal mendapatkan nomor Rujukan Satu Sehat". Kirim ulang?',
            'Jangan. Rujukan sering sudah terbentuk dan nomornya menempel di identifier pada pesan error itu; trait sudah memeriksanya otomatis. Kirim ulang berisiko rujukan ganda atau justru menghapus pendaftaran PCare.'],
        ['Nomor rujukan wajib tampil di layar?',
            'Yang wajib adalah tersimpan di database (syarat UAT). siklik menyimpannya di node JSON + audit log, sekaligus menampilkannya di layar /rujukan/keluar dan mencetaknya di Surat Pengantar Rujukan.'],
        ['Pengiriman gagal karena gangguan pusat — isian hilang?',
            'Tidak. Setiap langkah sukses langsung dipersist ke node JSON. Buka lagi kapan pun, tinggal tekan ulang tombol terakhir.'],
        ['Boleh mengirim kunjungan lewat endpoint kunjungan PCare lama sekaligus postKunjungan?',
            'Tidak. Satu kunjungan hanya boleh lewat satu endpoint. Kunjungan yang berakhir dirujuk (kdStatusPulang "4") dikirim lewat Sisrute/postKunjungan saja.'],
        ['Kenapa layar /rujukan/keluar tidak langsung menampilkan data?',
            'Kueri menyapu kolom CLOB pada sktxn_rjhdrs. Memuatnya otomatis tiap halaman dibuka membebani Oracle tanpa diminta. Tentukan rentang tanggal dulu, lalu tekan Muat Data.'],
    ] as [$tanya, $jawab])
        <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
            <div class="mb-1 font-semibold text-gray-900 dark:text-white">{{ $tanya }}</div>
            <p class="text-sm">{{ $jawab }}</p>
        </div>
    @endforeach
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Yang masih menggantung</h2>
<p class="max-w-3xl mb-3 text-sm">
    Hal-hal berikut belum punya jawaban resmi di sumber yang tersedia. Ditulis terbuka supaya tidak ada yang mengira
    sudah pasti — perbarui halaman ini begitu katalog resmi atau jawaban grup terbit.
</p>
<div class="max-w-3xl space-y-3 text-sm">
    <div
        class="p-4 border rounded-xl border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
        <div class="mb-1 font-semibold">Body resmi <code>deleteKunjungan</code></div>
        <p>Belum ada sampel resmi. Implementasi saat ini mengirim <code>{ "noKunjungan": "…" }</code> dengan verb DELETE,
            mengikuti pola endpoint <code>kunjungan</code> PCare. Sesuaikan begitu katalognya didapat.</p>
    </div>
    <div
        class="p-4 border rounded-xl border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
        <div class="mb-1 font-semibold">Keandalan <code>jmlRujuk</code> &amp; <code>kapasitas</code></div>
        <p>Beberapa faskes melaporkan angka ini tidak berubah walau rujukan sudah masuk. Ditampilkan apa adanya sebagai
            informasi, tidak dipakai sebagai dasar penolakan atau pengurutan.</p>
    </div>
    <div
        class="p-4 border rounded-xl border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
        <div class="mb-1 font-semibold">Perilaku produksi vs dev</div>
        <p>Di dev, header <code>Content-Type</code> justru harus dilepas; produksi wajib mengirimnya. Base URL produksi
            dan kemungkinan enkripsi response mengikuti katalog Trustmark BPJS dan belum diuji dari sini.</p>
    </div>
    <div
        class="p-4 border rounded-xl border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
        <div class="mb-1 font-semibold">Rujukan lanjutan &amp; kontrol</div>
        <p>Belum jelas bagaimana rujukan SRBK berinteraksi dengan surat kontrol / rujukan ulang ke faskes yang sama.
            Sementara ini tiap rujukan diperlakukan sebagai kiriman baru per kunjungan.</p>
    </div>
</div>
