@props([
    'wireModel',                       // contoh: 'formEresep.catatanKhusus' atau 'dataDaftarPoliRJ.eresep.0.catatanKhusus'
    'options' => [],                   // array string catatan (dari skmst_signa_catatans active)
    'disabled' => false,
    'placeholder' => 'Catatan Khusus',
    'inputId' => null,                 // id input — dipakai parent untuk focus via document.getElementById
    'enterAction' => null,             // ekspresi Alpine yg dijalankan saat Enter & dropdown tertutup
    'maxlength' => 255,
    'error' => false,
])

{{--
    Combobox CATATAN SIGNA (e-resep) — pilih dari daftar atau ketik bebas.

    Perilaku & markupnya milik <x-combobox> (mode teks bebas); di sini tinggal daftarnya.
    Catatan signa yang tak ada di master tetap sah, jadi ketikan petugas TIDAK pernah
    dirapikan saat blur dan Enter dipakai untuk `enterAction`, bukan memilih baris.
--}}
<x-combobox :wire-model="$wireModel" :options="$options" :disabled="$disabled" :placeholder="$placeholder"
    :input-id="$inputId" :enter-action="$enterAction" :maxlength="$maxlength" :error="$error"
    judul-daftar="daftar catatan" {{ $attributes }} />
