<?php

namespace App\Http\Traits\SATUSEHAT;

trait MedicationRequestTrait
{
    use SatuSehatTrait;

    /**
     * Kirim MedicationRequest resource (resep/permintaan obat)
     *
     * @param array $data
     *  - identifier         (array) list of ['system'=>string, 'value'=>string]  **required**
     *  - patientId          (string) UUID pasien
     *  - encounterId        (string) UUID encounter (optional)
     *  - medicationCodeableConcept (array) ['system'=>..., 'code'=>..., 'display'=>...] (optional)
     *  - medicationReference (string) 'Medication/{id}' (optional)
     *  - status             (string) draft|active|… default 'active'
     *  - intent             (string) proposal|order|… default 'order'
     *  - priority           (string) routine|urgent|… default 'routine'
     *  - authoredOn         (string) ISO8601 datetime
     *  - requesterId        (string) Practitioner/{id} (optional)
     *  - dosageInstruction  (array) list of FHIR dosageInstruction objects (optional)
     *  - dispenseRequest    (array) ['quantityValue'=>…, 'quantityUnit'=>…, 'quantityUnitCode'=>UCUM, 'validityStart'=>…, 'validityEnd'=>…, 'supplyDurationValue'=>…, 'supplyDurationUnit'=>UCUM] (optional)
     *  - note               (string) catatan klinis (optional)
     *
     * @return array
     */
    // public function createMedicationRequest(array $data): array
    // {
    //     $payload = [
    //         'resourceType' => 'MedicationRequest',
    //         'status'       => $data['status']   ?? 'active',
    //         'intent'       => $data['intent']   ?? 'order',
    //         'priority'     => $data['priority'] ?? 'routine',
    //     ];

    //     // —––––––– IDENTIFIER (mandatory) ––––––––
    //     if (!empty($data['identifier'])) {
    //         $payload['identifier'] = $data['identifier'];
    //     }

    //     // —––––––– Subject & authoredOn ––––––––
    //     $payload['subject']    = ['reference' => 'Patient/' . $data['patientId']];
    //     $payload['authoredOn'] = $data['authoredOn'];

    //     if (!empty($data['encounterId'])) {
    //         $payload['encounter'] = ['reference' => 'Encounter/' . $data['encounterId']];
    //     }

    //     if (!empty($data['medicationCodeableConcept'])) {
    //         $payload['medicationCodeableConcept'] = [
    //             'coding' => [[
    //                 'system'  => $data['medicationCodeableConcept']['system'],
    //                 'code'    => $data['medicationCodeableConcept']['code'],
    //                 'display' => $data['medicationCodeableConcept']['display'],
    //             ]],
    //             'text' => $data['medicationCodeableConcept']['display'],
    //         ];
    //     }

    //     if (!empty($data['medicationReference'])) {
    //         $payload['medicationReference'] = ['reference' => $data['medicationReference']];
    //     }

    //     if (! empty($data['category'])) {
    //         $categoryCC = [
    //             'coding' => [[
    //                 'system'  => 'http://terminology.hl7.org/CodeSystem/medicationrequest-category',
    //                 'code'    => $data['category'],
    //                 'display' => ucfirst($data['category']),
    //             ]],
    //             'text' => ucfirst($data['category']),
    //         ];
    //         // wrap in array
    //         $payload['category'] = [$categoryCC];
    //     }

    //     if (!empty($data['requesterId'])) {

    //         $payload['requester'] = [
    //             'reference' => 'Practitioner/' . $data['requesterId'],
    //             'display'   => $data['requesterName'] ?? null,
    //         ];
    //     } else {
    //         throw new \InvalidArgumentException('requesterId is required for MedicationRequest');
    //     }


    //     if (!empty($data['dosageInstruction'])) {
    //         $payload['dosageInstruction'] = $data['dosageInstruction'];
    //     }

    //     if (!empty($data['dispenseRequest'])) {
    //         $payload['dispenseRequest'] = $data['dispenseRequest'];
    //     }

    //     if (!empty($data['note'])) {
    //         $payload['note'] = [['text' => $data['note']]];
    //     }

    //     // dd(json_encode($payload));
    //     return $this->makeRequest('post', '/MedicationRequest', $payload);
    // }



    /* @param array $data  Expected keys:
     *   - registration_id
     *   - org_id
     *   - patientId, patientName
     *   - encounterId
     *   - requesterId, requesterName
     *   - identifier (array of identifiers)
     *   - containedMedication (array with medication details)
     *   - category (string)
     *   - reasonReference (array of refs)
     *   - dosageInstruction (FHIR array)
     *   - dispenseRequest (FHIR array)
     *   - note (string)
     *   - authoredOn (ISO8601 string)
     *
     * @return array API response
     */
    public function createMedicationRequest(array $data): array
    {
        // prepare timestamp
        $authoredOn = $data['authoredOn'];

        // ---- BUILD contained Medication ----
        // you'll need these in $data: registrationId, orgId, medicationCode, medicationDisplay,
        // medicationFormCode, medicationFormDisplay, strength, unit, isCompoundFlag, etc.
        $registrationId = $data['registrationId'];      // e.g. "A00000111222"
        $orgId          = $data['orgId'];               // your organization ID

        $containedMedication = [
            'resourceType' => 'Medication',
            'id'           => $data['medContainedId'],
            'meta'         => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication']
            ],
            'identifier' => [
                [
                    'system' => "http://sys-ids.kemkes.go.id/medication/{$orgId}",
                    'use'    => 'official',
                    'value'  => $registrationId
                ]
            ],
            // Racikan (compound) tidak punya kode KFA untuk campurannya — yang ber-KFA
            // adalah bahan-bahannya (lihat 'ingredient'). Untuk kasus itu pemanggil
            // mengirim medicationCode kosong, dan code cukup berisi teks.
            'code'   => trim((string) ($data['medicationCode'] ?? '')) !== ''
                ? [
                    'coding' => [[
                        'system'  => 'http://sys-ids.kemkes.go.id/kfa',
                        'code'    => $data['medicationCode'],      // e.g. "93001350"
                        'display' => $data['medicationDisplay']    // e.g. "Captopril 12,5 mg Tablet (PHAPROS)"
                    ]],
                    'text' => $data['medicationDisplay'],
                ]
                : ['text' => $data['medicationDisplay']],
            'status' => 'active',
            'form'   => [
                'coding' => [[
                    'system'  => 'http://terminology.kemkes.go.id/CodeSystem/medication-form',
                    'code'    => $data['medicationFormCode'],   // e.g. "BS066"
                    'display' => $data['medicationFormDisplay'] // e.g. "Tablet"
                ]]
            ],
            // 'ingredient' => [[
            //     'itemCodeableConcept' => [
            //         'coding' => [[
            //             'system'  => 'http://sys-ids.kemkes.go.id/kfa',
            //             'code'    => $data['ingredientCode'],      // e.g. "91000340"
            //             'display' => $data['ingredientDisplay']    // e.g. "Captopril"
            //         ]]
            //     ],
            //     'isActive' => true,
            //     'strength' => [
            //         'numerator' => [
            //             'system' => 'http://unitsofmeasure.org',
            //             'value'  => $data['strengthValue'],         // e.g. 12.5
            //             'code'   => $data['strengthUnit']           // e.g. "mg"
            //         ],
            //         'denominator' => [
            //             'value'  => 1,
            //             'unit'   => $data['formUnit'],               // e.g. "Tablet"
            //             'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
            //             'code'   => $data['formUnitCode']           // e.g. "TAB"
            //         ]
            //     ]
            // ]],
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                'valueCodeableConcept' => [
                    'coding' => [[
                        'system'  => 'http://terminology.kemkes.go.id/CodeSystem/medication-type',
                        'code'    => $data['medicationTypeCode'],   // e.g. "NC"
                        'display' => $data['medicationTypeDisplay'] // e.g. "Non-compound"
                    ]]
                ]
            ]]
        ];

        // Racikan (compound): kode KFA-nya ada di tiap bahan, bukan di campurannya.
        // Pemanggil yang menyusun daftar ingredient bergaya FHIR.
        if (!empty($data['ingredient'])) {
            $containedMedication['ingredient'] = $data['ingredient'];
        }

        // ---- BUILD MedicationRequest payload ----
        $payload = [
            'resourceType'        => 'MedicationRequest',
            'contained'           => [$containedMedication],
            'identifier'          => [
                [
                    'system' => "http://sys-ids.kemkes.go.id/prescription/{$orgId}",
                    'use'    => 'official',
                    'value'  => $data['prescriptionId']           // e.g. "A00000111222"
                ],
                [
                    'system' => "http://sys-ids.kemkes.go.id/prescription-item/{$orgId}",
                    'use'    => 'official',
                    // Satu resep bisa berisi banyak item; tanpa nomor item sendiri semua
                    // MedicationRequest-nya memakai identifier yang sama persis — dan
                    // SATUSEHAT tak bisa membedakan obat kedua dari obat pertama.
                    'value'  => $data['prescriptionItemId'] ?? "{$data['prescriptionId']}-1"
                ]
            ],
            'status'              => $data['status']   ?? 'completed',
            'intent'              => $data['intent']   ?? 'order',
            'priority'            => $data['priority'] ?? 'routine',
            'category'            => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/medicationrequest-category',
                    'code'   => $data['category'],                // e.g. 'community'
                    'display' => ucfirst($data['category'])
                ]]
            ]],
            'medicationReference' => [
                'reference' => "#{$data['medContainedId']}",
                'display'   => $data['medicationDisplay']
            ],
            'subject'             => [
                'reference' => "Patient/{$data['patientId']}",
                'display'   => $data['patientName']
            ],
            'encounter'           => [
                'reference' => "Encounter/{$data['encounterId']}"
            ],
            'authoredOn'          => $authoredOn,
            'requester'           => [
                'reference' => "Practitioner/{$data['requesterId']}",
                'display'   => $data['requesterName']
            ],
        ];

        // Field opsional HANYA dikirim bila ada isinya. dispenseRequest itu OBJEK
        // (kardinalitas 0..1) — mengirimnya sebagai array kosong ditolak SATUSEHAT
        // dengan "invalid value (expected a DispenseRequest object): []".
        foreach (['reasonReference', 'dosageInstruction', 'dispenseRequest'] as $kunciOpsional) {
            if (!empty($data[$kunciOpsional])) {
                $payload[$kunciOpsional] = $data[$kunciOpsional];
            }
        }

        // Optional: add note
        if (!empty($data['note'])) {
            $payload['note'] = [['text' => $data['note']]];
        }

        // debug
        // dd(json_encode($payload, JSON_PRETTY_PRINT), $payload);

        // send
        return $this->makeRequest('post', '/MedicationRequest', $payload);
    }
}
