<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Support\Skema\KamusData;
use App\Support\Skema\ModulTabel;

// Panduan dev: STRUKTUR TABEL siklik — peta rename prefix (11 Sep 2026), pengelompokan
// modul, relasi antar tabel (FK terdeklarasi + relasi implisit), dan rincian kolom.
// Semua data dibaca langsung dari data dictionary Oracle lewat App\Support\Skema\KamusData,
// jadi halaman ini selalu mencerminkan DB yang sedang terhubung. Versi statis untuk
// dibaca di repo: docs/struktur-tabel.md (php artisan siklik:dok-tabel).
new class extends Component {
    /** Kata kunci pencarian di bagian Peta Rename. */
    public string $cari = '';

    /** Tabel yang sedang dibuka di bagian Rincian Tabel. */
    public string $tabelDipilih = 'SKTXN_RJHDRS';

    /** Kata kunci pencarian nama tabel di bagian Rincian Tabel. */
    public string $cariTabel = '';

    #[Computed]
    public function peta(): array
    {
        $kunci = strtoupper(trim($this->cari));
        $semua = KamusData::petaRename();
        if ($kunci === '') {
            return $semua;
        }

        return array_values(array_filter(
            $semua,
            fn ($p) => str_contains($p['lama'], $kunci) || str_contains($p['baru'], $kunci) || str_contains(strtoupper($p['modul']), $kunci)
        ));
    }

    #[Computed]
    public function perModul(): array
    {
        return KamusData::perModul();
    }

    #[Computed]
    public function keteranganModul(): array
    {
        return ModulTabel::daftar();
    }

    #[Computed]
    public function relasiPerModul(): array
    {
        $hasil = [];
        foreach (KamusData::relasi() as $r) {
            $hasil[$r['modul']][] = $r;
        }

        return $hasil;
    }

    #[Computed]
    public function implisitPerModul(): array
    {
        $hasil = [];
        foreach (KamusData::relasiImplisit() as $r) {
            $hasil[$r['modul']][] = $r;
        }

        return $hasil;
    }

    #[Computed]
    public function daftarNamaTabel(): array
    {
        $kunci = strtoupper(trim($this->cariTabel));
        $nama = array_map(fn ($t) => $t['nama'], KamusData::tabel());
        sort($nama);

        return $kunci === '' ? $nama : array_values(array_filter($nama, fn ($n) => str_contains($n, $kunci)));
    }

    #[Computed]
    public function kolom(): array
    {
        return $this->tabelDipilih === '' ? [] : KamusData::kolom($this->tabelDipilih);
    }

    #[Computed]
    public function relasiTabel(): array
    {
        return $this->tabelDipilih === '' ? ['keluar' => [], 'masuk' => []] : KamusData::relasiTabel($this->tabelDipilih);
    }

    #[Computed]
    public function implisitTabel(): array
    {
        $nama = $this->tabelDipilih;

        return array_values(array_filter(
            KamusData::relasiImplisit(),
            fn ($r) => $r['anak'] === $nama || $r['induk'] === $nama
        ));
    }

    #[Computed]
    public function modulTabelDipilih(): string
    {
        return $this->tabelDipilih === '' ? '' : ModulTabel::dari($this->tabelDipilih);
    }

    #[Computed]
    public function ringkasan(): array
    {
        $tabel = KamusData::tabel();

        return [
            'tabel' => count(array_filter($tabel, fn ($t) => $t['jenis'] === 'TABLE')),
            'view' => count(array_filter($tabel, fn ($t) => $t['jenis'] === 'VIEW')),
            'relasi' => count(KamusData::relasi()),
            'implisit' => count(KamusData::relasiImplisit()),
            'rename' => count(KamusData::petaRename()),
        ];
    }

    public function mermaid(string $modul): string
    {
        return KamusData::mermaid($modul);
    }

    public function pilihTabel(string $nama): void
    {
        $this->tabelDipilih = strtoupper($nama);
        unset($this->kolom, $this->relasiTabel, $this->implisitTabel, $this->modulTabelDipilih);
    }

    public function prefixBaru(): array
    {
        return ModulTabel::prefix();
    }

    public function prefixLama(): array
    {
        return ModulTabel::prefixLama();
    }
};
?>

<div>
    <x-page-title title="Struktur Tabel"
        subtitle="Panduan dev: peta rename prefix SK, modul, relasi antar tabel & rincian kolom (dibaca langsung dari Oracle)" />

    @php
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan & Aturan Nama',
                'peta' => 'Peta Rename (lama → baru)',
            ],
            'Struktur' => [
                'modul' => 'Modul & Relasi Antar Tabel',
                'tabel' => 'Rincian Tabel & Kolom',
            ],
            'Operasional' => [
                'eksekusi' => 'Cara Eksekusi Rename',
                'konvensi' => 'Konvensi Tabel Baru',
            ],
        ];
        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            salin(teks, el) {
                navigator.clipboard.writeText(teks).then(() => {
                    const asal = el.textContent; el.textContent = "Tersalin"; setTimeout(() => el.textContent = asal, 1200);
                });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="px-6 pt-4 pb-16">
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-5">
                            <div class="px-3 mb-1 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}'
                                            ? 'bg-gray-100 font-semibold text-gray-900 dark:bg-gray-700 dark:text-white'
                                            : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4 text-xs leading-relaxed text-gray-500 border-t border-gray-200 dark:border-gray-700 dark:text-gray-400">
                        Sumber data: data dictionary Oracle yang sedang terhubung.<br>
                        Versi statis: <code>docs/struktur-tabel.md</code><br>
                        Kode: <code>App\Support\Skema\KamusData</code>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main class="min-w-0 text-gray-700 dark:text-gray-200">
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        @include('pages::panduan-dev.struktur-tabel.struktur-tabel-pendahuluan')
                    </section>
                    <section x-show="section === 'peta'" x-cloak>
                        @include('pages::panduan-dev.struktur-tabel.struktur-tabel-peta')
                    </section>
                    <section x-show="section === 'modul'" x-cloak>
                        @include('pages::panduan-dev.struktur-tabel.struktur-tabel-modul')
                    </section>
                    <section x-show="section === 'tabel'" x-cloak>
                        @include('pages::panduan-dev.struktur-tabel.struktur-tabel-tabel')
                    </section>
                    <section x-show="section === 'eksekusi'" x-cloak>
                        @include('pages::panduan-dev.struktur-tabel.struktur-tabel-eksekusi')
                    </section>
                    <section x-show="section === 'konvensi'" x-cloak>
                        @include('pages::panduan-dev.struktur-tabel.struktur-tabel-konvensi')
                    </section>
                </main>
            </div>
        </div>
    </div>
</div>
