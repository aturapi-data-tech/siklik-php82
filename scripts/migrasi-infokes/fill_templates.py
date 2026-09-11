#!/usr/bin/env python3
"""
Isi template migrasi Infokes (Pasien / Obat / Tindakan) dari dump JSON siklik.

Dump JSON dibuat oleh:  php artisan migrasi:infokes-dump
Template asli diambil dari folder --templates (nama file default Infokes),
lalu disalin + diisi ke folder --out dengan nama file mengikuti nama klinik.

Selain 3 template, dibuat 1 file Catatan_Migrasi_*.xlsx berisi:
  - Pasien_Perlu_Cek : baris pasien yang NIK/KK/BPJS-nya tidak valid / kosong
  - Obat_Mapping     : kode siklik -> baris template + satuan/kelompok hasil mapping
  - Tindakan_Mapping : kode siklik -> baris template
"""

import argparse
import copy
import datetime as dt
import json
import re
from pathlib import Path

import openpyxl

TEMPLATE_PASIEN = "Template_Migrasi_Pasien_NamaKlinik_PKM_AsalKota_Kab.xlsx"
TEMPLATE_OBAT = "Template_Migrasi_Obat_NamaKlinik_Dinkes.xlsx"
TEMPLATE_TINDAKAN = "Template_Migrasi_Tindakan_NamaKlinik_Dinkes.xlsx"

# --- lookup siklik --------------------------------------------------------
# golonganDarahOptions di ⚡master-pasien-actions.blade.php; 13 = "Tidak Tahu" -> kosong
GOL_DARAH = {
    "1": "A", "2": "B", "3": "AB", "4": "O",
    "5": "A+", "6": "A-", "7": "B+", "8": "B-",
    "9": "AB+", "10": "AB-", "11": "O+", "12": "O-",
    "14": "O",
    # data lama ada yang menyimpan huruf langsung
    "A": "A", "B": "B", "AB": "AB", "O": "O",
}
# getMaritalDescription() di MasterPasienTrait
STATUS_KAWIN = {"1": "Belum Kawin", "2": "Kawin", "3": "Cerai Hidup", "4": "Cerai Mati"}
JENIS_KELAMIN = {"L": "Laki-laki", "P": "Perempuan"}
# Klasifikasi obat -> (KELOMPOK, JENIS/TITLE) template. Kategori siklik hanya
# OBAT*/ALKES/UMUM, jadi untuk UMUM & ALKES dilihat lagi dari nama barang.
KW_REAGEN = ("STIK GULA", "STIK ASAM", "STIK KOLES", "GDA", "ASAM URAT", "KOLESTEROL", "REAGEN", "STRIP")
KW_BMHP = ("HABIS PAKAI", "SPUIT", "NEEDLE", "BENANG", "KASA", "MASKER", "SARUNG TANGAN", "HANDSCOON",
           "PLESTER", "HYPAFIX", "HYPAVIX", "INFUS SET", "LANCET", "POT URINE", "SAVETY BOX", "SAFETY BOX",
           "ALKOHOL", "H2O2", "ASEPTIC", "HANDSANITIZER", "POVIDON", "KERTAS PUYER", "USG GEL", "CUTICEL",
           "MESS", "SENDOK OBAT", "PZ ", "VASELIN")
KW_ALKES = ("PINSET", "TENSI", "TIMBANGAN", "PVC MANUAL", "NEBUL", "GUNTING", "STETOSKOP", "TERMOMETER")
KW_SUPLEMEN = ("VITAMIN", "ASAM FOLAT", "CAVIPLEX", "RAMABION", "ETABION", "ZINC", "B COMPLEX")
KW_VAKSIN = ("VAKSIN", "VACCINE", "IMUNISASI")


def classify_obat(cat_desc: str, name: str) -> tuple:
    cat, nm = (cat_desc or "").upper(), f" {(name or '').upper()} "
    if any(k in nm for k in KW_REAGEN) or "LABORAT" in cat:
        return "REAGEN", "LAINNYA"
    if any(k in nm for k in KW_BMHP):
        return "BMHP", "LAINNYA"
    if any(k in nm for k in KW_ALKES) or ("ALKES" in cat and not any(k in nm for k in KW_SUPLEMEN)):
        return "ALKES", "ALAT KESEHATAN"
    if any(k in nm for k in KW_VAKSIN):
        return "OBAT", "VAKSIN"
    if any(k in nm for k in KW_SUPLEMEN):
        return "OBAT", "SUPLEMEN MAKANAN"
    if "MAKANAN" in cat or "MINUMAN" in cat:
        return "LAINNYA", "LAINNYA"
    return "OBAT", "OBAT"


# satuan generik siklik (PCS/BIJI) diterjemahkan dari bentuk sediaan di nama barang
SEDIAAN_DARI_NAMA = (
    (("SIRUP", "SYR", "SYRUP", "TETES", "GARGLE", "KUMUR", "1 LITER", "1 LT", "60 ML", "250 ML"), "BOTOL"),
    (("SALEP", "CREAM", "KRIM", "GEL"), "TUBE"),
    (("KALENG",), "KALENG"),
    (("INJEKSI", " INJ", "INJ)"), "AMPUL"),
    (("CAPS", "KAPSUL", "CAPSUL"), "KAPSUL"),
    ((" TAB", "TABLET", " MG", " MCG"), "TABLET"),
    (("SPRAY",), "BOTOL"),
)
GENERIC_UOM = {"PCS", "PC", "BIJI", "BUAH", "BH", "UNIT"}


def satuan_dari_nama(name: str) -> str:
    nm = f" {(name or '').upper()} "
    for keys, satuan in SEDIAAN_DARI_NAMA:
        if any(k in nm for k in keys):
            return satuan
    return "BUAH"


# alias satuan siklik -> nama satuan di sheet Keterangan template
SATUAN_ALIAS = {
    "BIJI": "BUAH", "PC": "BUAH", "PCS": "BUAH", "BH": "BUAH",
    "BTL": "BOTOL", "FL": "FLS", "TAB": "TABLET", "TBL": "TABLET",
    "KAP": "KAPLET", "KPL": "KAPSUL", "CAP": "KAPSUL", "CAPS": "KAPSUL", "CAPSUL": "KAPSUL",
    "SCH": "SACHET", "SACH": "SACHET", "STR": "STRIP",
    "SUPP": "SUPPOSITORIA", "SUP": "SUPPOSITORIA",
    "TUB": "TUBE", "AMP": "AMPUL", "VL": "VIAL",
    "KRIM": "CREAM", "CRM": "CREAM", "SALEP": "TUBE",
    "ML": "MILILITER", "LITER": "LITER", "LTR": "LITER", "UNIT/LITER": "LITER",
    "GR": "GRAM", "GRAM": "GRAM", "MG": "MILIGRAM",
    "LBR": "LEMBAR", "LMB": "LEMBAR", "KLG": "KALENG", "KTK": "KOTAK", "KTG": "KANTONG",
    "PSG": "PASANG", "PKT": "PAKET", "DUS": "DOS",
}

# placeholder data lama siklik: '-', '- - - -', '.', '000', '000000000000'
PLACEHOLDER = re.compile(r"^[\s\-\.0]*$")


def clean(v) -> str:
    """Trim + kosongkan nilai placeholder seperti '-', '- - -', '.', '000'"""
    if v is None:
        return ""
    s = str(v).strip()
    return "" if PLACEHOLDER.match(s) else s


def digits(v) -> str:
    """Ambil digit saja; nomor yang isinya nol semua dianggap kosong."""
    d = re.sub(r"\D", "", str(v or ""))
    return "" if d.strip("0") == "" else d


def slug(s: str) -> str:
    return re.sub(r"[^A-Za-z0-9]", "", s.title())


def extend_validations(ws, last_row: int) -> None:
    """Perpanjang range data-validation template (default sampai baris 998) ke baris terakhir data."""
    for dv in ws.data_validations.dataValidation:
        new_ranges = []
        for rng in str(dv.sqref).split():
            m = re.match(r"^([A-Z]+)(\d+)(?::([A-Z]+)(\d+))?$", rng)
            if not m:
                new_ranges.append(rng)
                continue
            c1, r1, c2, r2 = m.group(1), int(m.group(2)), m.group(3) or m.group(1), int(m.group(4) or m.group(2))
            new_ranges.append(f"{c1}{r1}:{c2}{max(r2, last_row)}")
        dv.sqref = openpyxl.worksheet.cell_range.MultiCellRange(" ".join(new_ranges))


# --- PASIEN ---------------------------------------------------------------

def fill_pasien(template: Path, out: Path, klinik: dict, rows: list) -> list:
    wb = openpyxl.load_workbook(template)
    ws = wb["Data_Pasien"]
    issues = []
    asal = klinik.get("nama", "")
    date_style = "yyyy-mm-dd"

    for i, p in enumerate(rows, start=2):
        reg_no = clean(p["reg_no"])
        nama = clean(p["reg_name"]).upper()
        problems = []
        if not nama:
            problems.append(f"Nama kosong (WAJIB): {p['reg_name']!r}")

        nik = digits(p["nik_bpjs"])
        if not nik:
            problems.append("NIK kosong (WAJIB)")
        elif len(nik) != 16:
            problems.append(f"NIK {len(nik)} digit: {p['nik_bpjs']}")
        nik = nik if len(nik) <= 16 else ""

        no_kk = digits(p["no_kk"])
        if no_kk and len(no_kk) > 16:
            problems.append(f"No KK > 16 digit: {p['no_kk']}")
            no_kk = ""

        bpjs = digits(p["nokartu_bpjs"])
        if bpjs and len(bpjs) != 13:
            problems.append(f"No BPJS bukan 13 digit: {p['nokartu_bpjs']}")
            bpjs = "" if len(bpjs) > 13 else bpjs
        asuransi = "BPJS" if bpjs else "UMUM"

        jk = JENIS_KELAMIN.get((p["sex"] or "").strip().upper(), "")
        if not jk:
            problems.append(f"Jenis kelamin tidak dikenal: {p['sex']!r}")

        tgl_lahir = None
        if p["birth_date"]:
            tgl_lahir = dt.datetime.strptime(p["birth_date"], "%Y-%m-%d").date()
        else:
            problems.append("Tanggal lahir kosong")

        blood = GOL_DARAH.get(clean(p["blood"]).upper(), "")
        kawin = STATUS_KAWIN.get(clean(p["marital_status"]), "")
        phone = re.sub(r"[\s\-\.]", "", clean(p["phone"]))

        values = [
            asal,                       # A ASAL_PUSKESMAS
            reg_no,                     # B NO_RM
            nama,                       # C NAMA_LENGKAP
            no_kk,                      # D NO_KK
            nik,                        # E NIK
            asuransi,                   # F ASURANSI
            bpjs,                       # G NO_BPJS
            jk,                         # H JENIS_KELAMIN
            clean(p["birth_place"]).upper(),  # I TEMPAT_LAHIR
            tgl_lahir,                  # J TGL_LAHIR
            clean(p["address"]),        # K ALAMAT
            clean(p["rt"]),             # L RT
            clean(p["rw"]),             # M RW
            "",                         # N DUSUN (siklik tidak punya)
            clean(p["des_name"]),       # O KELURAHAN
            clean(p["kec_name"]),       # P KECAMATAN
            clean(p["kab_name"]),       # Q KOTA/KAB
            clean(p["prop_name"]),      # R PROPINSI
            blood,                      # S GOL_DARAH
            clean(p["job_name"]),       # T PEKERJAAN
            "",                         # U STATUS_KELUARGA (tidak ada di siklik)
            kawin,                      # V STATUS_PERKAWINAN
            "",                         # W EMAIL (tidak ada di siklik)
            phone,                      # X NO_HP
            clean(p["rel_desc"]),       # Y AGAMA
            clean(p["edu_desc"]),       # Z PENDIDIKAN
            clean(p["kk"]).upper(),     # AA NAMA AYAH / NAMA KK
            clean(p["nyonya"]).upper(), # AB NAMA IBU
        ]
        for col, v in enumerate(values, start=1):
            cell = ws.cell(row=i, column=col, value=v if v != "" else None)
            if col == 10 and v is not None:
                cell.number_format = date_style

        if problems:
            issues.append([reg_no, nama, "; ".join(problems)])

    extend_validations(ws, len(rows) + 1)
    wb.save(out)
    return issues


# --- OBAT -----------------------------------------------------------------

def fill_obat(template: Path, out: Path, rows: list) -> list:
    wb = openpyxl.load_workbook(template)
    ws = wb["List_Obat"]
    ket = wb["Keterangan"]

    # satuan valid = kolom F sheet Keterangan; kode = kolom E
    satuan_by_desc, satuan_by_code, last_ket_row = {}, {}, 1
    for r in range(2, ket.max_row + 1):
        code, desc = clean(ket.cell(r, 5).value).upper(), clean(ket.cell(r, 6).value).upper()
        if desc:
            satuan_by_desc[desc] = desc
            satuan_by_code[code] = desc
            last_ket_row = r
    kelompok_valid = {clean(ket.cell(r, 1).value).upper() for r in range(2, ket.max_row + 1)} - {""}
    jenis_valid = {clean(ket.cell(r, 3).value).upper() for r in range(2, ket.max_row + 1)} - {""}

    mapping, added_satuan = [], []
    for i, o in enumerate(rows, start=2):
        raw = clean(o["uom_desc"] or o["uom_id"]).upper()
        if raw in GENERIC_UOM:
            satuan = satuan_dari_nama(o["product_name"])
        else:
            satuan = satuan_by_desc.get(raw) or satuan_by_code.get(raw) or SATUAN_ALIAS.get(raw)
        if satuan and satuan not in satuan_by_desc:
            # alias menunjuk ke satuan yang tidak ada di template -> tambahkan
            satuan_by_desc[satuan] = satuan
            added_satuan.append(satuan)
        if not satuan:
            satuan = raw or "BUAH"
            if satuan not in satuan_by_desc:
                satuan_by_desc[satuan] = satuan
                added_satuan.append(satuan)

        kelompok, jenis = classify_obat(o["cat_desc"], o["product_name"])
        if kelompok not in kelompok_valid:
            kelompok = "LAINNYA"
        if jenis not in jenis_valid:
            jenis = "LAINNYA"

        nama = clean(o["product_name"]).upper()
        ws.cell(row=i, column=1, value=nama)
        ws.cell(row=i, column=2, value=satuan)
        ws.cell(row=i, column=3, value=kelompok)
        ws.cell(row=i, column=4, value=jenis)
        mapping.append([i, o["product_id"], nama, raw, satuan, o["product_type"], kelompok, jenis,
                        o["cat_desc"], o["sales_price"]])

    # satuan yang tidak ada di template -> tambahkan di sheet Keterangan (sesuai petunjuk template)
    for s in added_satuan:
        last_ket_row += 1
        ket.cell(row=last_ket_row, column=5, value=s[:4])
        ket.cell(row=last_ket_row, column=6, value=s)

    extend_validations(ws, len(rows) + 1)
    wb.save(out)
    return mapping, added_satuan


# --- TINDAKAN -------------------------------------------------------------

def fill_tindakan(template: Path, out: Path, rows: list) -> list:
    wb = openpyxl.load_workbook(template)
    ws = wb.active
    # template: "Tambahkan kolom baru apabila diperlukan" -> tambah KELOMPOK + KODE SIKLIK
    ws.cell(row=1, column=3, value="KELOMPOK")
    ws.cell(row=1, column=4, value="KODE SIKLIK")
    for col in (3, 4):
        ws.cell(row=1, column=col)._style = copy.copy(ws.cell(row=1, column=1)._style)

    mapping = []
    for i, t in enumerate(rows, start=2):
        nama = clean(t["nama"]).upper()
        tarif = t["tarif"] if t["tarif"] is not None else 0
        ws.cell(row=i, column=1, value=nama)
        ws.cell(row=i, column=2, value=tarif)
        ws.cell(row=i, column=3, value=t["kelompok"])
        ws.cell(row=i, column=4, value=f"{t['tabel']}:{t['kode']}")
        mapping.append([i, t["tabel"], t["kode"], nama, tarif, t["kelompok"], t["active_status"]])
    wb.save(out)
    return mapping


# --- CATATAN --------------------------------------------------------------

def write_catatan(out: Path, klinik: dict, pasien_issues, obat_map, added_satuan, tindakan_map) -> None:
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Ringkasan"
    ws.append(["Klinik", klinik.get("nama", "")])
    ws.append(["Kota", klinik.get("kota", "")])
    ws.append(["Dibuat", dt.datetime.now().strftime("%Y-%m-%d %H:%M")])
    ws.append([])
    ws.append(["Pasien perlu dicek", len(pasien_issues)])
    ws.append(["Obat", len(obat_map)])
    ws.append(["Satuan ditambahkan ke sheet Keterangan", ", ".join(added_satuan) or "-"])
    ws.append(["Tindakan", len(tindakan_map)])

    ws = wb.create_sheet("Pasien_Perlu_Cek")
    ws.append(["NO_RM", "NAMA", "MASALAH"])
    for r in pasien_issues:
        ws.append(r)

    ws = wb.create_sheet("Obat_Mapping")
    ws.append(["BARIS TEMPLATE", "PRODUCT_ID", "NAMA", "SATUAN SIKLIK", "SATUAN TEMPLATE",
               "PRODUCT_TYPE", "KELOMPOK", "JENIS", "KATEGORI SIKLIK", "HARGA JUAL"])
    for r in obat_map:
        ws.append(r)

    ws = wb.create_sheet("Tindakan_Mapping")
    ws.append(["BARIS TEMPLATE", "TABEL", "KODE", "NAMA", "TARIF", "KELOMPOK", "ACTIVE_STATUS"])
    for r in tindakan_map:
        ws.append(r)

    for sheet in wb.worksheets:
        for col in sheet.columns:
            width = max((len(str(c.value)) for c in col if c.value is not None), default=8)
            sheet.column_dimensions[col[0].column_letter].width = min(max(10, width + 2), 60)
    wb.save(out)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--dump", required=True, help="folder JSON hasil php artisan migrasi:infokes-dump")
    ap.add_argument("--templates", required=True, help="folder berisi 3 template asli Infokes")
    ap.add_argument("--out", required=True, help="folder output")
    args = ap.parse_args()

    dump, tpl, out = Path(args.dump).expanduser(), Path(args.templates).expanduser(), Path(args.out).expanduser()
    out.mkdir(parents=True, exist_ok=True)

    load = lambda name: json.loads((dump / name).read_text(encoding="utf-8"))
    klinik, pasien, obat, tindakan = load("klinik.json"), load("pasien.json"), load("obat.json"), load("tindakan.json")

    nama_klinik = slug(klinik.get("nama") or "Klinik")
    kota = slug(klinik.get("kota") or "Kota")

    f_pasien = out / f"Template_Migrasi_Pasien_{nama_klinik}_{kota}.xlsx"
    f_obat = out / f"Template_Migrasi_Obat_{nama_klinik}_Dinkes{kota}.xlsx"
    f_tindakan = out / f"Template_Migrasi_Tindakan_{nama_klinik}_Dinkes{kota}.xlsx"
    f_catatan = out / f"Catatan_Migrasi_{nama_klinik}_{kota}.xlsx"

    pasien_issues = fill_pasien(tpl / TEMPLATE_PASIEN, f_pasien, klinik, pasien)
    obat_map, added_satuan = fill_obat(tpl / TEMPLATE_OBAT, f_obat, obat)
    tindakan_map = fill_tindakan(tpl / TEMPLATE_TINDAKAN, f_tindakan, tindakan)
    write_catatan(f_catatan, klinik, pasien_issues, obat_map, added_satuan, tindakan_map)

    print(f"pasien   : {len(pasien)} baris, {len(pasien_issues)} perlu dicek -> {f_pasien.name}")
    print(f"obat     : {len(obat)} baris, satuan baru: {added_satuan or '-'} -> {f_obat.name}")
    print(f"tindakan : {len(tindakan)} baris -> {f_tindakan.name}")
    print(f"catatan  : {f_catatan.name}")


if __name__ == "__main__":
    main()
