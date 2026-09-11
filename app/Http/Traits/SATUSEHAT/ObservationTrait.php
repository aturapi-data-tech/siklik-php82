<?php

namespace App\Http\Traits\SATUSEHAT;


trait ObservationTrait
{
    use SatuSehatTrait;

    /**
     * Generic FHIR Observation creator
     *
     * @param array $data
     *   - patientId       (string)  required
     *   - encounterId     (string)  required
     *   - performerId     (string)  optional
     *   - effectiveDate   (string)  ISO8601 optional (default: now)
     *   - category        (array)   FHIR category coding array (default: vital-signs)
     *   - code            (array)   ['system'=>..., 'code'=>..., 'display'=>...] required
     *   - valueQuantity   (array)   ['value'=>float|int,'unit'=>string,'system'=>string,'code'=>string]
     *   - valueString     (string)  optional
     *   - valueInteger    (int)     skor polos (mis. NRS — contoh resmi memakai ini)
     *   - valueCodeableConcept (array) ['text'=>..., 'system'?, 'code'?, 'display'?];
     *                               tanpa 'code' dikirim sebagai `text` saja (sah di FHIR)
     *   - valueRange      (array)   ['low'=>['value','unit','code'], 'high'=>[...]]
     *   - components      (array)   list of buildVitalSignComponent(...) items
     *
     * Hanya SATU bentuk nilai yang dipakai, sesuai urutan cabang di bawah.
     *
     * @return array  decoded JSON response
     * @throws \Exception on HTTP error
     */
    public function createObservation(array $data): array
    {
        // Basic payload
        $payload = [
            'resourceType'      => 'Observation',
            'status'            => $data['status']       ?? 'final',
            'category'          => $data['category']     ?? [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                    'code'    => 'vital-signs',
                    'display' => 'Vital Signs',
                ]],
            ]],
            'code'              => [
                'coding' => [[
                    'system'  => $data['code']['system'],
                    'code'    => $data['code']['code'],
                    'display' => $data['code']['display'],
                ]],
                'text' => $data['code']['display'],
            ],
            'subject'           => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'effectiveDateTime' => $data['effectiveDate'] ?? now()->toIso8601String(),
        ];

        // Optional performer
        if (!empty($data['performerId'])) {
            $payload['performer'] = [[
                'reference' => 'Practitioner/' . $data['performerId']
            ]];
        }

        // Either a single valueQuantity...
        if (!empty($data['valueQuantity']) && is_array($data['valueQuantity'])) {
            $payload['valueQuantity'] = [
                'value'  => $data['valueQuantity']['value'],
                'unit'   => $data['valueQuantity']['unit'],
                'system' => $data['valueQuantity']['system'],
                'code'   => $data['valueQuantity']['code'],
            ];
        }
        // ...or a simple string
        elseif (isset($data['valueString'])) {
            $payload['valueString'] = $data['valueString'];
        }
        // ...or a plain integer score (mis. NRS — contoh resmi Postman memakai
        // valueInteger, bukan valueQuantity; jangan "dirapikan" jadi seragam)
        elseif (isset($data['valueInteger'])) {
            $payload['valueInteger'] = (int) $data['valueInteger'];
        }
        // ...or a coded answer (mis. tingkat kesadaran)
        elseif (!empty($data['valueCodeableConcept']) && is_array($data['valueCodeableConcept'])) {
            $konsep = $data['valueCodeableConcept'];

            // Kode boleh TIDAK ADA. CodeableConcept dengan `text` saja itu sah di FHIR,
            // dan jauh lebih jujur daripada mengarang kode: nilai yang belum punya
            // padanan terminologi resmi tetap terkirim sebagai teks, bukan sebagai
            // konsep yang keliru. Tanpa cabang ini, coding terisi null bertiga.
            if (empty($konsep['code'])) {
                $payload['valueCodeableConcept'] = [
                    'text' => $konsep['text'] ?? ($konsep['display'] ?? ''),
                ];
            } else {
                $payload['valueCodeableConcept'] = [
                    'coding' => [[
                        'system'  => $konsep['system'],
                        'code'    => $konsep['code'],
                        'display' => $konsep['display'],
                    ]],
                    // `text` eksplisit dipakai bila ada — label yang dibaca petugas
                    // sering lebih berarti daripada display terminologi.
                    'text' => $konsep['text'] ?? $konsep['display'],
                ];
            }
        }
        // ...or a range (mis. dosis oksigen "3-4 L/menit" — rentang pilihan, BUKAN hasil
        // ukur; jangan dipaksa jadi angka tunggal karena itu mengarang presisi yang tak
        // pernah diukur)
        elseif (!empty($data['valueRange']) && is_array($data['valueRange'])) {
            $range = [];
            foreach (['low', 'high'] as $sisi) {
                if (isset($data['valueRange'][$sisi])) {
                    $range[$sisi] = [
                        'value'  => $data['valueRange'][$sisi]['value'],
                        'unit'   => $data['valueRange'][$sisi]['unit'],
                        'system' => 'http://unitsofmeasure.org',
                        'code'   => $data['valueRange'][$sisi]['code'],
                    ];
                }
            }
            if ($range !== []) {
                $payload['valueRange'] = $range;
            }
        }
        // ...or multiple components
        elseif (!empty($data['components']) && is_array($data['components'])) {
            $payload['component'] = $data['components'];
        }
        // dd($payload);
        return $this->makeRequest('post', '/Observation', $payload);
    }


    /**
     * Cari semua Observation untuk sebuah encounter.
     *
     * @param string $encounterId
     * @return array
     * @throws \Exception on HTTP error
     */
    public function searchObservationsByEncounter(string $encounterId): array
    {
        // Inisialisasi request ke endpoint Observation dengan filter encounter
        return $this->makeRequest('get', "/Observation?encounter={$encounterId}");
    }
}
