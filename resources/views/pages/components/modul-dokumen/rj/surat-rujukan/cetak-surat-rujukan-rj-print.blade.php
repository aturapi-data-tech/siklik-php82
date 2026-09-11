{{--
    resources/views/pages/components/modul-dokumen/rj/surat-rujukan/cetak-surat-rujukan-rj-print.blade.php

    SURAT PENGANTAR RUJUKAN (hal. 1) + RESUME KLINIS PASIEN RUJUKAN (hal. 2).
    Mengikuti format Kemkes/BPJS untuk rujukan berbasis kompetensi; dasar isi:
    Permenkes 16/2024 Pasal 17 — surat rujukan elektronik paling sedikit memuat
    identitas pasien, identitas fasyankes & unit layanan penerima, rekam medis,
    dan alasan rujukan, serta dapat dicetak sesuai kebutuhan pasien.

    Versi FKTP (klinik pratama): rujukan Rawat Jalan → FKRTL lewat PCare-SISRUTE.
    Tidak ada SEP, tidak ada jalur IGD/rawat inap, tidak ada bundle FHIR — nomor
    rujukan PCare & SATUSEHAT diterbitkan BPJS lalu disimpan di node rujukanKompetensi.

    Jebakan dompdf yang dihindari di sini: kelas Tailwind arbitrary tidak ter-render
    (dipakai inline style), atribut border="1" pada <table> diabaikan (garis ditulis
    per sel), dan blok tanda tangan memakai tinggi tetap + &nbsp; (bukan flex/<br>).
--}}

@php
    $sel = 'border:1px solid #000; padding:4px; vertical-align:top;';
    $garis = 'border-top:1px solid #000; margin-bottom:8px;';
@endphp

<x-pdf.layout-a4 title="SURAT PENGANTAR RUJUKAN">

    {{-- ── IDENTITAS PASIEN (sejajar kop) ── --}}
    <x-slot name="patientData">
        <x-pdf.identitas-pasien
            :rm="$data['pasien']['rm'] ?? null"
            :nama="$data['pasien']['nama'] ?? null"
            :jenisKelamin="$data['pasien']['jenisKelamin'] ?? null"
            :tempatLahir="$data['pasien']['tempatLahir'] ?? null"
            :tglLahir="$data['pasien']['tglLahir'] ?? null"
            :umur="$data['pasien']['umur'] ?? null"
            :alamat="$data['pasien']['alamat'] ?? null" />
    </x-slot>

    {{-- ══════════════════ HALAMAN 1 — SURAT PENGANTAR ══════════════════ --}}
    <div style="padding:0 8px;">

        {{-- Nomor rujukan & tanggal --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-bottom:10px;">
            <tr>
                <td style="width:170px; padding:1px 0;">NO. RUJUKAN SATUSEHAT</td>
                <td style="width:10px;">:</td>
                <td style="font-weight:bold;">{{ $data['noRujukanSatuSehat'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">NO. RUJUKAN PCARE</td>
                <td>:</td>
                <td style="font-weight:bold;">{{ $data['noRujukanPcare'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Tanggal Rujukan</td>
                <td>:</td>
                <td>{{ $data['tanggal'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Estimasi Kunjungan</td>
                <td>:</td>
                <td>{{ $data['estimasiRujuk'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="{{ $garis }}"></div>

        {{-- Fasyankes perujuk --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-bottom:8px;">
            <tr>
                <td style="width:170px; padding:1px 0;">Fasyankes Perujuk</td>
                <td style="width:10px;">:</td>
                <td>{{ $data['perujuk']['nama'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Kode Register</td>
                <td>:</td>
                <td>{{ $data['perujuk']['kode'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; vertical-align:top;">Alamat</td>
                <td style="vertical-align:top;">:</td>
                <td>{{ $data['perujuk']['alamat'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Poli Asal / Tgl. Layanan</td>
                <td>:</td>
                <td>{{ $data['poliAsal'] ?: '-' }} / {{ $data['tglKunjungan'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="{{ $garis }}"></div>

        {{-- Fasyankes tujuan --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-bottom:14px;">
            <tr>
                <td style="width:170px; padding:1px 0;">Kepada Yth.</td>
                <td style="width:10px;">:</td>
                <td></td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Fasyankes Tujuan</td>
                <td>:</td>
                <td style="font-weight:bold;">{{ $data['tujuan']['nama'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Kode Register</td>
                <td>:</td>
                <td>{{ $data['tujuan']['kode'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Strata</td>
                <td>:</td>
                <td>{{ $data['tujuan']['strata'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; vertical-align:top;">Alamat</td>
                <td style="vertical-align:top;">:</td>
                <td>{{ $data['tujuan']['alamat'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Sub Spesialis</td>
                <td>:</td>
                <td>{{ $data['layanan']['subSpesialis'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Sarana</td>
                <td>:</td>
                <td>{{ $data['layanan']['sarana'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="font-size:11px; margin-bottom:2px;">Dengan hormat,</div>
        <div style="font-size:11px; margin-bottom:8px;">Mohon untuk dilakukan pemeriksaan / penanganan lebih lanjut
            pada pasien:</div>

        {{-- Identitas & pokok rujukan --}}
        <table cellpadding="0" cellspacing="0"
            style="width:100%; font-size:11px; border-collapse:collapse; margin-bottom:16px;">
            <tr>
                <td style="{{ $sel }} width:22%;">Nama</td>
                <td style="{{ $sel }} width:28%;">{{ $data['pasien']['nama'] ?: '-' }}</td>
                <td style="{{ $sel }} width:22%;">No. Rekam Medis</td>
                <td style="{{ $sel }} width:28%;">{{ $data['pasien']['rm'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">NIK</td>
                <td style="{{ $sel }}">{{ $data['pasien']['nik'] ?: '-' }}</td>
                <td style="{{ $sel }}">Jenis Kelamin</td>
                <td style="{{ $sel }}">{{ $data['pasien']['jenisKelamin'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Umur</td>
                <td style="{{ $sel }}">{{ $data['pasien']['umur'] ?: '-' }}</td>
                <td style="{{ $sel }}">Tgl. Lahir</td>
                <td style="{{ $sel }}">{{ $data['pasien']['tglLahir'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Alamat</td>
                <td style="{{ $sel }}" colspan="3">{{ $data['pasien']['alamat'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Jenis Jaminan</td>
                <td style="{{ $sel }}">{{ $data['pasien']['jenisJaminan'] ?: '-' }}</td>
                <td style="{{ $sel }}">Nomor Jaminan</td>
                <td style="{{ $sel }}">{{ $data['pasien']['nomorJaminan'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Diagnosa Sementara</td>
                <td style="{{ $sel }}" colspan="3">{{ $data['diagnosaSementara'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Kriteria Rujukan</td>
                <td style="{{ $sel }}" colspan="3">
                    @forelse ($data['resume']['kriteria'] as $baris)
                        {{ $baris }}
                    @empty
                        -
                    @endforelse
                </td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Terapi yang telah diberikan</td>
                <td style="{{ $sel }} white-space:pre-line;" colspan="3">{{ $data['terapiDiberikan'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="font-size:11px; margin-bottom:2px;">
            Rujukan telah mendapatkan persetujuan baik lisan maupun tertulis dari pasien dan/atau keluarga pasien.
        </div>
        <div style="font-size:11px; margin-bottom:18px;">
            Demikian kami sampaikan, atas perhatian Saudara kami ucapkan terima kasih.
        </div>

        {{-- Tanda tangan: tinggi tetap + &nbsp;, hindari flex/<br> (jebakan dompdf) --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px;">
            <tr>
                <td style="width:50%; text-align:center;">&nbsp;</td>
                <td style="width:50%; text-align:center;">
                    {{ $data['perujuk']['kota'] ? $data['perujuk']['kota'] . ', ' : '' }}{{ $data['tanggal'] ?: '' }}
                </td>
            </tr>
            <tr>
                <td style="text-align:center;">Tenaga Kesehatan Penerima Rujukan</td>
                <td style="text-align:center;">Dokter Penanggung Jawab Pasien</td>
            </tr>
            <tr>
                <td style="height:64px; text-align:center;">&nbsp;</td>
                <td style="height:64px; text-align:center;">
                    @if (!empty($data['ttdDokterPath']))
                        <img src="{{ $data['ttdDokterPath'] }}" style="height:60px;" alt="TTD Dokter">
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>
            <tr>
                <td style="text-align:center;">
                    <span style="border-top:1px solid #000; padding:0 40px;">&nbsp;</span>
                </td>
                <td style="text-align:center;">
                    <span style="border-top:1px solid #000; padding:0 40px;">{{ $data['dpjp'] ?: '&nbsp;' }}</span>
                </td>
            </tr>
        </table>

        <div style="font-size:10px; margin-top:20px;">
            Nb.: Rujukan tercatat pada faskes
            <span style="border-bottom:1px dotted #000;">&nbsp;{{ $data['tujuan']['nama'] ?: '' }}&nbsp;</span>
            dengan No. Rujukan SATUSEHAT
            <span style="border-bottom:1px dotted #000;">&nbsp;{{ $data['noRujukanSatuSehat'] ?: '' }}&nbsp;</span>
        </div>
    </div>

    {{-- ══════════════════ HALAMAN 2 — RESUME KLINIS ══════════════════ --}}
    <div style="page-break-before:always; padding:0 8px;">

        <div style="text-align:center; font-size:13px; font-weight:bold; text-decoration:underline; margin-bottom:12px;">
            RESUME KLINIS PASIEN RUJUKAN
        </div>

        @php
            $resume = $data['resume'];
            $selNo = $sel . ' width:24px; text-align:center;';
            $selJudul = $sel . ' width:205px;';
            $selIsi = $sel;
        @endphp

        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; border-collapse:collapse;">

            {{-- I. Identitas pasien --}}
            <tr>
                <td style="{{ $selNo }}">I</td>
                <td style="{{ $selJudul }}"><strong>IDENTITAS PASIEN</strong></td>
                <td style="{{ $selIsi }}">&nbsp;</td>
            </tr>
            @foreach ([
        'a. Nama Pasien' => $data['pasien']['nama'],
        'b. No. Rekam Medis' => $data['pasien']['rm'],
        'c. Umur' => $data['pasien']['umur'],
        'd. Jenis Kelamin' => $data['pasien']['jenisKelamin'],
        'e. Alamat' => $data['pasien']['alamat'],
        'f. No. Kartu JKN' => $data['pasien']['nomorJaminan'],
    ] as $label => $nilai)
                <tr>
                    <td style="{{ $selNo }}">&nbsp;</td>
                    <td style="{{ $selJudul }}">{{ $label }}</td>
                    <td style="{{ $selIsi }}">{{ $nilai ?: '-' }}</td>
                </tr>
            @endforeach

            {{-- II. Anamnesa --}}
            <tr>
                <td style="{{ $selNo }}">II</td>
                <td style="{{ $selJudul }}"><strong>Anamnesa</strong></td>
                <td style="{{ $selIsi }}">&nbsp;</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">a. Keluhan Utama</td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['keluhanUtama'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">b. Riwayat Penyakit Dahulu</td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['riwayatPenyakit'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">c. Alergi</td>
                <td style="{{ $selIsi }}">{{ $resume['alergi'] ?: '-' }}</td>
            </tr>

            {{-- III. Pemeriksaan fisik --}}
            <tr>
                <td style="{{ $selNo }}">III</td>
                <td style="{{ $selJudul }}"><strong>Pemeriksaan Fisik</strong></td>
                <td style="{{ $selIsi }}">&nbsp;</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">a. Keadaan Umum</td>
                <td style="{{ $selIsi }}">{{ $resume['keadaanUmum'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">b. Tingkat Kesadaran</td>
                <td style="{{ $selIsi }}">{{ $resume['kesadaran'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">c. Tanda-Tanda Vital</td>
                <td style="{{ $selIsi }}">
                    Tensi: {{ $resume['ttv']['tensi'] ?: '-' }} &nbsp;&nbsp;
                    Nadi: {{ $resume['ttv']['nadi'] ?: '-' }} &nbsp;&nbsp;
                    Suhu: {{ $resume['ttv']['suhu'] ?: '-' }} &nbsp;&nbsp;
                    Frek. Nafas: {{ $resume['ttv']['nafas'] ?: '-' }}
                    @if ($resume['ttv']['spo2'])
                        &nbsp;&nbsp; SpO2: {{ $resume['ttv']['spo2'] }}
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">d. Kelainan Yang Bermasalah</td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['kelainan'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">e. Pemeriksaan Penunjang</td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['penunjang'] ?: '-' }}</td>
            </tr>

            {{-- IV. Diagnosa --}}
            <tr>
                <td style="{{ $selNo }}">IV</td>
                <td style="{{ $selJudul }}"><strong>Diagnosa (ICD-10)</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['diagnosa'] as $baris)
                        {{ $loop->iteration }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- V. Kriteria rujukan --}}
            <tr>
                <td style="{{ $selNo }}">V</td>
                <td style="{{ $selJudul }}"><strong>Kriteria Rujukan</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['kriteria'] as $baris)
                        {{ $loop->iteration }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- VI. Tindakan --}}
            <tr>
                <td style="{{ $selNo }}">VI</td>
                <td style="{{ $selJudul }}"><strong>Tindakan Yang Telah Dilakukan</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['tindakan'] as $baris)
                        {{ chr(96 + $loop->iteration) }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- VII. Terapi --}}
            <tr>
                <td style="{{ $selNo }}">VII</td>
                <td style="{{ $selJudul }}"><strong>Terapi Yang Telah Diberikan</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['terapi'] as $baris)
                        {{ chr(96 + $loop->iteration) }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- VIII. Tujuan rujukan --}}
            <tr>
                <td style="{{ $selNo }}">VIII</td>
                <td style="{{ $selJudul }}"><strong>Tujuan Rujukan</strong></td>
                <td style="{{ $selIsi }}">
                    {{ $data['tujuan']['nama'] ?: '-' }}
                    @if ($data['tujuan']['kode'])
                        ({{ $data['tujuan']['kode'] }})
                    @endif
                    <br>
                    Sub Spesialis: {{ $data['layanan']['subSpesialis'] ?: '-' }} &nbsp;&nbsp;
                    Sarana: {{ $data['layanan']['sarana'] ?: '-' }}
                </td>
            </tr>

            {{-- IX. Alasan merujuk --}}
            <tr>
                <td style="{{ $selNo }}">IX</td>
                <td style="{{ $selJudul }}"><strong>Alasan Merujuk</strong></td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['alasan'] ?: '-' }}</td>
            </tr>
        </table>

        {{-- Tanda tangan resume --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-top:18px;">
            <tr>
                <td style="width:60%;">&nbsp;</td>
                <td style="width:40%; text-align:center;">
                    {{ $data['perujuk']['kota'] ? $data['perujuk']['kota'] . ', ' : '' }}{{ $data['tanggal'] ?: '' }}
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="height:64px; text-align:center;">
                    @if (!empty($data['ttdDokterPath']))
                        <img src="{{ $data['ttdDokterPath'] }}" style="height:60px;" alt="TTD Dokter">
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="text-align:center;">
                    <span style="border-top:1px solid #000; padding:0 40px;">{{ $data['dpjp'] ?: '&nbsp;' }}</span>
                </td>
            </tr>
        </table>

        <div style="font-size:9px; color:#555; text-align:center; margin-top:18px;">
            Dicetak: {{ $data['tglCetak'] ?? '-' }}
            &nbsp;&bull;&nbsp; No. RM: {{ $data['pasien']['rm'] ?: '-' }}
            &nbsp;&bull;&nbsp; {{ $data['perujuk']['nama'] }}
            @if ($data['noKunjunganPcare'])
                &nbsp;&bull;&nbsp; No. Kunjungan PCare: {{ $data['noKunjunganPcare'] }}
            @endif
        </div>
    </div>

</x-pdf.layout-a4>
