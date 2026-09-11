<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Support\Bpjs\BpjsHttp;

/**
 * SISRUTE FKTP — Rujukan Berbasis Kompetensi Layanan (SRBK) lewat gateway
 * `pcare-sisrute-rest` BPJS Kesehatan. Klinik pratama, jalur RAWAT JALAN saja.
 *
 * BEDA DENGAN SIRUS (FKRTL): rumah sakit memakai gateway `vclaim-sisrute-rest`
 * dengan path `Rujukan/GetKriteriaRujukan|GetFaskesRujukan|Insert|Delete`.
 * FKTP memakai path `Sisrute/GetKriteriaRujukan`, `Sisrute/GetFaskesRujukan`,
 * `Sisrute/postKunjungan`, `Sisrute/deleteKunjungan` — dan rujukannya menumpang
 * pada payload KUNJUNGAN PCare, bukan pada objek t_rujukan seperti FKRTL.
 *
 * Alurnya: SIM klinik → BPJS → SATUSEHAT. Kita TIDAK mengirim bundle FHIR sendiri;
 * BPJS yang membuat CarePlan + ServiceRequest di SATUSEHAT dan mengembalikan
 * No. Rujukan PCare + No. Rujukan SATUSEHAT.
 *
 * Aturan payload, katalog error, dan jebakan: docs/rujukan-kompetensi.md
 * serta .claude/skills/rujukan-kompetensi/SKILL.md.
 *
 * Dipakai dengan `use PcareSisruteTrait;` di komponen Livewire, berdampingan
 * dengan PcareTrait — karena itu SEMUA nama method di sini diawali `sisrute`
 * supaya tidak bertabrakan dengan signature()/stringDecrypt()/sendResponse()
 * milik PcareTrait.
 *
 * Semua method publik mengembalikan ARRAY (bukan response()->json) dengan bentuk:
 *   ['ok' => bool, 'code' => int, 'message' => string, 'response' => array|null, 'raw' => string]
 */
trait PcareSisruteTrait
{
    // =====================================================================
    // 1. ENDPOINT — empat panggilan SRBK FKTP
    // =====================================================================

    /**
     * Langkah 1: kemampuan layanan (kriteria rujukan) untuk satu diagnosa ICD-10.
     *
     * PENTING: `linkId` yang dibalas DINAMIS per diagnosa (dan bisa berubah per
     * pemanggilan). Jangan di-cache lintas diagnosa — linkId yang dipakai di
     * langkah 3 & 5 WAJIB berasal dari response langkah 1 yang sama.
     *
     * @param string $kodeDiagnosa ICD-10, mis. "I10" atau "A02.9"
     * @param string $encounterId  UUID Encounter SATUSEHAT (tanpa prefix "Encounter/")
     */
    public function sisruteGetKriteriaRujukan(string $kodeDiagnosa, string $encounterId): array
    {
        $kodeDiagnosa = trim($kodeDiagnosa);
        $encounterId = $this->sisruteUuidEncounter($encounterId);

        if ($kodeDiagnosa === '') {
            return $this->sisruteGagalIsian('Kode diagnosa (ICD-10) wajib diisi.');
        }
        if ($encounterId === '') {
            return $this->sisruteGagalIsian('Encounter SATUSEHAT belum terkirim — kirim Encounter dulu sebelum merujuk.');
        }

        $badan = [
            'kodeFaskesSatuSehat' => $this->sisruteKodeFaskes(),
            'kodeDiagnosa' => $kodeDiagnosa,
            // Langkah 1 & 3 memakai reference BERPREFIX "Encounter/".
            'encounter' => ['reference' => 'Encounter/' . $encounterId],
        ];

        return $this->sisruteKirim('POST', 'Sisrute/GetKriteriaRujukan', $badan, 'get-kriteria-rujukan');
    }

    /**
     * Langkah 3: daftar kandidat faskes tujuan.
     *
     * $isian (kodeFaskesSatuSehat & encounter diisi trait ini):
     *   kodeSubSpesialis   string  kode subspesialis PCare (getReferensiSubSpesialis)
     *   kodeSarana         string  kode sarana PCare (getSarana); boleh "" bila tidak perlu
     *   kodeDiagnosa       string  ICD-10 yang sama dengan langkah 1
     *   estimasiRujuk      string  "DD-MM-YYYY" (BUKAN Y-m-d)
     *   kriteriaRujukan    array   ['item' => [ {linkId, text, answer:[...]} ]] — TEPAT SATU item terisi
     *   codeJejaringWilayah array  {kodePropinsi (2 digit), namaPropinsi, kodeKabupaten, namaKabupaten}
     *   encounterId        string  UUID Encounter (dipakai untuk menyusun encounter.reference)
     */
    public function sisruteGetFaskesRujukan(array $isian): array
    {
        $encounterId = $this->sisruteUuidEncounter((string) ($isian['encounterId'] ?? ''));
        if ($encounterId === '') {
            return $this->sisruteGagalIsian('Encounter SATUSEHAT belum terkirim — kirim Encounter dulu sebelum merujuk.');
        }

        $kodeDiagnosa = trim((string) ($isian['kodeDiagnosa'] ?? ''));
        if ($kodeDiagnosa === '') {
            return $this->sisruteGagalIsian('Kode diagnosa (ICD-10) wajib diisi.');
        }

        $kriteriaRujukan = $isian['kriteriaRujukan'] ?? [];
        $daftarKriteria = $kriteriaRujukan['item'] ?? [];
        if (!is_array($daftarKriteria) || count($daftarKriteria) < 1) {
            return $this->sisruteGagalIsian('Pilih satu kriteria rujukan (Terapi / Tindakan Medis / Upaya Diagnosis).');
        }

        $wilayah = $isian['codeJejaringWilayah'] ?? [];
        $kodePropinsi = trim((string) ($wilayah['kodePropinsi'] ?? ''));
        if (!preg_match('/^[0-9]{2}$/', $kodePropinsi)) {
            return $this->sisruteGagalIsian('Kode propinsi harus 2 digit angka (mis. 35 untuk Jawa Timur).');
        }

        $estimasiRujuk = trim((string) ($isian['estimasiRujuk'] ?? ''));
        if (!preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}$/', $estimasiRujuk)) {
            return $this->sisruteGagalIsian('Estimasi tanggal rujuk harus berformat DD-MM-YYYY.');
        }

        $badan = [
            'kodeFaskesSatuSehat' => $this->sisruteKodeFaskes(),
            'kodeSubSpesialis' => (string) ($isian['kodeSubSpesialis'] ?? ''),
            'kodeSarana' => (string) ($isian['kodeSarana'] ?? ''),
            'kodeDiagnosa' => $kodeDiagnosa,
            'estimasiRujuk' => $estimasiRujuk,
            'kriteriaRujukan' => ['item' => array_values($daftarKriteria)],
            'codeJejaringWilayah' => [
                'kodePropinsi' => $kodePropinsi,
                'namaPropinsi' => (string) ($wilayah['namaPropinsi'] ?? ''),
                'kodeKabupaten' => (string) ($wilayah['kodeKabupaten'] ?? ''),
                'namaKabupaten' => (string) ($wilayah['namaKabupaten'] ?? ''),
            ],
            'encounter' => ['reference' => 'Encounter/' . $encounterId],
        ];

        return $this->sisruteKirim('POST', 'Sisrute/GetFaskesRujukan', $badan, 'get-faskes-rujukan');
    }

    /**
     * Langkah 5: kirim kunjungan PCare + rujukan SRBK sekaligus.
     *
     * $kunjungan       payload kunjungan PCare biasa (buildKunjunganPayload) —
     *                  kdStatusPulang WAJIB "4" (dirujuk); dipaksa di sini.
     * $rujukLanjut     {tglEstRujuk "DD-MM-YYYY", kdppk, subSpesialis:{kdSubSpesialis1, kdSarana}, khusus:null}
     * $satuSehatRujukan {kodeFaskesSatuSehat, idPasienSatuSehat, kdppkSatuSehatTujuanRujukan,
     *                    kdDokterSatuSehat, encounter:{reference:"<uuid polos>"}, patientInstruction,
     *                    kriteriaRujukan:{item:[ satu item ]}, keteranganRujukan, codeJejaringWilayah}
     *
     * Kunjungan NON-rujuk TETAP lewat endpoint `kunjungan` lama (PcareTrait::addKunjungan).
     * Jangan mengirim dua-duanya untuk satu kunjungan yang sama.
     */
    public function sisrutePostKunjungan(array $kunjungan, array $rujukLanjut, array $satuSehatRujukan): array
    {
        if (($kunjungan['noKartu'] ?? '') === '') {
            return $this->sisruteGagalIsian('No. Kartu BPJS peserta wajib diisi.');
        }
        if (trim((string) ($kunjungan['kdDokter'] ?? '')) === '') {
            return $this->sisruteGagalIsian('Kode dokter BPJS (kdDokter) wajib diisi — BPJS menolak dengan "dokter tidak valid" bila kosong/keliru.');
        }
        if (trim((string) ($rujukLanjut['kdppk'] ?? '')) === '') {
            return $this->sisruteGagalIsian('PPK tujuan (kdppk) wajib dipilih dari daftar kandidat.');
        }
        if (trim((string) ($satuSehatRujukan['kdppkSatuSehatTujuanRujukan'] ?? '')) === '') {
            return $this->sisruteGagalIsian('Kode SATUSEHAT faskes tujuan wajib diambil dari baris kandidat yang sama dengan kdppk.');
        }

        // encounter di langkah 5 memakai UUID POLOS (tanpa "Encounter/") — beda dengan
        // langkah 1 & 3. Lihat sampel "payloadRujukan.txt" & "response simulasi 03092026.json".
        $satuSehatRujukan['encounter'] = [
            'reference' => $this->sisruteUuidEncounter((string) ($satuSehatRujukan['encounter']['reference'] ?? '')),
        ];
        $satuSehatRujukan['kodeFaskesSatuSehat'] = (string) ($satuSehatRujukan['kodeFaskesSatuSehat'] ?? '') ?: $this->sisruteKodeFaskes();
        // Prefix "Organization/" hanya ada di RESPONSE GetFaskesRujukan — dibuang saat dikirim balik.
        $satuSehatRujukan['kdppkSatuSehatTujuanRujukan'] = $this->sisruteOrgIdPolos((string) $satuSehatRujukan['kdppkSatuSehatTujuanRujukan']);

        $badan = $kunjungan;
        $badan['kdStatusPulang'] = '4';
        $badan['rujukLanjut'] = $rujukLanjut;
        $badan['satuSehatRujukan'] = $satuSehatRujukan;

        return $this->sisruteKirim('POST', 'Sisrute/postKunjungan', $badan, 'post-kunjungan');
    }

    /**
     * Batal rujuk. PERINGATAN: endpoint ini menghapus SAMPAI PENDAFTARAN PCare,
     * bukan cuma rujukannya (konfirmasi BPJS 20 Mei 2026). Tidak ada work-around
     * "batal rujuk tapi kunjungan tetap ada". Sesudah dihapus, pembuatan ulang
     * harus mulai dari pendaftaran PCare lagi.
     */
    public function sisruteDeleteKunjungan(string $noKunjungan): array
    {
        $noKunjungan = trim($noKunjungan);
        if ($noKunjungan === '') {
            return $this->sisruteGagalIsian('No. Kunjungan PCare wajib diisi untuk membatalkan rujukan.');
        }

        // Verb DELETE + body {"noKunjungan": ...} — mengikuti pola kunjungan PCare
        // (yang memakai verb DELETE) dan pola Rujukan/Delete FKRTL yang juga berbadan.
        return $this->sisruteKirim('DELETE', 'Sisrute/deleteKunjungan', ['noKunjungan' => $noKunjungan], 'delete-kunjungan');
    }

    // =====================================================================
    // 2. KATALOG PESAN ERROR → TINDAKAN
    // =====================================================================

    /**
     * Menerjemahkan pesan mentah BPJS/SATUSEHAT menjadi tindakan yang harus
     * dilakukan petugas. Dikumpulkan dari grup WhatsApp piloting SRBK
     * (Apr–Sep 2026); lihat docs/rujukan-kompetensi.md §Katalog error.
     *
     * Mengembalikan string kosong bila pesan tidak dikenali — pemanggil tetap
     * menampilkan pesan asli dari BPJS, jangan disembunyikan.
     */
    public function sisruteHintKatalog(string $pesanMentah): string
    {
        $pesan = trim($pesanMentah);
        if ($pesan === '') {
            return '';
        }

        // Kunci dicocokkan case-insensitive sebagai substring; urutan = prioritas
        // (yang lebih spesifik lebih dulu).
        $katalog = [
            'not registered for this service' =>
                'Cons ID ini belum terdaftar untuk layanan pcare-sisrute-rest. Ajukan akses service SRBK ke IT Wilayah / KC BPJS setempat (KC Tulungagung) — cons ID PCare biasa tidak otomatis berlaku.',

            'consumer id is expired' =>
                'Cons ID sudah kedaluwarsa. Minta perpanjangan ke IT Wilayah BPJS; tidak ada yang perlu diubah di aplikasi.',

            'signature service tidak sesuai' =>
                'Signature tidak cocok: pastikan SISRUTE_CONS_ID dan SISRUTE_SECRET_KEY sepasang (bukan campuran kredensial PCare lama) dan jam server tepat (timestamp UTC).',

            'connection timed out' =>
                'Tidak tersambung ke gateway BPJS. Umumnya IP publik klinik belum di-whitelist BPJS (pengajuan lewat ITSM) — atau server dev sedang tumbang. Cek juga BPJS_PROXY_AKTIF/BPJS_PROXY_URL.',
            'connection refused' =>
                'Koneksi ditolak gateway BPJS. Umumnya IP publik belum di-whitelist (ITSM) atau URL dev sedang dipindah. Cek SISRUTE_URL dan whitelist IP.',
            'timeout was reached' =>
                'Permintaan kehabisan waktu sebelum tersambung. Cek whitelist IP (ITSM) dan status server dev BPJS sebelum mengulang.',
            'timeout akses ke api sisrute' =>
                'BPJS berhasil dihubungi tapi jalur ke SATUSEHAT-nya yang timeout. Bukan salah isian — tunggu beberapa menit lalu ulangi; bila berulang, laporkan ke Issue Tracker BPJS dengan trace-id.',

            'hanya boleh mengisi salah satu' =>
                'Kriteria rujukan harus TEPAT SATU: Terapi ATAU Tindakan Medis ATAU Upaya Diagnosis. Kosongkan dua sisanya (jangan dikirim sebagai item terisi).',

            'tidak mengandung kriteria rujukan dan jejaring wilayah' =>
                'SATUSEHAT tidak mengembalikan kriteria untuk diagnosa ini. Coba kode ICD-10 yang lebih spesifik (mis. A02.9, bukan A02). Bila tetap kosong, diagnosa ini belum dipetakan — laporkan ke SATUSEHAT.',

            'tidak mengandung faskes rujukan' =>
                'Tidak ada kandidat faskes untuk kombinasi diagnosa + subspesialis + wilayah + tanggal ini. Ubah wilayah/subspesialis/tanggal, atau laporkan ke SATUSEHAT bila seharusnya ada.',

            'data faskes rujukan di sisrute tidak ditemukan' =>
                'Jejaring wilayah yang dipilih belum punya faskes rujukan terdaftar di Sisrute. Coba kabupaten/propinsi lain, atau laporkan ke SATUSEHAT.',

            'gagal mendapatkan nomor rujukan satu sehat' =>
                'BUG SISI BPJS/SATUSEHAT: rujukan MUNGKIN SUDAH TERBENTUK meski dilaporkan gagal. JANGAN langsung kirim ulang — periksa dulu identifier di response (referral-number-pcare / servicerequest). Bila nomor sudah ada, simpan nomor itu; kirim ulang berisiko rujukan ganda atau pendaftaran PCare terhapus.',

            'pendaftaran tidak valid' =>
                'Pendaftaran PCare untuk kunjungan ini sudah tidak ada (biasanya karena deleteKunjungan atau percobaan kirim ulang). Daftarkan ulang pasien di PCare sebelum mengirim rujukan.',

            'satu sehat tujuan rujukan tidak sesuai dengan ppk dirujuk' =>
                'kdppk (BPJS) dan kdppkSatuSehatTujuanRujukan (Org ID SATUSEHAT) berasal dari faskes berbeda. Keduanya WAJIB dari satu baris kandidat yang sama di hasil GetFaskesRujukan.',

            'ppk rujuk tidak ditemukan di pemetaan' =>
                'Faskes tujuan ini belum terpetakan BPJS↔SATUSEHAT di sisi pusat. Pilih kandidat rumah sakit lain; bila tujuannya wajib, laporkan pemetaannya ke BPJS/SATUSEHAT.',
            'ppk dirujuk tidak ditemukan' =>
                'Faskes tujuan ini belum terpetakan BPJS↔SATUSEHAT di sisi pusat. Pilih kandidat rumah sakit lain atau laporkan pemetaannya ke BPJS/SATUSEHAT.',

            'dokter tidak valid' =>
                'Kode dokter BPJS (kdDokter) tidak dikenal di faskes ini. Yang divalidasi bukan kode SATUSEHAT-nya — lengkapi kode dokter BPJS pada master dokter (PCare getDokter).',

            'kodesubspesialis tidak valid' =>
                'Kode subspesialis tidak dikenal. Ambil dari referensi PCare (getSpesialis → getReferensiSubSpesialis), jangan diketik manual.',

            'kodepropinsi' =>
                'kodePropinsi pada codeJejaringWilayah harus 2 digit angka (mis. "35"), diambil dari JejaringWilayah hasil GetKriteriaRujukan.',

            'format json tidak valid' =>
                'Bentuk payload ditolak gateway. Cek Content-Type (server DEV dvlp MENOLAK bila Content-Type dikirim) dan pastikan kriteriaRujukan berupa objek {item:[…]}, bukan array polos.',

            'no mapping rule matched' =>
                'Path/verb HTTP salah di gateway BPJS. Pastikan endpoint FKTP (Sisrute/…) dan verb-nya benar — deleteKunjungan memakai verb DELETE, bukan POST.',

            'rate limit quota violation' =>
                'Kuota permintaan SATUSEHAT habis (HTTP 429). Hentikan pengiriman berulang, tunggu kuota diperpanjang, lalu ulangi. Laporkan ke SATUSEHAT bila kuota harian belum wajar terpakai.',
            'quota limit' =>
                'Kuota permintaan SATUSEHAT habis (HTTP 429). Hentikan pengiriman berulang, tunggu kuota diperpanjang, lalu ulangi.',
        ];

        $pesanKecil = mb_strtolower($pesan);
        foreach ($katalog as $kunci => $tindakan) {
            if (str_contains($pesanKecil, $kunci)) {
                return $tindakan;
            }
        }

        // HTTP 429 kadang datang tanpa teks kuota sama sekali.
        if (str_contains($pesanKecil, '429') || str_contains($pesanKecil, 'too many requests')) {
            return 'Kuota/rate limit terlampaui (HTTP 429). Berhenti mengirim ulang, tunggu, lalu coba lagi.';
        }

        return '';
    }

    // =====================================================================
    // 3. PEMBACA RESPONSE — membantu pemanggil, tidak memanggil jaringan
    // =====================================================================

    /**
     * Mengambil nomor & jejak rujukan dari hasil sisrutePostKunjungan().
     *
     * Dua bentuk response yang pernah terlihat:
     *  a. Sukses gateway FKTP  : {noKunjungan, serviceRequestId, noRujukanSatuSehat}
     *  b. Gagal "Gagal mendapatkan nomor Rujukan Satu Sehat" : pesan memuat ServiceRequest
     *     FHIR lengkap; nomor PCare ada di identifier system .../referral-number-pcare,
     *     nomor SATUSEHAT di .../referral-number-satusehat, trace-id di meta.tag.
     *
     * Karena itu nomor DICARI JUGA di `raw` — rujukan yang sudah terbentuk tidak
     * boleh hilang cuma karena BPJS melaporkannya sebagai gagal.
     *
     * @return array{noKunjunganPcare:string, noRujukanPcare:string, noRujukanSatuSehat:string, serviceRequestId:string, traceId:string}
     */
    public function sisruteBacaNomorRujukan(array $hasil): array
    {
        $response = is_array($hasil['response'] ?? null) ? $hasil['response'] : [];
        $raw = (string) ($hasil['raw'] ?? '');

        $nomor = [
            'noKunjunganPcare' => (string) ($response['noKunjungan'] ?? ''),
            'noRujukanPcare' => (string) ($response['noRujukanPcare'] ?? ''),
            'noRujukanSatuSehat' => (string) ($response['noRujukanSatuSehat'] ?? ''),
            'serviceRequestId' => (string) ($response['serviceRequestId'] ?? ''),
            'traceId' => '',
        ];

        // Identifier FHIR yang menempel di pesan error / response mentah.
        $pola = [
            'noRujukanPcare' => 'referral-number-pcare',
            'noRujukanSatuSehat' => 'referral-number-satusehat',
        ];
        foreach ($pola as $kunci => $sistem) {
            if ($nomor[$kunci] !== '') {
                continue;
            }
            if (preg_match('~' . preg_quote($sistem, '~') . '\\\\?"\s*,\s*\\\\?"value\\\\?"\s*:\s*\\\\?"([^"\\\\]+)~i', $raw, $cocok)) {
                $nomor[$kunci] = $cocok[1];
            }
        }

        if ($nomor['serviceRequestId'] === '' && preg_match('~sys-ids\.kemkes\.go\.id/servicerequest/[0-9]+\\\\?"\s*,\s*\\\\?"value\\\\?"\s*:\s*\\\\?"([^"\\\\]+)~i', $raw, $cocok)) {
            $nomor['serviceRequestId'] = $cocok[1];
        }

        if (preg_match('~terminology\.kemkes\.go\.id/trace-id\\\\?"\s*,\s*\\\\?"code\\\\?"\s*:\s*\\\\?"([^"\\\\]+)~i', $raw, $cocok)) {
            $nomor['traceId'] = $cocok[1];
        }

        // No. Rujukan PCare = No. Kunjungan PCare (BPJS memakai nomor yang sama;
        // dikonfirmasi faskes piloting 21 Agu 2026). Pakai itu bila identifier
        // referral-number-pcare tidak ikut dikirim.
        if ($nomor['noRujukanPcare'] === '') {
            $nomor['noRujukanPcare'] = $nomor['noKunjunganPcare'];
        }

        return $nomor;
    }

    /**
     * Meratakan JejaringWilayah (bentuk Questionnaire FHIR) dari hasil
     * sisruteGetKriteriaRujukan() menjadi dua daftar datar siap dipakai <select>.
     *
     * Bentuk aslinya: JejaringWilayah[0].item[] → linkId "1.1" Provinsi (answerOption[])
     * dan "1.2" Kabupaten/Kota (answerOption[]). Kode kabupaten berawalan kode propinsi.
     *
     * @return array{propinsiList: array<int, array{kode:string, nama:string}>, kabupatenList: array<int, array{kode:string, nama:string, kodePropinsi:string}>}
     */
    public function sisruteWilayahDariKriteria(array $response): array
    {
        $propinsiList = [];
        $kabupatenList = [];

        foreach ((array) ($response['JejaringWilayah'] ?? $response['jejaringWilayah'] ?? []) as $grup) {
            foreach ((array) ($grup['item'] ?? []) as $pertanyaan) {
                $linkId = (string) ($pertanyaan['linkId'] ?? '');
                foreach ((array) ($pertanyaan['answerOption'] ?? []) as $pilihan) {
                    $kode = trim((string) ($pilihan['valueCoding']['code'] ?? ''));
                    $nama = trim((string) ($pilihan['valueCoding']['display'] ?? ''));
                    if ($kode === '') {
                        continue;
                    }
                    if ($linkId === '1.1') {
                        $propinsiList[] = ['kode' => $kode, 'nama' => $nama];
                    } elseif ($linkId === '1.2') {
                        $kabupatenList[] = ['kode' => $kode, 'nama' => $nama, 'kodePropinsi' => substr($kode, 0, 2)];
                    }
                }
            }
        }

        return ['propinsiList' => $propinsiList, 'kabupatenList' => $kabupatenList];
    }

    // =====================================================================
    // 4. TRANSPORT — signature, kirim, decrypt, log
    // =====================================================================

    /**
     * Header BPJS pola PCare. X-authorization IKUT DIKIRIM: pcare-sisrute-rest
     * mewarisi kontrak header PCare (username/password faskes + kode aplikasi 095);
     * gateway rujukan resmi pun mengirim username & password di dataheader-nya.
     *
     * `decrypt_key` sengaja TIDAK ikut sebagai header (beda dengan PcareTrait lama)
     * — dikembalikan terpisah lewat parameter $kunciDecrypt.
     */
    private function sisruteHeader(?string &$kunciDecrypt = null): array
    {
        $consId = (string) config('bpjs.sisrute.cons_id');
        $secretKey = (string) config('bpjs.sisrute.secret_key');
        $userKey = (string) config('bpjs.sisrute.user_key');

        // Timestamp BPJS = detik UTC sejak epoch.
        $tStamp = (string) (Carbon::now('UTC')->timestamp);
        $signature = base64_encode(hash_hmac('sha256', $consId . '&' . $tStamp, $secretKey, true));

        $kunciDecrypt = $consId . $secretKey . $tStamp;

        $header = [
            'X-cons-id' => $consId,
            'X-timestamp' => $tStamp,
            'X-signature' => $signature,
            'X-authorization' => 'Basic ' . base64_encode(
                config('bpjs.sisrute.username') . ':' . config('bpjs.sisrute.password') . ':' . config('bpjs.sisrute.kd_aplikasi')
            ),
            'user_key' => $userKey,
        ];

        // Server DEV (dvlp) MENOLAK permintaan yang membawa Content-Type
        // (info BPJS 11 Jun 2026); produksi tetap application/json.
        $contentType = trim((string) config('bpjs.sisrute.content_type', ''));
        if ($contentType !== '') {
            $header['Content-Type'] = $contentType;
        }

        return $header;
    }

    /** Dekripsi response terenkripsi BPJS: AES-256-CBC lalu LZString. Sama persis dengan PCare/VClaim. */
    private function sisruteStringDecrypt(string $kunci, string $teks): string
    {
        $kunciHash = hex2bin(hash('sha256', $kunci));
        $iv = substr(hex2bin(hash('sha256', $kunci)), 0, 16);
        $keluaran = openssl_decrypt(base64_decode($teks), 'AES-256-CBC', $kunciHash, OPENSSL_RAW_DATA, $iv);

        if ($keluaran === false) {
            return '';
        }

        return (string) \LZCompressor\LZString::decompressFromEncodedURIComponent($keluaran);
    }

    /**
     * Satu pintu keluar untuk keempat endpoint: simulasi / kirim / baca / catat.
     */
    private function sisruteKirim(string $verb, string $jalur, array $badan, string $namaFixture): array
    {
        $url = rtrim((string) config('bpjs.sisrute.url'), '/') . '/' . $jalur;

        if ((bool) config('bpjs.sisrute.simulasi', false)) {
            return $this->sisruteDariFixture($namaFixture, $url, $badan);
        }

        $mulai = microtime(true);

        try {
            $kunciDecrypt = null;
            $header = $this->sisruteHeader($kunciDecrypt);

            $permintaan = BpjsHttp::mulai()
                ->timeout(max(1, (int) config('bpjs.sisrute.timeout', 20)))
                ->withHeaders($header);

            $response = $verb === 'DELETE'
                ? $permintaan->delete($url, $badan)
                : $permintaan->post($url, $badan);

            $raw = (string) $response->body();
            $lama = $response->transferStats?->getTransferTime() ?? (microtime(true) - $mulai);

            // Gateway membalas {metaData:{code,message}, response:…}. Bila metaData
            // tidak ada (halaman HTML error / gangguan upstream), pakai status HTTP.
            $kode = $response->json('metaData.code');
            $pesan = (string) ($response->json('metaData.message') ?? $response->reason() ?? '');

            if ($kode === null) {
                $kode = $response->status();
                if ($pesan === '') {
                    $pesan = 'Gagal menghubungkan ke BPJS (' . $url . ')';
                }
            }

            $kode = (int) $kode;
            $isi = $response->json('response');

            if (($kode === 200 || $kode === 201) && is_string($isi) && $isi !== '') {
                // Produksi mengirim terenkripsi; dev kerap mengirim JSON polos.
                $terbuka = $this->sisruteStringDecrypt((string) $kunciDecrypt, $isi);
                $isi = $terbuka !== '' ? json_decode($terbuka, true) : json_decode($isi, true);
            }

            $hasil = $this->sisruteHasil(
                $kode === 200 || $kode === 201,
                $kode,
                $pesan !== '' ? $pesan : 'OK',
                is_array($isi) ? $isi : null,
                $raw
            );

            $this->sisruteCatatLog($hasil, $url, $badan, $lama);

            return $hasil;
        } catch (Exception $kesalahan) {
            $hasil = $this->sisruteHasil(false, 408, $kesalahan->getMessage(), null, '');
            $this->sisruteCatatLog($hasil, $url, $badan, microtime(true) - $mulai);

            return $hasil;
        }
    }

    /**
     * Mode simulasi: jawaban dari database/fixtures/sisrute/*.json, tanpa jaringan.
     * Tetap dicatat ke web_log_status supaya alur latihan bisa ditelusuri — ditandai [SIMULASI].
     */
    private function sisruteDariFixture(string $namaFixture, string $url, array $badan): array
    {
        $berkas = database_path('fixtures/sisrute/' . $namaFixture . '.json');

        if (!is_file($berkas)) {
            $hasil = $this->sisruteHasil(false, 500, 'Fixture simulasi tidak ditemukan: ' . $berkas, null, '');
            $this->sisruteCatatLog($hasil, $url, $badan, 0.0, true);

            return $hasil;
        }

        $raw = (string) file_get_contents($berkas);
        $isi = json_decode($raw, true);

        if (!is_array($isi)) {
            $hasil = $this->sisruteHasil(false, 500, 'Fixture simulasi bukan JSON yang sah: ' . $berkas, null, $raw);
            $this->sisruteCatatLog($hasil, $url, $badan, 0.0, true);

            return $hasil;
        }

        // Fixture menyimpan amplop gateway {metaData:{code,message}, response:{…}}.
        $kode = (int) ($isi['metaData']['code'] ?? 200);
        $pesan = (string) ($isi['metaData']['message'] ?? 'OK');
        $data = $isi['response'] ?? null;

        $hasil = $this->sisruteHasil(
            $kode === 200 || $kode === 201,
            $kode,
            $pesan,
            is_array($data) ? $data : null,
            $raw
        );
        $this->sisruteCatatLog($hasil, $url, $badan, 0.0, true);

        return $hasil;
    }

    /** Bentuk balikan baku seluruh method publik trait ini. */
    private function sisruteHasil(bool $ok, int $code, string $message, ?array $response, string $raw): array
    {
        return [
            'ok' => $ok,
            'code' => $code,
            'message' => $message,
            'response' => $response,
            'raw' => $raw,
        ];
    }

    /** Gagal sebelum menyentuh jaringan (isian belum lengkap) — tidak perlu dicatat sebagai panggilan API. */
    private function sisruteGagalIsian(string $pesan): array
    {
        return $this->sisruteHasil(false, 400, $pesan, null, '');
    }

    /**
     * Catat payload + response mentah ke WEB_LOG_STATUS.
     *
     * CATATAN: tabel WEB_LOG_STATUS siklik TIDAK punya kolom http_payload (berbeda
     * dengan sirus) — payload karena itu ikut disimpan DI DALAM kolom `response`
     * pada key `payload`, bukan dibuang.
     */
    private function sisruteCatatLog(array $hasil, string $url, array $badan, float $lama, bool $simulasi = false): void
    {
        $catatan = [
            'response' => $hasil['response'] ?? $hasil['raw'],
            'payload' => $badan,
            'metadata' => [
                'message' => ($simulasi ? '[SIMULASI] ' : '') . $hasil['message'],
                'code' => $hasil['code'],
            ],
        ];

        try {
            DB::table('web_log_status')->insert([
                'code' => $hasil['code'],
                'date_ref' => Carbon::now(config('app.timezone')),
                'response' => json_encode($catatan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'http_req' => ($simulasi ? '[SIMULASI] ' : '') . $url,
                'requestTransferTime' => round($lama, 3),
            ]);
        } catch (Exception $kesalahan) {
            // Log adalah bukti, bukan syarat: kegagalan menulis log tidak boleh
            // menggagalkan rujukan yang sudah terkirim ke BPJS.
            report($kesalahan);
        }
    }

    // =====================================================================
    // 5. UTILITAS KECIL
    // =====================================================================

    /** Org ID SATUSEHAT klinik ini; payload SRBK memakainya TANPA prefix "Organization/". */
    private function sisruteKodeFaskes(): string
    {
        return $this->sisruteOrgIdPolos((string) config('satusehat.organization_id'));
    }

    /** "Organization/100006775" → "100006775"; nilai polos dibiarkan apa adanya. */
    private function sisruteOrgIdPolos(string $nilai): string
    {
        return trim(str_ireplace('Organization/', '', trim($nilai)));
    }

    /** "Encounter/<uuid>" atau "<uuid>" → "<uuid>". */
    private function sisruteUuidEncounter(string $nilai): string
    {
        return trim(str_ireplace('Encounter/', '', trim($nilai)));
    }
}
