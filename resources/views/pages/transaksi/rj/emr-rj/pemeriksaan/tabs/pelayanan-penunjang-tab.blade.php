<div class="w-full mb-1">
    <div class="pt-0">
        {{-- Lab --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-laborat-rj-actions
                    :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="laborat-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
            </div>

            <table class="ds-table ds-table-entri">
                <thead>
                    <tr>
                        <th scope="col"
                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                            No Lab
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                            Tgl Lab
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                            Pemeriksaan Lab
                        </th>
                        <th scope="col"
                            class="w-8 px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @isset($dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang']['lab'])
                        @foreach ($dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang']['lab'] as $key => $pemeriksaanPenunjangLab)
                            <tr class="border-b group">
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangLab['labHdr']['labHdrNo'] ?? '' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangLab['labHdr']['labHdrDate'] ?? '' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ isset($pemeriksaanPenunjangLab['labHdr']['labDtl']) ? implode(',', array_column($pemeriksaanPenunjangLab['labHdr']['labDtl'], 'clabitem_desc')) : '' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    -
                                </td>
                            </tr>
                        @endforeach
                    @endisset
                </tbody>
            </table>
        </div>

        {{-- Rad --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.radiologi.rm-radiologi-rj-actions
                    :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="radiologi-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
            </div>

            <table class="ds-table ds-table-entri">
                <thead>
                    <tr>
                        <th scope="col"
                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                            No Rad
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                            Tgl Rad
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">
                            Pemeriksaan Rad
                        </th>
                        <th scope="col"
                            class="w-8 px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @isset($dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang']['rad'])
                        @foreach ($dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang']['rad'] as $key => $pemeriksaanPenunjangRad)
                            <tr class="border-b group">
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangRad['radHdr']['radHdrNo'] ?? '-' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangRad['radHdr']['radHdrDate'] ?? '-' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ isset($pemeriksaanPenunjangRad['radHdr']['radDtl']) ? implode(',', array_column($pemeriksaanPenunjangRad['radHdr']['radDtl'], 'rad_desc')) : '-' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    -
                                </td>
                            </tr>
                        @endforeach
                    @endisset
                </tbody>
            </table>
        </div>


    </div>
</div>
