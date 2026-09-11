{{-- Partial: Arsitektur FKTP — siapa mengirim apa ke mana, dan bedanya dengan FKRTL. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">02 — Mulai</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Arsitektur FKTP</h1>

<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    Di FKTP hanya ada <b>satu jalur</b>: SIM klinik berbicara ke layanan BPJS <code>pcare-sisrute-rest</code>,
    dan <b>BPJS</b> yang membentuk resource SATUSEHAT (CarePlan + ServiceRequest) atas nama klinik. Klinik tidak
    pernah memanggil FHIR SATUSEHAT untuk urusan rujukan — kredensial SATUSEHAT klinik tetap dipakai untuk
    Encounter/Patient/Condition seperti biasa, bukan untuk mengirim rujukan.
</p>

<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900 dark:text-gray-400">
        Diagram alir
    </div>
    <div class="p-4 overflow-x-auto">
        <svg viewBox="0 0 860 210" class="w-full min-w-[680px]" style="font-family:inherit">
            <defs>
                <marker id="panah-srbk-fktp" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7"
                    orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#6b7280" />
                </marker>
            </defs>

            <rect x="20" y="70" width="180" height="70" rx="12" fill="#f3f4f6" />
            <text x="110" y="100" text-anchor="middle" font-size="14" font-weight="600" fill="#111827">siklik (FKTP)</text>
            <text x="110" y="120" text-anchor="middle" font-size="11" fill="#6b7280">EMR Rawat Jalan</text>

            <rect x="330" y="70" width="200" height="70" rx="12" fill="#f3f4f6" />
            <text x="430" y="98" text-anchor="middle" font-size="13" font-weight="600" fill="#111827">BPJS pcare-sisrute-rest</text>
            <text x="430" y="118" text-anchor="middle" font-size="11" fill="#6b7280">GetKriteria / GetFaskes / postKunjungan</text>

            <rect x="660" y="70" width="180" height="70" rx="12" fill="#f3f4f6" />
            <text x="750" y="98" text-anchor="middle" font-size="14" font-weight="600" fill="#111827">SATUSEHAT</text>
            <text x="750" y="118" text-anchor="middle" font-size="11" fill="#6b7280">CarePlan + ServiceRequest</text>

            <line x1="200" y1="95" x2="324" y2="95" stroke="#6b7280" stroke-width="2" marker-end="url(#panah-srbk-fktp)" />
            <text x="262" y="86" text-anchor="middle" font-size="11" fill="#6b7280">HMAC PCare</text>

            <line x1="324" y1="120" x2="204" y2="120" stroke="#6b7280" stroke-width="2" marker-end="url(#panah-srbk-fktp)" />
            <text x="264" y="136" text-anchor="middle" font-size="11" fill="#6b7280">kandidat + nomor rujukan</text>

            <line x1="530" y1="95" x2="654" y2="95" stroke="#6b7280" stroke-width="2" marker-end="url(#panah-srbk-fktp)" />
            <text x="592" y="86" text-anchor="middle" font-size="11" fill="#6b7280">BPJS yang mengirim</text>

            <line x1="654" y1="120" x2="534" y2="120" stroke="#6b7280" stroke-width="2" stroke-dasharray="6 4"
                marker-end="url(#panah-srbk-fktp)" />
            <text x="594" y="136" text-anchor="middle" font-size="11" fill="#6b7280">no. rujukan satusehat</text>

            <text x="110" y="170" text-anchor="middle" font-size="11" fill="#6b7280">Encounter sudah terkirim lebih dulu</text>
        </svg>
    </div>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Beda dengan FKRTL (rumah sakit)</h2>
<p class="max-w-3xl mb-3 text-sm">
    Kalau membaca kode atau dokumen dari SIM rumah sakit (mis. sirus), jangan disalin mentah — arsitekturnya berbeda.
</p>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">Hal</th>
                <th class="px-4 py-2">FKTP (siklik)</th>
                <th class="px-4 py-2">FKRTL (rumah sakit)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-semibold">Layanan BPJS</td>
                <td class="px-4 py-2"><code>pcare-sisrute-rest</code></td>
                <td class="px-4 py-2"><code>vclaim-sisrute-rest</code></td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Endpoint kirim</td>
                <td class="px-4 py-2"><code>Sisrute/postKunjungan</code> — payload kunjungan PCare biasa + blok rujukan</td>
                <td class="px-4 py-2"><code>Rujukan/Insert</code> — wrapper <code>request.t_rujukan</code> berbasis SEP</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Dasar transaksi</td>
                <td class="px-4 py-2">Kunjungan PCare (<code>noKunjungan</code>)</td>
                <td class="px-4 py-2">SEP (<code>noSep</code>)</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Jalur FHIR sendiri</td>
                <td class="px-4 py-2">Tidak ada</td>
                <td class="px-4 py-2">Ada — IGD &amp; ranap dikirim RS sendiri sebagai Task/CarePlan FHIR</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Persetujuan tujuan</td>
                <td class="px-4 py-2">Tidak ada (rawat jalan)</td>
                <td class="px-4 py-2">Ada untuk IGD/ranap (accepted/rejected lewat Task)</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Pembatalan</td>
                <td class="px-4 py-2"><code>Sisrute/deleteKunjungan</code> — <b>destruktif</b>, ikut menghapus pendaftaran PCare</td>
                <td class="px-4 py-2"><code>Rujukan/Delete</code> — hanya rujukannya</td>
            </tr>
        </tbody>
    </table>
</div>

<div
    class="px-4 py-3 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-900/50 dark:text-amber-200">
    <b>Satu kunjungan, satu endpoint.</b> Kunjungan yang berakhir dirujuk dikirim lewat
    <code>Sisrute/postKunjungan</code>; kunjungan biasa tetap lewat endpoint <code>kunjungan</code> PCare lama
    (<code>PcareTrait::addKunjungan</code>). <b>Jangan mengirim keduanya</b> untuk satu kunjungan yang sama —
    PCare akan melihatnya sebagai kunjungan ganda.
</div>
