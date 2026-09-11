<?php
// resources/views/pages/transaksi/apotek/apotek.blade.php
// Wrapper Antrian Apotek — RJ only (klinik pratama, tidak ada UGD)

use Livewire\Component;

new class extends Component {
    public string $activeTab = 'rj';
};
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- Judul di topbar (sebelah logo) — pola master --}}
        <x-page-title title="Antrian Apotek" subtitle="Telaah resep & pelayanan kefarmasian — Rawat Jalan" />

        {{-- CONTENT — langsung antrian RJ --}}
        <div class="mt-4">
            <livewire:pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj
                wire:key="antrian-apotek-rj-wrapper" />
        </div>

    </div>
</div>
