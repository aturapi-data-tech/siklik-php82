{{--
    Toolbar halaman Laba Rugi (partial ⚡laba-rugi).
    Butuh dari @include: $labelRentang, $labaBersihBulan, $labaBersihYtd.
    Property komponen ($periode, $hppManualAktif) diwarisi otomatis dari view induk.
    Butuh Alpine tampilkanAkun / tampilkanHpp / caraPakai dari x-data root halaman.
--}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div>
                            <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <div class="flex items-stretch gap-1">
                                <x-secondary-button type="button" wire:click="prevMonth" class="px-3" title="Bulan sebelumnya">◀</x-secondary-button>
                                <x-text-input id="periodeInput" type="text" wire:model.live.debounce.500ms="periodeInput"
                                    placeholder="01/2026" maxlength="7" class="font-mono text-center w-28" />
                                <x-secondary-button type="button" wire:click="nextMonth" class="px-3" title="Bulan berikutnya">▶</x-secondary-button>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                @if ($periode !== '')
                                    {{ $labelRentang }}
                                @else
                                    <span class="text-rose-600">Format: mm/yyyy</span>
                                @endif
                            </p>
                        </div>

                        <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 cursor-pointer select-none dark:text-gray-200">
                            <input type="checkbox" x-model="tampilkanAkun" class="w-4 h-4 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-900">
                            Tampilkan akun
                        </label>
                        <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 cursor-pointer select-none dark:text-gray-200">
                            <input type="checkbox" x-model="tampilkanHpp" class="w-4 h-4 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-900">
                            Rincian HPP
                        </label>
                        <button type="button" x-on:click="caraPakai = !caraPakai"
                            class="pb-2 text-sm text-left text-blue-700 underline dark:text-blue-300">Cara pakai</button>
                    </div>

                    @if ($periode !== '')
                        <div class="grid grid-cols-2 gap-3 text-right">
                            <div class="px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/40 dark:border-gray-700">
                                <div class="text-[10px] tracking-wider text-gray-500 uppercase">Laba Bersih · Bulan</div>
                                <div class="font-mono text-lg font-bold {{ $labaBersihBulan < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                    Rp {{ number_format($labaBersihBulan, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-4 py-2 border rounded-lg bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                                <div class="text-[10px] tracking-wider text-emerald-700 uppercase dark:text-emerald-300">Laba Bersih · YTD</div>
                                <div class="font-mono text-lg font-bold {{ $labaBersihYtd < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                    Rp {{ number_format($labaBersihYtd, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                @include('pages::transaksi.keuangan.laba-rugi.laba-rugi-hpp-override')
            </div>
