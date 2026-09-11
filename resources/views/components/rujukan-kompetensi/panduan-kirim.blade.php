{{-- resources/views/components/rujukan-kompetensi/panduan-kirim.blade.php

    Panduan pemakaian Rujukan Berbasis Kompetensi jalur FKTP (BPJS PCare +
    SISRUTE), gaya biru-info standar, default TERTUTUP.

    Jalur klinik pratama BERBEDA dari jalur rumah sakit: tidak ada SEP, tidak ada
    tahap persetujuan faskes tujuan, dan pengiriman rujukan MENYATU dengan
    pengiriman kunjungan PCare (satu panggilan postKunjungan) — bukan panggilan
    rujukan tersendiri. Itu sebabnya membatalkan rujukan ikut menghapus
    pendaftaran PCare pasien.

    Prop:
      :jalur  'pcare' (bawaan) — disediakan supaya panel lain kelak bisa memakai
              komponen yang sama tanpa menyalin isinya.
--}}

@props(['jalur' => 'pcare'])

<div x-data="{ buka: false }"
    class="overflow-hidden border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700">
    <button type="button" x-on:click="buka = !buka"
        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
        <span class="flex items-center min-w-0 gap-2">
            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="truncate">Panduan: cara mengirim rujukan sampai terkirim</span>
        </span>
        <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">

        {{-- 1. SEBELUM MULAI --}}
        <div>
            <div class="font-semibold">Sebelum mulai</div>
            <p class="mt-1">
                Rujukan Rawat Jalan dikirim lewat <span class="font-semibold">BPJS PCare</span>, yang meneruskannya
                ke SATUSEHAT. Kalau ada prasyarat yang kurang, daftarnya muncul di kotak merah di atas formulir.
            </p>
            <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                <li><span class="font-semibold">Encounter SATUSEHAT sudah dikirim</span> &mdash; lewat Daftar
                    kunjungan &rarr; menu Satu Sehat &rarr; Encounter, pada pasien yang sama.</li>
                <li><span class="font-semibold">Pendaftaran PCare sudah terkirim</span> &mdash; rujukan menempel
                    pada kunjungan PCare, jadi pendaftarannya harus lebih dulu sukses.</li>
                <li><span class="font-semibold">IHS pasien &amp; IHS dokter</span> terisi di master, dan dokter
                    punya Kode Dokter BPJS.</li>
                <li><span class="font-semibold">Diagnosa utama (ICD-10)</span> sudah dientri di EMR.</li>
            </ul>
        </div>

        {{-- 2. URUTAN LANGKAH --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Urutan langkah</div>
            <p class="mt-1">Nomor di bawah sama dengan nomor pada penanda langkah di atas formulir.</p>
            <ol class="mt-1 ml-4 space-y-1 list-decimal">
                <li>
                    <span class="font-semibold">Diagnosa &amp; Kriteria.</span> Diagnosa terisi otomatis dari
                    diagnosa utama EMR (boleh diganti lewat pencarian). Tekan
                    <span class="font-semibold">Ambil Kriteria</span> untuk menarik daftar pertanyaan dari server,
                    lalu pilih <span class="font-semibold">tepat satu</span> kriteria. Kriteria
                    &ldquo;Tindakan Medis&rdquo; wajib disertai kode ICD-9-CM.
                </li>
                <li>
                    <span class="font-semibold">Pilih Kandidat.</span> Lengkapi wilayah, spesialis &rarr;
                    subspesialis, sarana, dan estimasi tanggal rujuk; tekan
                    <span class="font-semibold">Cari Kandidat</span>, lalu pilih satu faskes dari daftar.
                    Faskes bertanda <span class="font-semibold">non-BPJS</span> tidak bisa dipilih.
                </li>
                <li>
                    <span class="font-semibold">Kirim Rujukan.</span> Pengiriman ini
                    <span class="font-semibold">sekaligus mengirim kunjungan PCare</span> dengan status pulang
                    &ldquo;Rujuk Vertikal&rdquo;. Yang terbit <span class="font-semibold">dua nomor</span>:
                    No. Rujukan PCare dan No. Rujukan SATUSEHAT.
                </li>
            </ol>
            <p class="mt-2">
                <span class="font-semibold">Tidak ada tahap persetujuan.</span> Rujukan Rawat Jalan langsung jadi
                begitu terkirim &mdash; terima/tolak hanya berlaku untuk rujukan gawat darurat &amp; rawat inap.
            </p>
        </div>

        {{-- 3. YANG DIISI PETUGAS --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Yang perlu diisi petugas</div>
            <p class="mt-1">
                Hanya <span class="font-semibold">diagnosa, kriteria, wilayah, subspesialis, sarana, tanggal, dan
                catatan</span>. Nomor kartu, kode dokter, kode poli, tanda vital, dan kode faskes klinik diambil
                sendiri dari data kunjungan.
            </p>
            <div class="mt-1 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <tbody class="align-top">
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Kriteria</td>
                            <td class="py-0.5">Harus <span class="font-semibold">tepat satu</span> yang terisi &mdash;
                                lebih dari satu ditolak server.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">ICD-9-CM</td>
                            <td class="py-0.5">Wajib bila kriterianya Tindakan Medis. Kodenya ikut menentukan
                                kandidat, jadi salah kode = daftar faskes keliru tanpa pesan error.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Wilayah</td>
                            <td class="py-0.5">Menentukan jejaring faskes yang dicari. Pilihannya datang dari
                                server bersama kriteria &mdash; bukan daftar wilayah kita sendiri.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Subspesialis &amp; Sarana</td>
                            <td class="py-0.5">Diambil dari referensi PCare. Subspesialis baru bisa dipilih
                                setelah spesialisnya dipilih.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Estimasi Tgl. Rujuk</td>
                            <td class="py-0.5">Kapan pasien direncanakan dilayani di faskes tujuan &mdash;
                                bukan jam pengiriman. Boleh hari ini; format dd/mm/yyyy.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 4. KALAU TERSENDAT --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Kalau tersendat</div>
            <div class="mt-1 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <tbody class="align-top">
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">&ldquo;Data belum siap&hellip;&rdquo;</td>
                            <td class="py-0.5">Prasyarat di kotak merah belum lengkap &mdash; paling sering
                                Encounter SATUSEHAT belum dikirim atau pendaftaran PCare belum sukses.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Kandidat kosong</td>
                            <td class="py-0.5">Bukan error. Server menilai tidak ada faskes yang perlu dituju
                                untuk kombinasi diagnosa, kriteria, subspesialis, dan wilayah itu.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Daftar kandidat hilang sendiri</td>
                            <td class="py-0.5">Memang sengaja: begitu diagnosa, kriteria, wilayah, subspesialis,
                                atau tanggal diubah, kandidat lama tidak berlaku lagi. Cari kandidat ulang.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">&ldquo;linkId&rdquo; ditolak</td>
                            <td class="py-0.5">Kriteria kedaluwarsa &mdash; kode pertanyaan berganti tiap diagnosa.
                                Tekan Ambil Kriteria lagi lalu pilih ulang.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Gangguan koneksi / kuota</td>
                            <td class="py-0.5">Isian tidak hilang &mdash; tiap langkah yang sukses tersimpan di
                                kunjungan. Ulangi tombol yang sama, jangan mengulang dari langkah awal.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Salah kirim</td>
                            <td class="py-0.5"><span class="font-semibold">Batalkan Rujukan</span> menghapus rujukan
                                <em>berikut pendaftaran PCare</em> pasien ini. Sesudahnya pendaftaran harus
                                dikirim ulang dari Daftar kunjungan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 5. ISTILAH --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Istilah</div>
            <div class="mt-1 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <tbody class="align-top">
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Kriteria</td>
                            <td class="py-0.5">Pertanyaan dari server yang menentukan alasan medis rujukan.
                                Kode pertanyaannya (linkId) berganti tiap diagnosa.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Kandidat</td>
                            <td class="py-0.5">Daftar faskes yang dinilai mampu menangani, dihitung dari diagnosa,
                                kriteria, subspesialis, sarana, dan wilayah yang dikirim.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">kdppk</td>
                            <td class="py-0.5">Kode faskes di BPJS. Dipasangkan dengan Org ID SATUSEHAT dari
                                <span class="font-semibold">baris yang sama</span>.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Encounter</td>
                            <td class="py-0.5">Data kunjungan pasien di SATUSEHAT. Seluruh langkah rujukan
                                menempel padanya.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">IHS</td>
                            <td class="py-0.5">Nomor identitas pasien/dokter di SATUSEHAT, tersimpan di master.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">ICD-10 / ICD-9-CM</td>
                            <td class="py-0.5">Kode baku diagnosa / tindakan. Pilih kode paling rinci.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
