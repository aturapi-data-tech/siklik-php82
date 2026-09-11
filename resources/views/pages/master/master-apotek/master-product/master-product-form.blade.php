{{--
    Isi form Master Produk Apotek — dipisah dari ⚡master-product-actions.blade.php
    supaya SFC-nya tetap di bawah batas panjang berkas repo.

    Partial ini MURNI template: semua state ($form, $formMode, $kolomKfaAda) dan
    $errors diwarisi dari komponen pemanggil. Jangan menaruh logika di sini —
    partial tidak mewarisi blok `use` milik SFC.
--}}
                {{-- Section 1: Identitas --}}
                <x-border-form title="Identitas Produk">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Produk" />
                                <x-text-input wire:model.live="form.product_id" x-ref="inputProductId"
                                    maxlength="25"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.product_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.product_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Produk" />
                                <x-text-input wire:model.live="form.product_name" x-ref="inputProductName"
                                    maxlength="100"
                                    :error="$errors->has('form.product_name')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.product_name')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Tipe" />
                                <x-select-input wire:model.live="form.product_type"
                                    :error="$errors->has('form.product_type')"
                                    class="w-full mt-1">
                                    <option value="OBT">OBAT</option>
                                    <option value="ALK">ALAT KESEHATAN</option>
                                    <option value="BHP">BAHAN HABIS PAKAI</option>
                                    <option value="LAB">LABORATORIUM</option>
                                    <option value="LAY">LAYANAN/JASA</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.product_type')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Rak / Lokasi (opsional)" />
                                <x-text-input wire:model.live="form.product_rak"
                                    maxlength="100"
                                    :error="$errors->has('form.product_rak')"
                                    class="w-full mt-1 uppercase" placeholder="A1-3" />
                                <x-input-error :messages="$errors->get('form.product_rak')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>

                {{-- Section 2: Klasifikasi --}}
                <x-border-form title="Klasifikasi (Kategori / Satuan / Supplier)">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Kategori" />
                            <x-select-input wire:model.live="form.cat_id"
                                :error="$errors->has('form.cat_id')"
                                class="w-full mt-1">
                                <option value="">— Pilih Kategori —</option>
                                @foreach ($this->categories as $c)
                                    <option value="{{ $c->cat_id }}">{{ $c->cat_desc }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.cat_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Satuan (UOM)" />
                            <x-select-input wire:model.live="form.uom_id"
                                :error="$errors->has('form.uom_id')"
                                class="w-full mt-1">
                                <option value="">— Pilih Satuan —</option>
                                @foreach ($this->uoms as $u)
                                    <option value="{{ $u->uom_id }}">{{ $u->uom_desc }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.uom_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Supplier" />
                            <x-select-input wire:model.live="form.supp_id"
                                :error="$errors->has('form.supp_id')"
                                class="w-full mt-1">
                                <option value="">— Pilih Supplier —</option>
                                @foreach ($this->suppliers as $s)
                                    <option value="{{ $s->supp_id }}">{{ $s->supp_name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.supp_id')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>

                {{-- Section 3: Harga & Margin --}}
                <x-border-form title="Harga (HPP &amp; Jual)">
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="HPP (Cost Price)" />
                                <x-text-input wire:model.live="form.cost_price"
                                    type="number" min="0" step="100"
                                    :error="$errors->has('form.cost_price')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.cost_price')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Harga Jual" />
                                <x-text-input wire:model.live="form.sales_price"
                                    type="number" min="0" step="100"
                                    :error="$errors->has('form.sales_price')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.sales_price')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Margin (%) — auto" />
                                <x-text-input wire:model.live="form.margin_persen"
                                    type="number" step="0.01"
                                    :error="$errors->has('form.margin_persen')"
                                    class="w-full mt-1 bg-gray-50 dark:bg-gray-800" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Auto-hitung dari HPP &amp; Harga Jual; bisa di-override manual.
                                </p>
                                <x-input-error :messages="$errors->get('form.margin_persen')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>

                {{-- Section 4: Stok --}}
                <x-border-form title="Stok &amp; Status">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Qty per Box (opsional)" />
                            <x-text-input wire:model.live="form.qty_box"
                                type="number" min="0" step="0.01"
                                :error="$errors->has('form.qty_box')"
                                class="w-full mt-1" />
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Mis. 1 box = 100 strip</p>
                            <x-input-error :messages="$errors->get('form.qty_box')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Limit Stok (alert)" />
                            <x-text-input wire:model.live="form.limit_stock"
                                type="number" min="0" step="1"
                                :error="$errors->has('form.limit_stock')"
                                class="w-full mt-1" />
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Alert kalau stok di bawah angka ini</p>
                            <x-input-error :messages="$errors->get('form.limit_stock')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Status" />
                            <x-select-input wire:model.live="form.active_status"
                                :error="$errors->has('form.active_status')"
                                class="w-full mt-1">
                                <option value="1">AKTIF</option>
                                <option value="0">NONAKTIF</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>

                {{-- Section 5: SATUSEHAT (KFA) --}}
                <x-border-form title="SATUSEHAT — Kode KFA">
                    @if ($kolomKfaAda)
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Kode KFA" />
                                <x-text-input wire:model.live="form.product_id_satusehat"
                                    maxlength="50"
                                    :error="$errors->has('form.product_id_satusehat')"
                                    class="w-full mt-1" placeholder="93001350" />
                                <x-input-error :messages="$errors->get('form.product_id_satusehat')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama KFA (opsional)" />
                                <x-text-input wire:model.live="form.product_name_satusehat"
                                    maxlength="250"
                                    :error="$errors->has('form.product_name_satusehat')"
                                    class="w-full mt-1" placeholder="Captopril 12,5 mg Tablet" />
                                <x-input-error :messages="$errors->get('form.product_name_satusehat')" class="mt-1" />
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                            Diisi manual dari Kamus Farmasi &amp; Alkes Kemenkes
                            (<span class="font-mono">kfa.kemkes.go.id</span>) — belum ada pencarian KFA otomatis.
                            Obat TANPA kode KFA <span class="font-semibold">tidak bisa dikirim</span> ke SATUSEHAT
                            (MedicationRequest &amp; MedicationDispense melewatinya dan melaporkannya).
                            Nama KFA kosong → dipakai Nama Produk.
                        </p>
                    @else
                        <p class="text-xs text-amber-600 dark:text-amber-400">
                            Kolom pemetaan KFA belum ada di <span class="font-mono">skmst_products</span>.
                            Jalankan <span class="font-mono">database/sql/2026_09_11_alter_skmst_products_add_satusehat.sql</span>
                            (atau <span class="font-mono">install_bundle_fitur_lanjutan.sql</span>) lebih dulu; sesudah itu
                            kolom Kode &amp; Nama KFA muncul di sini tanpa perubahan kode.
                        </p>
                    @endif
                </x-border-form>
