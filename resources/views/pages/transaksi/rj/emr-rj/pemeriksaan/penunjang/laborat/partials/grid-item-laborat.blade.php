{{--
    Grid pilihan item pemeriksaan laboratorium (modal Order Laboratorium RJ).
    Dipisah dari SFC induk (⚡rm-laborat-rj-actions) supaya file induk tetap ringkas.
    Memakai state komponen induk: $this->items, $this->isSelected(), wire:click toggleItem.
--}}
            {{-- Item Grid --}}
            <div class="flex-1 p-5 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @forelse ($this->items as $item)
                        @php $selected = $this->isSelected($item->clabitem_id); @endphp
                        <button type="button"
                            wire:click="toggleItem('{{ $item->clabitem_id }}', '{{ addslashes($item->clabitem_desc) }}', {{ $item->price ?? 'null' }}, '{{ $item->item_code }}')"
                            class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                {{ $selected
                                    ? 'border-brand-green bg-brand-green/10 text-brand-green shadow-sm'
                                    : 'border-gray-200 bg-white hover:border-brand-green/40 hover:bg-brand-green/5 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">

                            {{-- Checkmark --}}
                            @if ($selected)
                                <span
                                    class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand-green rounded-full">
                                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            @endif

                            <p class="text-xs font-medium leading-tight">{{ $item->clabitem_desc }}</p>

                            @if ($item->price)
                                <p class="mt-1 text-[10px] {{ $selected ? 'text-brand-green/70' : 'text-gray-400' }}">
                                    {{ number_format($item->price) }}
                                </p>
                            @endif
                        </button>
                    @empty
                        <div class="py-12 text-center text-gray-400 col-span-full">
                            <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-sm">Tidak ada item ditemukan</p>
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if ($this->items->hasPages())
                    <div class="mt-4">
                        {{ $this->items->links() }}
                    </div>
                @endif
            </div>
