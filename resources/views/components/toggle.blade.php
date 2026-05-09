{{-- resources/views/components/toggle.blade.php --}}
{{--
    Pemakaian:
      Mode 1 (model binding):
        <x-toggle wire:model.live="passStatus" trueValue="N" falseValue="O" label="Pasien Baru" :disabled="$isFormLocked" />
        <x-toggle wire:model.live="dataDaftarUGD.passStatus" trueValue="N" falseValue="O" label="Pasien Baru" />
        <x-toggle wire:model.live="activeStatus" trueValue="1" falseValue="0">Status Aktif</x-toggle>
        <x-toggle wire:model.live="forceFlag" :trueValue="true" :falseValue="false" label="Paksa" />

      Mode 2 (per-row di table — pakai `current` + `wireClick`):
        <x-toggle :current="$row->active_record" trueValue="1" falseValue="0"
                  wireClick="toggleActive('{{ $row->emp_id }}')">Aktif</x-toggle>

    Catatan:
      - Pure server-side render (visual + click via wire:click) — bebas race condition.
      - Mendukung nested array (dataDaftarXxx.field) tanpa @entangle.
      - Click memicu wire:click="$set(...)" (Mode 1) atau wire:click="..." (Mode 2).
--}}

@props([
    'trueValue' => 'Y',
    'falseValue' => 'N',
    'label' => null,
    'disabled' => false,
    'current' => null,
    'wireClick' => null,
    'size' => 'sm',
])

@php
    $wireModel = $attributes->whereStartsWith('wire:model')->first();

    $currentValue = null;
    if ($current !== null) {
        $currentValue = $current;
    } elseif ($wireModel && isset($__livewire)) {
        try {
            $currentValue = data_get($__livewire, $wireModel);
        } catch (\Throwable) {
        }
    }
    $currentValue ??= $falseValue;

    $isOn = $currentValue == $trueValue;
    $nextValue = $isOn ? $falseValue : $trueValue;

    // Encode value untuk wire:click="$set(...)" expression
    $encode = fn($v) => match (true) {
        is_bool($v)    => $v ? 'true' : 'false',
        is_int($v),
        is_float($v)   => (string) $v,
        is_null($v)    => 'null',
        default        => "'" . addslashes((string) $v) . "'",
    };

    $attrs = $attributes->whereDoesntStartWith('wire:model');

    $trackClass = match (true) {
        $isOn && $disabled => 'bg-gray-400',
        $isOn => 'bg-brand',
        !$isOn && $disabled => 'bg-gray-200',
        default => 'bg-gray-300',
    };

    [$trackSize, $thumbSize, $thumbOn, $thumbOff, $labelSize] = match ($size) {
        'lg'    => ['h-8 w-14',  'w-6 h-6 mt-1',   'translate-x-7 ml-1', 'translate-x-1', 'text-base'],
        'md'    => ['h-7 w-[3.25rem]', 'w-5 h-5 mt-1', 'translate-x-7 ml-1', 'translate-x-1', 'text-sm'],
        default => ['h-6 w-11',  'w-4 h-4 mt-1',   'translate-x-6 ml-1', 'translate-x-1', 'text-sm'],
    };
    $thumbClass = $isOn ? $thumbOn : $thumbOff;
@endphp

<div
    @if (!$disabled)
        @if ($wireModel)
            wire:click="$set('{{ $wireModel }}', {{ $encode($nextValue) }})"
        @elseif ($wireClick)
            wire:click="{{ $wireClick }}"
        @endif
    @endif
    {{ $attrs->merge([
        'class' => 'flex items-center space-x-2 ' . ($disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'),
    ]) }}>
    <div class="transition rounded-full {{ $trackSize }} {{ $trackClass }}">
        <div class="transition transform bg-white rounded-full shadow {{ $thumbSize }} {{ $thumbClass }}"></div>
    </div>

    @if ($label)
        <span class="block font-medium text-gray-700 dark:text-gray-300 {{ $labelSize }} {{ $disabled ? 'opacity-60' : '' }}">{{ $label }}</span>
    @elseif (trim((string) $slot) !== '')
        <span class="block font-medium text-gray-700 dark:text-gray-300 {{ $labelSize }} {{ $disabled ? 'opacity-60' : '' }}">{{ $slot }}</span>
    @endif
</div>
