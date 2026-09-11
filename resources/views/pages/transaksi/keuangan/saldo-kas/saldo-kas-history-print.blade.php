{{-- resources/views/pages/transaksi/keuangan/saldo-kas/saldo-kas-history-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="REKAP RIWAYAT TRANSAKSI KAS">

    @php
        // Semua tanggal sudah dipra-format di cetakRekap() — di sini hanya format angka.
        $formatAngka = fn($nilai) => number_format((float) $nilai, 0, ',', '.');
        $namaKlinik = $identitas->int_name ?? config('app.name');
        $alamatKlinik = trim(($identitas->int_address ?? '') . (!empty($identitas->int_city) ? ', ' . $identitas->int_city : ''));
    @endphp

    {{-- ── KOP RINGKAS (SKMST_IDENTITASES) ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px; font-size:11px;">
        <tr>
            <td style="font-weight:bold; font-size:12px;">{{ $namaKlinik }}</td>
            <td style="text-align:right; color:#555;">{{ $alamatKlinik }}</td>
        </tr>
        @if (!empty($identitas->int_phone1))
            <tr>
                <td colspan="2" style="text-align:right; color:#555;">Telp. {{ $identitas->int_phone1 }}</td>
            </tr>
        @endif
    </table>

    {{-- ── INFO AKUN & PERIODE ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px; font-size:11px;">
        <tr>
            <td style="width:75px; color:#555; padding:1px 0;">Akun Kas</td>
            <td style="width:8px;">:</td>
            <td style="font-weight:bold;">{{ $accId }} — {{ $accDesc }}</td>
            <td style="width:70px; color:#555;">Tampilan</td>
            <td style="width:8px;">:</td>
            <td style="font-weight:bold; width:120px;">{{ $modeLabel }}</td>
        </tr>
        <tr>
            <td style="color:#555; padding:1px 0;">Cara Bayar</td>
            <td>:</td>
            <td style="font-weight:bold;">{{ $cbId }} — {{ $cbDesc }}</td>
            <td style="color:#555;">Dicetak</td>
            <td>:</td>
            <td>{{ $dicetakPada }}</td>
        </tr>
        <tr>
            <td style="color:#555; padding:1px 0;">Periode</td>
            <td>:</td>
            <td colspan="4" style="font-weight:bold;">{{ $periodeLabel }}</td>
        </tr>
    </table>

    {{-- ── RINGKASAN ── --}}
    <table width="100%" cellpadding="5" cellspacing="0" style="margin-bottom:10px; font-size:11px; border-collapse:collapse; text-align:center;">
        <tr>
            <td style="border:1px solid #999; background:#f3f4f6; width:25%;">Saldo Awal<br><b>{{ $formatAngka($saldoAwal) }}</b></td>
            <td style="border:1px solid #999; background:#eff6ff; width:25%;">Debit<br><b>{{ $formatAngka($totalDebit) }}</b></td>
            <td style="border:1px solid #999; background:#fff1f2; width:25%;">Kredit<br><b>{{ $formatAngka($totalKredit) }}</b></td>
            <td style="border:1px solid #999; background:#ecfdf5; width:25%;">Saldo Akhir<br><b style="font-size:12px;">{{ $formatAngka($saldoAkhir) }}</b></td>
        </tr>
    </table>

    {{-- ── REKAP PER JENIS ── --}}
    @if ($rekapJenis->count() > 0)
        <table width="100%" cellpadding="3" cellspacing="0" style="margin-bottom:10px; font-size:10px; border-collapse:collapse;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="border:1px solid #999; text-align:left;">JENIS TRANSAKSI</th>
                    <th style="border:1px solid #999; text-align:right; width:70px;">JUMLAH</th>
                    <th style="border:1px solid #999; text-align:right; width:110px;">NOMINAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rekapJenis as $rekap)
                    <tr>
                        <td style="border:1px solid #999;">{{ $rekap->jenis }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $rekap->jumlah }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($rekap->nominal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── REKAP HARIAN (dipakai bila detail transaksi terlalu banyak untuk dicetak) ── --}}
    @unless ($detailPenuh)
        <p style="font-size:10px; color:#8a6d1b; background:#fff8e1; border:1px solid #e6c200; padding:5px; margin-bottom:8px;">
            Periode ini memuat {{ number_format($transaksiList->count(), 0, ',', '.') }} transaksi (batas cetak detail
            {{ number_format($batasDetail, 0, ',', '.') }}), jadi yang dicetak adalah <b>rekap harian</b>.
            Untuk detail per transaksi, cetak per tanggal lewat tampilan Harian.
        </p>

        <table width="100%" cellpadding="3" cellspacing="0" style="font-size:10px; border-collapse:collapse;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="border:1px solid #999; text-align:left; width:80px;">TANGGAL</th>
                    <th style="border:1px solid #999; text-align:right; width:70px;">TRX</th>
                    <th style="border:1px solid #999; text-align:right;">DEBIT</th>
                    <th style="border:1px solid #999; text-align:right;">KREDIT</th>
                    <th style="border:1px solid #999; text-align:right; width:110px;">SALDO AKHIR HARI</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4" style="border:1px solid #999; font-style:italic; color:#555;">Saldo per {{ $saldoAwalTgl }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($saldoAwal) }}</td>
                </tr>
                @foreach ($rekapHarian as $hari)
                    <tr>
                        <td style="border:1px solid #999;">{{ $hari->tglLabel }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $hari->jumlah }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($hari->debit) }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($hari->kredit) }}</td>
                        <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($hari->saldoAkhir) }}</td>
                    </tr>
                @endforeach
                <tr style="background:#ecfdf5;">
                    <td colspan="2" style="border:1px solid #999; font-weight:bold;">SALDO PER {{ $sampaiLabel }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($totalDebit) }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($totalKredit) }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold; font-size:11px;">{{ $formatAngka($saldoAkhir) }}</td>
                </tr>
            </tbody>
        </table>
    @endunless

    {{-- ── TABEL TRANSAKSI ── --}}
    @if ($detailPenuh)
    <table width="100%" cellpadding="3" cellspacing="0" style="font-size:10px; border-collapse:collapse;">
        <thead>
            <tr style="background:#f3f4f6;">
                <th style="border:1px solid #999; text-align:left; width:64px;">TANGGAL</th>
                <th style="border:1px solid #999; text-align:left;">DESKRIPSI</th>
                <th style="border:1px solid #999; text-align:left; width:120px;">LAWAN AKUN</th>
                <th style="border:1px solid #999; text-align:right; width:74px;">DEBIT</th>
                <th style="border:1px solid #999; text-align:right; width:74px;">KREDIT</th>
                <th style="border:1px solid #999; text-align:right; width:84px;">SALDO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5" style="border:1px solid #999; font-style:italic; color:#555;">Saldo per {{ $saldoAwalTgl }}</td>
                <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($saldoAwal) }}</td>
            </tr>

            @if ($mode === 'shift' && count($shiftGroups) > 0)
                @foreach ($shiftGroups as $group)
                    <tr style="background:#e8f5ee;">
                        <td colspan="6" style="border:1px solid #999; font-weight:bold;">
                            SHIFT {{ $group->shift }}@if ($group->range) ({{ $group->range }})@endif
                        </td>
                    </tr>
                    @foreach ($group->items as $transaksi)
                        <tr>
                            <td style="border:1px solid #999; white-space:nowrap;">
                                {{ $transaksi->tglLabel }}<br>
                                <span style="color:#777;">{{ $transaksi->jamLabel }}</span>
                            </td>
                            <td style="border:1px solid #999;">{{ $transaksi->deskripsi }}</td>
                            <td style="border:1px solid #999; color:#555;">
                                {{ $transaksi->lawanAccId }}@if (!empty($transaksi->lawanAccDesc))<br><span style="font-size:9px;">{{ $transaksi->lawanAccDesc }}</span>@endif
                            </td>
                            <td style="border:1px solid #999; text-align:right;">{{ $transaksi->debit > 0 ? $formatAngka($transaksi->debit) : '—' }}</td>
                            <td style="border:1px solid #999; text-align:right;">{{ $transaksi->kredit > 0 ? $formatAngka($transaksi->kredit) : '—' }}</td>
                            <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($transaksi->saldoBerjalan) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background:#f3f4f6;">
                        <td colspan="3" style="border:1px solid #999; text-align:right; font-weight:bold;">Subtotal Shift {{ $group->shift }}</td>
                        <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($group->subtotalDebit) }}</td>
                        <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($group->subtotalKredit) }}</td>
                        <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($group->saldoAkhir) }}</td>
                    </tr>
                @endforeach
            @else
                @forelse ($transaksiList as $transaksi)
                    <tr>
                        <td style="border:1px solid #999; white-space:nowrap;">
                            {{ $transaksi->tglLabel }}<br>
                            <span style="color:#777;">{{ $transaksi->jamLabel }}</span>
                        </td>
                        <td style="border:1px solid #999;">{{ $transaksi->deskripsi }}</td>
                        <td style="border:1px solid #999; color:#555;">
                            {{ $transaksi->lawanAccId }}@if (!empty($transaksi->lawanAccDesc))<br><span style="font-size:9px;">{{ $transaksi->lawanAccDesc }}</span>@endif
                        </td>
                        <td style="border:1px solid #999; text-align:right;">{{ $transaksi->debit > 0 ? $formatAngka($transaksi->debit) : '—' }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $transaksi->kredit > 0 ? $formatAngka($transaksi->kredit) : '—' }}</td>
                        <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($transaksi->saldoBerjalan) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="border:1px solid #999; text-align:center; padding:14px; color:#777;">
                            Tidak ada transaksi pada periode ini.
                        </td>
                    </tr>
                @endforelse
            @endif

            @if ($transaksiList->count() > 0)
                <tr style="background:#ecfdf5;">
                    <td colspan="3" style="border:1px solid #999; font-weight:bold;">SALDO PER {{ $sampaiLabel }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($totalDebit) }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold;">{{ $formatAngka($totalKredit) }}</td>
                    <td style="border:1px solid #999; text-align:right; font-weight:bold; font-size:11px;">{{ $formatAngka($saldoAkhir) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
    @endif

</x-pdf.layout-a4-with-out-background>
