# BPJS: whitelist IP publik & proxy keluar lewat VPS (siklik — klinik FKTP)

**Kebijakan BPJS Kesehatan (berlaku 2 Sep 2026):** API BPJS hanya melayani permintaan dari **IP publik
yang didaftarkan faskes**. Pengajuan whitelist IP tidak lagi lewat surat/e-mail biasa, melainkan lewat
**form pengajuan di ITSM BPJS** (Formulir Pengajuan Akses Bridging SIM) — diisi faskes bersama vendor,
menyebut kode PPK, nama aplikasi, dan daftar IP publik (utama + backup bila ada).

Konsekuensi teknis: kalau IP yang didaftarkan **bukan** IP internet klinik (mis. klinik pakai IP dinamis
dari ISP, atau memang mendaftarkan IP VPS), maka **semua panggilan API BPJS dari siklik harus KELUAR
lewat IP itu**.

Cara yang dipilih: **forward proxy (Squid) di VPS**, aplikasi cukup diberi `BPJS_PROXY_URL`.
Tidak perlu VPN, tidak perlu memindah server, tidak mengubah satu pun URL BPJS.

Layanan BPJS yang terkena: **PCare**, **Antrean FKTP outbound**, **i-Care**.
**SATUSEHAT (Kemenkes) TIDAK** lewat proxy ini — whitelist itu milik BPJS Kesehatan.
Antrean FKTP arah **inbound** (Mobile JKN → klinik) juga tidak terkena: di situ BPJS yang menghubungi
klinik, jadi yang perlu dibuka justru IP publik/port server klinik dari sisi jaringan.

## 1. Sisi aplikasi (sudah di repo)

| Bagian | Isi |
|---|---|
| `config/bpjs.php` | grup `pcare`, `antrian`, `antrean_fktp`, `icare` + `proxy_aktif`, `proxy_url`, `ip_whitelist`, `timeout`, `connect_timeout`, `ip_echo_url` — semua dari `.env` |
| `App\Support\Bpjs\BpjsHttp::mulai()` | satu-satunya pembuat `PendingRequest` untuk API BPJS: batas waktu + opsi `proxy` Guzzle bila saklar hidup |
| `PcareTrait`, `AntrianTrait`, `iCareTrait` | `Http::timeout(...)` diganti `BpjsHttp::mulai()` |
| `php artisan bpjs:cek-proxy` | bertanya ke `api.ipify.org` lewat jalur yang sama, lalu membandingkan dengan `BPJS_IP_WHITELIST` |

`.env`:

```
BPJS_PROXY_AKTIF=true                      # SAKLAR; bawaan false = langsung (keadaan produksi saat ini)
BPJS_PROXY_URL=http://siklik:KATA_SANDI@IP_VPS:3128
BPJS_IP_WHITELIST=IP_VPS
# BPJS_HTTP_TIMEOUT=10
# BPJS_HTTP_CONNECT_TIMEOUT=3
# ANTRIAN_HTTP_TIMEOUT=15                  # endpoint antrean BPJS lebih lambat dari PCare
```

Proxy hanya dipakai bila `BPJS_PROXY_AKTIF=true` **dan** URL terisi. Produksi boleh menyimpan URL
sejak sekarang dengan saklar mati; saat BPJS menegakkan whitelist, cukup `BPJS_PROXY_AKTIF=true` +
`php artisan config:clear` (atau `config:cache` ulang bila produksi memakai config cache).
Mematikan kembali = satu baris juga.

> **Aturan pengembangan:**
> 1. Panggilan BPJS baru **wajib** lewat `BpjsHttp::mulai()`, jangan `Http::` langsung — kalau tidak,
>    panggilan itu keluar dari IP klinik dan ditolak BPJS tanpa pesan yang jelas.
> 2. Kredensial/URL dibaca lewat `config('bpjs.…')`, **jangan `env()`** di dalam `app/` —
>    `env()` mengembalikan `null` begitu `php artisan config:cache` dijalankan.

## 2. Sisi VPS (sekali pasang)

Sebagai root di VPS (contoh Rocky/RHEL 8):

```bash
dnf install -y squid httpd-tools
htpasswd -c /etc/squid/passwd siklik          # buat user proxy + kata sandi
chgrp squid /etc/squid/passwd && chmod 640 /etc/squid/passwd

cp /etc/squid/squid.conf /etc/squid/squid.conf.asli
cat > /etc/squid/squid.conf <<'EOF'
http_port 3128

# Lapis 1: user + sandi (/etc/squid/passwd)
auth_param basic program /usr/lib64/squid/basic_ncsa_auth /etc/squid/passwd
auth_param basic realm proxy-bpjs
acl terautentikasi proxy_auth REQUIRED

# Lapis 2: tujuan yang boleh — hanya BPJS + penunjuk IP untuk uji
acl bpjs dstdomain .bpjs-kesehatan.go.id
acl ipecho dstdomain api.ipify.org
acl port_aman port 443 80

http_access allow terautentikasi bpjs port_aman
http_access allow terautentikasi ipecho port_aman
http_access deny all

# Tanpa cache, tanpa jejak identitas
cache deny all
via off
forwarded_for delete
request_header_access X-Forwarded-For deny all
EOF

squid -k parse 2>&1 | grep -i "error\|fatal"; echo "parse selesai"
systemctl enable --now squid
# Bila ada firewalld: firewall-cmd --permanent --add-port=3128/tcp && firewall-cmd --reload
```

Uji dari server siklik:

```bash
curl -x http://siklik:KATA_SANDI@IP_VPS:3128 https://api.ipify.org   # harus mencetak IP_VPS
php artisan bpjs:cek-proxy                                          # harus "COCOK"
```

`bpjs:cek-proxy` sengaja memakai jalur yang **persis sama** dengan trait BPJS, sehingga bisa memisahkan
masalah jaringan (IP salah, proxy mati) dari masalah kredensial/payload (cons id, signature, body).

## 3. Keamanan VPS — kerjakan hari pertama

- Ganti kata sandi root VPS, lalu matikan login sandi SSH (`PasswordAuthentication no`, pakai kunci SSH).
- Port yang perlu terbuka cukup 22 dan 3128.
- Kata sandi proxy hanya di `.env` server, **jangan** di repo.

## 4. Mengunci ke IP klinik (opsional, saat produksi mapan)

Tambah `acl klinik src IP_KLINIK/32` dan sisipkan `klinik` ke dua baris `http_access allow`, lalu
`systemctl reload squid`. Dev di luar klinik akan ditolak 403 walau sandi benar. Kalau IP klinik
berubah: ubah satu baris + reload; aplikasi tidak disentuh.

Catatan khusus klinik pratama: banyak klinik memakai IP dinamis dari ISP. Kalau IP dinamis itu yang
didaftarkan ke BPJS, setiap kali ISP mengganti IP harus mengajukan perubahan lewat ITSM BPJS lagi —
itulah alasan utama memilih IP VPS statis + proxy.

## 5. Jejak keputusan

- Alternatif yang ditolak: memindah seluruh siklik ke VPS (DB Oracle on-prem), VPN site-to-site
  (butuh perangkat di klinik), reverse proxy per-URL BPJS (header/host BPJS berubah).
- Pola ini diadopsi dari sirus-php82 (`docs/bpjs-whitelist-ip-proxy.md`, commit `fa21c008`), disesuaikan
  untuk klinik FKTP: layanan yang lewat proxy adalah PCare / Antrean FKTP / i-Care, bukan VClaim / Aplicares / SISRUTE.
