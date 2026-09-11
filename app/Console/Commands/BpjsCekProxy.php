<?php

namespace App\Console\Commands;

use App\Support\Bpjs\BpjsHttp;
use Illuminate\Console\Command;

/**
 * Memastikan panggilan BPJS keluar lewat IP yang di-whitelist.
 *
 * Memakai jalur yang persis sama dengan trait BPJS (BpjsHttp::mulai), lalu bertanya
 * ke layanan penunjuk IP publik (config bpjs.ip_echo_url) "saya terlihat dari IP mana?".
 * Cocok dijalankan setelah mengubah BPJS_PROXY_URL atau saat BPJS menolak "IP tidak
 * terdaftar" — memisahkan masalah jaringan dari masalah kredensial/payload.
 */
class BpjsCekProxy extends Command
{
    protected $signature = 'bpjs:cek-proxy';

    protected $description = 'Cek IP publik yang dilihat BPJS saat aplikasi memanggil API-nya (lewat proxy bila BPJS_PROXY_URL diisi)';

    public function handle(): int
    {
        $this->info('Proxy        : ' . BpjsHttp::proxyUrlTersamar());
        if (!BpjsHttp::lewatProxy()) {
            $this->warn('Panggilan BPJS saat ini LANGSUNG (tidak lewat VPS). Nyalakan dengan BPJS_PROXY_AKTIF=true + BPJS_PROXY_URL.');
        }
        $this->info('IP whitelist : ' . (BpjsHttp::ipWhitelist() ?: '(BPJS_IP_WHITELIST belum diisi)'));
        $this->info('Batas waktu  : ' . BpjsHttp::timeout() . ' dtk (connect ' . BpjsHttp::connectTimeout() . ' dtk)');

        $url = (string) config('bpjs.ip_echo_url');

        try {
            $mulai = microtime(true);
            $respons = BpjsHttp::mulai()->get($url);
            $lama = round(microtime(true) - $mulai, 2);
        } catch (\Throwable $e) {
            $this->error("Gagal menghubungi {$url}: " . $e->getMessage());
            $this->line('Periksa: proxy hidup? port terbuka di firewall VPS? IP klinik diizinkan di ACL Squid? user/sandi proxy benar?');

            return self::FAILURE;
        }

        if (!$respons->successful()) {
            $this->error("HTTP {$respons->status()} dari {$url}");

            return self::FAILURE;
        }

        $ipTerlihat = trim((string) ($respons->json('ip') ?? $respons->body()));
        $this->info("IP terlihat  : {$ipTerlihat} ({$lama} dtk)");

        $whitelist = BpjsHttp::ipWhitelist();
        if ($whitelist === '') {
            $this->warn('BPJS_IP_WHITELIST kosong — tidak bisa dibandingkan. Isi dengan IP yang diajukan ke BPJS.');

            return self::SUCCESS;
        }

        if ($ipTerlihat === $whitelist) {
            $this->info('COCOK — BPJS akan melihat IP yang di-whitelist.');

            return self::SUCCESS;
        }

        $this->error("TIDAK COCOK — BPJS melihat {$ipTerlihat}, padahal yang didaftarkan {$whitelist}.");
        $this->line('Bila proxy NONAKTIF: itu memang keadaan langsung — nyalakan BPJS_PROXY_AKTIF=true saat BPJS menegakkan whitelist. Bila AKTIF: cek URL/skema, atau VPS keluar lewat IP lain.');

        return self::FAILURE;
    }
}
