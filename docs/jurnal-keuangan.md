# Jurnal keuangan: baca langsung tabel transaksi (tanpa view)

**Keputusan 11 Sep 2026** (mengikuti sirus-php82, 9 Sep 2026). Laporan keuangan web — Buku Besar,
Cek Saldo Kas, Laba Rugi, Neraca — membaca jurnal LANGSUNG dari tabel transaksi lewat
`App\Support\Keuangan\Jurnal`, bukan lewat view `SKVIEW_ACCOUNTS` → `SKVIEW_ACCOUNTS_LABARUGI` →
`SKVIEW_ACCOUNTS_NERACA`. View-view itu kini hanya untuk form Oracle 6i.

## Kenapa

- Oracle siklik 10.2.0.1 sama dengan lokal sirus: view `UNION ALL` + subquery skalar memberi hasil
  **tidak stabil** (agregat berubah tergantung bentuk query). Query per cabang yang dibangun `Jurnal`
  konsisten di semua bentuk query dan sama dengan penjumlahan baris di PHP.
- Predikat tanggal di atas view tidak sargable; `Jurnal` menanam predikat di TIAP cabang, dan cabang
  ber-akun konfigurasi yang tidak diminta tidak dikirim ke Oracle sama sekali.
- Dengan select langsung, selisih yang tersisa terhadap form 6i pasti DATA (saldo awal, stock opname).

## Komponen

| Berkas | Peran |
|---|---|
| `app/Support/Keuangan/JurnalCabang.php` | **Sumber kebenaran** 46 cabang jurnal (23 pasang cermin), diturunkan mekanis 11 Sep 2026 dari DDL `SKVIEW_ACCOUNTS`, lalu dirawat di PHP. Bentuk: `label, akun, akunLawan, tanggal, debit, kredit, from, where`; akun konfigurasi ditulis `conf:7` dst. (skacc_confacctxns), akun kas `b.acc_id` |
| `app/Support/Keuangan/Jurnal.php` | `query($accId, $sisi, $dari, $sampai)` → Builder berkolom `txn_name, txn_acc, txn_acc_k, shift('1'), txn_date, txn_d, txn_k`; `queryBanyak()`; `arusPerAkun([...], $dari, $sampai)` → `[acc_id => [debit, kredit]]` satu pemindaian; `saldoAwalPerAkun()`; `namaAkun()`; `akunKonfigurasiId('7')` |
| `app/Support/Keuangan/Hpp.php` | HPP tahunan = saldo awal persediaan + arus persediaan − Σ hpp_product × stok akhir (`SKVIEW_SALDOAKHIRSTOCKS`), padanan `SKVIEW_HPPES`; disisipkan `Jurnal` sebagai cabang semu `from dual` tanggal 1 Desember (akun HPP conf 9 debit, persediaan conf 2 kredit) |
| `app/Support/Keuangan/SaldoKas.php` | Rumus 6i `hitung_saldo_tanggal` (saldo awal tahun + arus) di atas `Jurnal`; shift tidak berpengaruh (siklik tanpa shift di jurnal, `SKTXN_SHIFTCTLS` kosong) |
| `php artisan siklik:verif-jurnal` | Pembanding Jurnal vs view per akun tingkat baris untuk rentang pendek; jalankan setiap kali katalog berubah dan sebelum deploy ke DB lain |

## Peta cabang (ringkas)

| Kelompok | Sumber | Akun ↔ lawan |
|---|---|---|
| BAYAR APOTEK / BAYAR RJ (cash in) | `SKTXN_CASHINHDRS`, `SKTXN_RJCASHINS` × `SKACC_CARABAYARS` | kas `b.acc_id` ↔ piutang conf:7 |
| SLS TRANSAKSI, SLS DISKON ITEM/TOTAL | `SKTXN_SLSHDRS` (Σ dtl) | penjualan conf:1 / diskon conf:5 ↔ piutang conf:7 |
| BAYAR RCV (cash out) | `SKTXN_CASHOUTHDRS` × `SKACC_CARABAYARS` | kas `b.acc_id` ↔ hutang conf:8 |
| RCV TRANSAKSI, DISKON ITEM/TOTAL, MATERAI, PPN | `SKTXN_RCVHDRS` (Σ dtl), `SKVIEW_RCVHDRS` (PPN) | persediaan conf:2 / diskon conf:6 / materai conf:12 / PPN conf:13 ↔ hutang conf:8 |
| CASH IN / CASH OUT TU | `SKTXN_TUCASHINS/OUTS` × `SKACC_TUCICOS` / `SKACC_CARABAYARS` | akun kas ↔ akun tucico |
| RJ JD/OBAT/JK/LAB/JM/RAD/LAIN/RJ ADMIN/RS ADMIN, RJ DISKON | `SKTXN_RJHDRS` (Σ rincian per rj_no) | penjualan conf:1 / diskon conf:5 ↔ piutang conf:7 |
| HPP (semu, 1 Des) | `Hpp::nilai(tahun)` | HPP conf:9 ↔ persediaan conf:2 |

Asimetri warisan view yang **dipertahankan apa adanya** (tinjau bila ingin diseragamkan): cabang cermin
kedua RCV DISKON TOTAL / MATERAI / PPN memfilter nilai `> 0` sedangkan cabang pertama tidak; BAYAR
(cash in/out) tidak memfilter status dokumen; kedua cabang SLS DISKON ITEM berlabel sama.

## Pemakai (halaman web, sejak 11 Sep 2026)

| Halaman | Cara pakai |
|---|---|
| Buku Besar | `Jurnal::query($acc, SISI_ACC, …)` per akun; saldo awal periode = `saldoAwalPerAkun` + `arusPerAkun` 1 Jan s/d H-1; nama lawan `namaAkun()`; paginasi PHP; rekap per jenis dari 2 kata pertama `txn_name`; banner HPP bila periode memuat 1 Des & akun conf 9/2 |
| Cek Saldo Kas + Edit Saldo Awal + History | `SaldoKas::hitung`, `arusTahun` (back-calc), `SaldoKas::query()` sisi 6i + saldo berjalan; cetak history PDF (detail ≤ 400 baris, di atasnya rekap harian — DomPDF kehabisan memori); pemilih shift tersembunyi selama `daftarShift()` kosong |
| Laba Rugi | template datar L1: DTLS → TEMACCOUNTES; nilai `arusPerAkun` bulan & YTD (2 pemindaian); tanda = `dk_status` grup; pos HPP dikenali dari akun conf 9 → `Hpp::nilai(tahun)` + rincian + **override manual** (state komponen); bila `Hpp::wajar()` false HPP dianggap 0 + badge merah |
| Neraca | grup akun master 1/2/3 + semua akun aktif; saldo = `saldoAwalPerAkun` + `arusPerAkun` YTD; Laba (Rugi) Tahun Berjalan = Σ(K − D) arus gra 4/5 YTD (memuat cabang semu HPP bila 1 Des ≤ cutoff); toggle estimasi HPP menjurnal dua sisi (laba −HPP, persediaan −HPP) agar tetap seimbang |

Jebakan yang sudah dibayar: bind di select-list `UNION ALL` harus `to_number(?)` (oci8 mem-bind semua sebagai VARCHAR2 → ORA-01790);
`skacc_temlabarugineracahdrs.temp_status` terbalik di data (L1 = 'N') → filter `temp_id` langsung; pos HPP di template ber-gra 4
(K) padahal beban → dikenali data-driven. Backlog: `Jurnal::queryDenganSaldoBerjalan` (SUM OVER) untuk paginasi SQL,
`Jurnal::arusPerAkunPerBulan`, `Hpp::nilaiBulan`; beban template L1 nol sepanjang 2026 di dev = belum ada transaksi kas TU, bukan cabang hilang.

## Konvensi sisi

Baris `txn_acc = akun` adalah baris MILIK akun itu (`txn_d`/`txn_k` = debit/kredit akun itu sendiri);
baris `txn_acc_k = akun` adalah baris cermin milik akun lawan. Buku Besar memakai `SISI_ACC` untuk akun
D maupun K. `SaldoKas::sisi('K')` meniru 6i (baca `txn_acc_k`) — halaman Cek Saldo Kas hanya memuat akun
kas (D) sehingga belum berdampak.

## Aturan

- Cabang baru/ubah: edit `JurnalCabang.php` sepasang cabang cermin (akun ↔ lawan); ubah view di DB hanya
  bila form 6i masih memerlukannya. Lalu `php artisan siklik:verif-jurnal --dari=… --sampai=…`.
- **Jangan** join tabel lain di atas hasil `Jurnal::query`; nama akun lewat `Jurnal::namaAkun`.
- Laporan ber-template pakai `Jurnal::arusPerAkun` (satu pemindaian per rentang), bukan `query` per akun.
- HPP bergantung stock opname & saldo awal persediaan; angka tahun berjalan bisa menyimpang jauh
  (2026 lokal: −38,9 M). Halaman Laba Rugi menyediakan override manual; ini masalah DATA, bukan rumus.
- Setelah katalog atau rumus berubah: perbarui tabel peta cabang di atas.

## Hasil verifikasi 11 Sep 2026 (Oracle dev, Agustus 2026)

10 akun (5 kas, penjualan 4101, piutang 1131, hutang 2101, persediaan 1141, diskon 5102): jumlah baris,
Σ debit, Σ kredit **identik** dengan `SKVIEW_ACCOUNTS`; HPP 2025 = 106.752.391 sama dengan `SKVIEW_HPPES`;
`arusPerAkun` 10 akun YTD 0,33 detik.
