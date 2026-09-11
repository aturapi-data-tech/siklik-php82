{{--
    Panel override HPP manual (partial ⚡laba-rugi).
    Butuh: $hppManualAktif (public property komponen).
    Partial TIDAK mewarisi `use` blok atas — di sini tidak ada pemanggilan class.
--}}
<div class="pt-3 mt-3 border-t border-gray-300 border-dashed dark:border-gray-600">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-3">
            <x-toggle wire:model.live="hppManualAktif" :trueValue="true" :falseValue="false" />
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Override HPP manual</div>
                <p class="mt-0.5 max-w-md text-[11px] text-gray-500 dark:text-gray-400">
                    Stock opname belum rutin dan saldo awal persediaan belum lengkap, sehingga HPP
                    otomatis bisa menyimpang jauh. Isi nilai HPP yang benar di sini supaya laba tetap
                    bisa dihitung. Nilai ini hanya hidup di layar — tidak ditulis ke database.
                </p>
            </div>
        </div>
        @if ($hppManualAktif)
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="hppManualBulan" value="HPP Bulan Ini" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                    <x-text-input-number id="hppManualBulan" wire:model="hppManualBulan" class="w-40" />
                </div>
                <div>
                    <x-input-label for="hppManualYtd" value="HPP YTD" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                    <x-text-input-number id="hppManualYtd" wire:model="hppManualYtd" class="w-40" />
                </div>
            </div>
        @endif
    </div>
</div>
