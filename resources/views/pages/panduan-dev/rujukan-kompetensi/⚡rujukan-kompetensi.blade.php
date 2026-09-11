<?php

use Livewire\Component;

// Panduan dev: RUJUKAN BERBASIS KOMPETENSI (SRBK) versi FKTP — klinik pratama
// siklik. Alur PCare-SISRUTE (BPJS yang meneruskan ke SATUSEHAT), prasyarat
// kredensial, katalog endpoint + contoh payload, lokasi panel & node JSON,
// katalog error, mode simulasi, dan daftar hal yang masih menggantung.
//
// Gaya halaman mengikuti panduan Struktur Tabel (sidebar Alpine + section).
// Detail teknis tertulis di docs/rujukan-kompetensi.md; sumber lapangan:
// grup resmi SATUSEHAT Rujukan x PCare x VClaim (Apr-Sep 2026) + sampel JSON
// yang dibagikan di grup itu.
new class extends Component {
    //
};
?>

<div>
    <x-page-title title="Rujukan Kompetensi"
        subtitle="Panduan dev: alur SRBK FKTP (PCare-SISRUTE), endpoint, payload, katalog error & mode simulasi" />

    @php
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan & Dasar Hukum',
                'arsitektur' => 'Arsitektur FKTP',
                'prasyarat' => 'Prasyarat & Kredensial',
            ],
            'Alur & API' => [
                'alur' => 'Alur 6 Langkah & Aturan',
                'endpoint' => 'Katalog Endpoint & JSON',
            ],
            'Di siklik' => [
                'panel' => 'Lokasi Panel & Node Data',
                'simulasi' => 'Mode Simulasi',
            ],
            'Bila Bermasalah' => [
                'error' => 'Katalog Error → Penanganan',
                'faq' => 'Pertanyaan Umum',
            ],
            'Referensi' => [
                'referensi' => 'Dokumen & Sumber',
            ],
        ];
        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
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
                            <div
                                class="px-3 mb-1 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                {{ $group }}</div>
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

                    <div
                        class="px-3 pt-4 text-xs leading-relaxed text-gray-500 border-t border-gray-200 dark:border-gray-700 dark:text-gray-400">
                        Detail teknis: <code>docs/rujukan-kompetensi.md</code><br>
                        Trait: <code>App\Http\Traits\BPJS\PcareSisruteTrait</code><br>
                        Layar pantau: <a href="{{ route('rujukan.keluar') }}" wire:navigate
                            class="underline">/rujukan/keluar</a><br>
                        Sumber lapangan: grup resmi SATUSEHAT Rujukan &times; PCare &times; VClaim (Apr&ndash;Sep 2026).
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main class="min-w-0 text-gray-700 dark:text-gray-200">
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-pendahuluan')
                    </section>
                    <section x-show="section === 'arsitektur'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-arsitektur')
                    </section>
                    <section x-show="section === 'prasyarat'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-prasyarat')
                    </section>
                    <section x-show="section === 'alur'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-alur')
                    </section>
                    <section x-show="section === 'endpoint'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-endpoint')
                    </section>
                    <section x-show="section === 'panel'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-panel')
                    </section>
                    <section x-show="section === 'simulasi'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-simulasi')
                    </section>
                    <section x-show="section === 'error'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-error')
                    </section>
                    <section x-show="section === 'faq'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-faq')
                    </section>
                    <section x-show="section === 'referensi'" x-cloak>
                        @include('pages::panduan-dev.rujukan-kompetensi.rujukan-kompetensi-referensi')
                    </section>

                    {{-- ============ NAV BAWAH ============ --}}
                    <div
                        class="flex items-center justify-between pt-5 mt-12 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" x-show="idx() > 0" x-on:click="go(order[idx() - 1])"
                            class="px-3 py-1.5 text-sm rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                            &larr; <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() <= 0"></span>
                        <button type="button" x-show="idx() < order.length - 1" x-on:click="go(order[idx() + 1])"
                            class="px-3 py-1.5 text-sm font-semibold rounded-lg text-gray-900 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                            <span x-text="labels[order[idx() + 1]]"></span> &rarr;
                        </button>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>
