<?php
// Komponen Modal Satu Sehat RJ.
// Dipisah dari daftar-rj-actions supaya orchestrator tetap ramping.
// Trigger dari parent: dispatch event 'daftar-rj.satu-sehat.open' dengan rjNo.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    public ?string $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    /**
     * URUTAN KANONIK "Kirim Semua". Bukan sekadar urutan tampilan — ada
     * ketergantungan keras di dalamnya:
     *   - encounter WAJIB pertama; semua resource lain menunjuk padanya;
     *   - encounter-selesai WAJIB terakhir dan butuh condition sudah terkirim
     *     (SATUSEHAT mewajibkan Encounter.diagnosis saat finish, RuleNumber 10457).
     * Mengubah urutan di sini berarti mengubah perilaku kirim, bukan tata letak.
     *
     * Daftar ini SENGAJA hanya memuat langkah yang kartunya benar-benar ada di
     * siklik sekarang. Menambah langkah berikutnya (chief-complaint, allergy,
     * medication-dispense, dst.) cukup menyisipkan namanya di sini SEBELUM
     * 'encounter-selesai' plus merender kartunya di grid bawah — tidak ada
     * tempat lain yang menghitung jumlah langkah.
     */
    private const URUTAN_KIRIM = [
        'encounter',
        'condition',
        'observation',
        'procedure',
        'medication-request',
        'encounter-selesai',
    ];

    /** Sisa langkah yang belum dijalankan; kosong = tidak ada rantai berjalan. */
    public array $antrianKirim = [];

    /** Langkah yang sedang ditunggu kabarnya. Dikosongkan begitu kabarnya datang. */
    public string $langkahAktif = '';

    /** Jumlah langkah, dipakai teks konfirmasi supaya tak pernah basi. */
    public function jumlahLangkah(): int
    {
        return count(self::URUTAN_KIRIM);
    }

    public function kirimSemua(): void
    {
        if (empty($this->rjNo)) {
            return;
        }

        // Sekali jalan saja. Tanpa penjaga ini, klik kedua MERESTART antrean dari
        // awal sementara langkah pertama masih di udara — dua rantai berjalan
        // bergantian dan langkah yang sama berangkat dua kali ke SATUSEHAT.
        if (!empty($this->antrianKirim) || $this->langkahAktif !== '') {
            $this->dispatch('toast', type: 'info', message: 'Kirim Semua sedang berjalan. Tunggu selesai, atau tekan Hentikan.');
            return;
        }

        $this->antrianKirim = self::URUTAN_KIRIM;
        $this->langkahAktif = '';
        $this->jalankanLangkahBerikutnya();
    }

    public function batalkanKirimSemua(): void
    {
        $this->antrianKirim = [];
        $this->langkahAktif = '';
        $this->dispatch('toast', type: 'info', message: 'Kirim Semua dihentikan. Langkah yang sudah berjalan tidak dibatalkan.');
    }

    /**
     * Satu langkah dijalankan per putaran, TIDAK ditembakkan serentak. Kalau semua
     * event dilepas sekaligus, Livewire menjalankannya paralel dan Condition bisa
     * berangkat sebelum Encounter-nya ada.
     */
    private function jalankanLangkahBerikutnya(): void
    {
        $langkah = array_shift($this->antrianKirim);
        if ($langkah === null) {
            $this->langkahAktif = '';
            $this->dispatch('toast', type: 'success', message: 'Kirim Semua selesai. Periksa status tiap langkah di bawah.');
            return;
        }

        $this->langkahAktif = $langkah;

        // Penutup kunjungan bukan "kirim resource baru": ia PUT Encounter yang sudah
        // ada, jadi event-nya pun beda (finish, bukan kirim).
        if ($langkah === 'encounter-selesai') {
            $this->dispatch('ss-encounter-rj.finish', rjNo: $this->rjNo);
            return;
        }

        $this->dispatch('ss-' . $langkah . '-rj.kirim', rjNo: $this->rjNo);
    }

    /**
     * Kabar selesai dari sebuah langkah. Dicocokkan dengan langkah yang SEDANG
     * ditunggu, lalu penandanya dikosongkan — kartu Encounter dirender dua kali
     * (bagian kirim & selesai), jadi tanpa pencocokan ini satu kabar nyasar bisa
     * membuat antrean melompati satu langkah.
     */
    #[On('rj-satu-sehat.langkah-selesai')]
    public function langkahSelesai(string $langkah): void
    {
        if ($this->langkahAktif === '' || $langkah !== $this->langkahAktif) {
            return;
        }

        $this->langkahAktif = '';
        $this->jalankanLangkahBerikutnya();
    }

    public function mount(?string $initialRjNo = null): void
    {
        if (!empty($initialRjNo)) {
            $this->rjNo = $initialRjNo;
            $this->loadData();
        }
    }

    #[On('daftar-rj.satu-sehat.open')]
    public function handleOpenSatuSehat(string $rjNo): void
    {
        // Antrean SELALU direset saat modal dibuka. Rantai yang tersangkut pada
        // pasien sebelumnya tidak boleh ikut terbawa: langkah berikutnya akan
        // dikirim atas nama pasien yang SEDANG dibuka, bukan pemilik aslinya.
        $this->antrianKirim = [];
        $this->langkahAktif = '';

        $this->rjNo = $rjNo;

        if (!$this->loadData()) {
            return;
        }

        $this->dispatch('open-modal', name: 'rj-satu-sehat');
    }

    /**
     * Tutup lewat server supaya penanda kunjungan dikosongkan: isi modal dibungkus
     * guard if($rjNo) di template, jadi seluruh kartu SATUSEHAT ikut dihapus saat
     * tutup, bukan tinggal tersembunyi. Antrean kirim ikut dikosongkan (sama
     * seperti saat buka). Escape / klik backdrop tetap menutup di sisi Alpine saja.
     */
    public function closeModal(): void
    {
        $this->antrianKirim = [];
        $this->langkahAktif = '';
        $this->rjNo = null;
        $this->dataDaftarPoliRJ = [];
        $this->dispatch('close-modal', name: 'rj-satu-sehat');
    }

    #[On('rj-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->loadData();
    }

    private function loadData(): bool
    {
        if (empty($this->rjNo)) {
            return false;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return false;
        }
        $this->dataDaftarPoliRJ = $data;
        return true;
    }
};
?>

<div>
    <x-modal name="rj-satu-sehat" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali
             (anak memuat datanya dari prop), tutup = dihapus tanpa mount ulang. --}}
        @if ($rjNo)
            <div class="flex flex-col min-h-0">
                {{-- HEADER --}}
                <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                    <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                        style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                    </div>
                    <div class="relative flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10 dark:bg-teal-400/15">
                                    <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Kirim Satu Sehat</h2>
                                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                        <span class="font-semibold">{{ $dataDaftarPoliRJ['regName'] ?? '-' }}</span>
                                        &mdash; RM: {{ $dataDaftarPoliRJ['regNo'] ?? '-' }}
                                        &mdash; RJ: {{ $rjNo ?? '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- KIRIM SEMUA — menjalankan seluruh langkah berurutan, satu per putaran. --}}
                        <div class="flex items-center gap-2 ml-auto">
                            @if (!empty($antrianKirim) || $langkahAktif !== '')
                                {{-- Spinner dirender apa adanya, TIDAK lewat wire:loading: tiap langkah
                                     dijalankan oleh komponen ANAK, jadi wadah ini sendiri tidak sedang
                                     memuat dan wire:loading di sini takkan pernah menyala. Selama antrean
                                     belum habis, prosesnya memang sedang berjalan — itu yang ditandai. --}}
                                <span class="inline-flex items-center gap-1.5 text-xs text-muted dark:text-gray-400">
                                    <x-loading class="text-teal-600 dark:text-teal-400" />
                                    Mengirim <span class="font-semibold">{{ $langkahAktif ?: '…' }}</span>
                                    &middot; sisa {{ count($antrianKirim) }} langkah
                                </span>
                                <x-danger-button type="button" wire:click="batalkanKirimSemua">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-satu-sehat.ikon-tombol jenis="hentikan" />
                                        Hentikan
                                    </span>
                                </x-danger-button>
                            @else
                                <x-confirm-button variant="primary" action="kirimSemua()"
                                    title="Kirim semua langkah ke SATUSEHAT?"
                                    message="{{ $this->jumlahLangkah() }} langkah dijalankan berurutan mulai dari Encounter. Langkah yang datanya belum lengkap akan ditolak dan dilewati — rantai tetap lanjut. Kiriman yang sudah berangkat TIDAK bisa dibatalkan."
                                    confirmText="Ya, Kirim Semua" wire:key="kirim-semua-{{ $rjNo ?? 'none' }}">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-satu-sehat.ikon-tombol />
                                        Kirim Semua
                                    </span>
                                </x-confirm-button>
                            @endif
                        </div>

                        <x-icon-button color="gray" type="button" wire:click="closeModal">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>

                {{-- BODY — kartu SFC self-contained --}}
                <div class="relative flex-1 px-6 py-6 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                    {{-- PENGHALANG selama Kirim Semua berjalan. Tiap langkah menunggu jawaban
                         SATUSEHAT, jadi jendela untuk salah klik lebar: tanpa ini petugas bisa
                         menekan Kirim di kartu lain di tengah rantai dan mengirim satu langkah
                         dua kali. Dipasang di wadah, bukan menonaktifkan tiap tombol satu per
                         satu — dan sekalian jadi penanda visual bahwa proses sedang jalan.
                         Header di atasnya tetap bisa diklik, jadi tombol Hentikan tetap hidup. --}}
                    @if (!empty($antrianKirim) || $langkahAktif !== '')
                        <div class="absolute inset-0 z-20 cursor-not-allowed bg-surface-soft/60 dark:bg-gray-950/40"
                            title="Kirim Semua sedang berjalan — tekan Hentikan di atas untuk membatalkan sisanya.">
                        </div>
                    @endif

                    {{-- Grid, bukan tumpukan: modal ini full-width dan kartu yang berderet ke
                         bawah menyisakan dua pertiga layar kosong. items-start supaya kartu
                         yang pratinjaunya dibuka memanjang sendiri, tidak ikut menarik tinggi
                         tetangga sebarisnya. --}}
                    <div class="grid items-start max-w-7xl gap-3 mx-auto grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                        <livewire:pages::transaksi.rj.satu-sehat.kirim-encounter :rjNo="$rjNo" bagian="kirim"
                            wire:key="ss-encounter-rj-{{ $rjNo ?? 'none' }}" />
                        <livewire:pages::transaksi.rj.satu-sehat.kirim-condition :rjNo="$rjNo"
                            wire:key="ss-condition-rj-{{ $rjNo ?? 'none' }}" />
                        <livewire:pages::transaksi.rj.satu-sehat.kirim-observation :rjNo="$rjNo"
                            wire:key="ss-observation-rj-{{ $rjNo ?? 'none' }}" />
                        <livewire:pages::transaksi.rj.satu-sehat.kirim-procedure :rjNo="$rjNo"
                            wire:key="ss-procedure-rj-{{ $rjNo ?? 'none' }}" />
                        <livewire:pages::transaksi.rj.satu-sehat.kirim-medication-request :rjNo="$rjNo"
                            wire:key="ss-medication-request-rj-{{ $rjNo ?? 'none' }}" />

                        {{-- Kartu penutup melebar SATU BARIS PENUH: langkah terakhir rantai,
                             jadi menyelipkannya di tengah baris grid akan mengaburkan urutannya.
                             Instance kedua kartu Encounter — bagian 'selesai' hanya menjawab
                             event finish, bagian 'kirim' di atas hanya menjawab event kirim. --}}
                        <div class="md:col-span-2 xl:col-span-3 space-y-3">
                            <livewire:pages::transaksi.rj.satu-sehat.kirim-encounter :rjNo="$rjNo" bagian="selesai"
                                wire:key="ss-encounter-selesai-rj-{{ $rjNo ?? 'none' }}" />
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-modal>
</div>
