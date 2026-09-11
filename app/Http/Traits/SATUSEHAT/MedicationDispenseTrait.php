<?php

namespace App\Http\Traits\SATUSEHAT;

trait MedicationDispenseTrait
{
    use SatuSehatTrait;

    /**
     * Kirim MedicationDispense resource (pengeluaran/dispense obat) disesuaikan dengan contoh SatuSehat
     *
     * @param array $data  Expected keys:
     *  - registrationId            (string)
     *  - prescriptionItemId        (string)
     *  - orgId                     (string)
     *  - medContainedId            (string)
     *  - medicationCode            (string)
     *  - medicationDisplay         (string)
     *  - medicationFormCode        (string)
     *  - medicationFormDisplay     (string)
     *  - medicationTypeCode        (string)
     *  - medicationTypeDisplay     (string)
     *  - manufacturerId            (string)
     *  - ingredient               (array)   // array of ingredient definitions
     *  - patientId, patientName   (string)
     *  - encounterId              (string)
     *  - whenPrepared             (string) ISO8601
     *  - whenHandedOver           (string) ISO8601
     *  - performers               (array)   // array of actor references
     *  - dosageInstruction        (array)
     *  - authorizingPrescription  (array)
     *  - quantity                 (array)   // ['value'=>, 'unit'=>, 'system'=>, 'code'=>]
     *  - daysSupply               (array)   // ['value'=>, 'unit'=>, 'system'=>, 'code'=>]
     *  - receiver                 (array)
     *  - substitution             (array)
     *  - destinationId, destinationDisplay (optional)
     *  - note                     (string) optional
     *
     * @return array API response
     */
    public function createMedicationDispense(array $data): array
    {
        // —––– build contained Medication –––—
        $med = [
            'resourceType' => 'Medication',
            'id'           => $data['medContainedId'],
            'meta'         => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication']
            ],
            'identifier' => [[
                'system' => "http://sys-ids.kemkes.go.id/medication/{$data['orgId']}",
                'use'    => 'official',
                'value'  => $data['registrationId']
            ]],
            'code'   => [
                'coding' => [[
                    'system'  => 'http://sys-ids.kemkes.go.id/kfa',
                    'code'    => $data['medicationCode'],
                    'display' => $data['medicationDisplay']
                ]]
            ],
            'status' => 'active',
            'manufacturer' => [
                'reference' => "Organization/{$data['orgId']}"
            ],
            'form'   => [
                'coding' => [[
                    'system'  => 'http://terminology.kemkes.go.id/CodeSystem/medication-form',
                    'code'    => $data['medicationFormCode'],
                    'display' => $data['medicationFormDisplay']
                ]]
            ],
            // 'ingredient' => $data['ingredient'],
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                'valueCodeableConcept' => [
                    'coding' => [[
                        'system'  => 'http://terminology.kemkes.go.id/CodeSystem/medication-type',
                        'code'    => $data['medicationTypeCode'],
                        'display' => $data['medicationTypeDisplay']
                    ]]
                ]
            ]]
        ];

        // —––– build MedicationDispense –––—
        $payload = [
            'resourceType' => 'MedicationDispense',
            'contained'    => [$med],
            'identifier' => [
                [
                    'system' => "http://sys-ids.kemkes.go.id/prescription/{$data['orgId']}",
                    'use'    => 'official',
                    'value'  => $data['registrationId']
                ],
                [
                    'system' => "http://sys-ids.kemkes.go.id/prescription-item/{$data['orgId']}",
                    'use'    => 'official',
                    'value'  => $data['prescriptionItemId']
                ],
            ],
            'status'   => $data['status'] ?? 'completed',
            'category' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category',
                    'code'    => $data['category'],
                    'display' => ucfirst($data['category'])
                ]]
            ],
            'medicationReference' => [
                'reference' => "#{$data['medContainedId']}"
            ],
            'subject'   => [
                'reference' => "Patient/{$data['patientId']}",
                'display'   => $data['patientName']
            ],
            'context' => [
                'reference' => "Encounter/{$data['encounterId']}"
            ],
            'whenPrepared'   => $data['whenPrepared'],
            'whenHandedOver' => $data['whenHandedOver'],
            'performer'      => $data['performer'],
            'authorizingPrescription' => [$data['authorizingPrescription']],
            'receiver'       => [$data['receiver']],
            // 'substitution'   => $data['substitution'],
        ];

        // Racikan: bahan-bahannya yang membawa kode KFA — disusun pemanggil.
        if (!empty($data['ingredient'])) {
            $payload['contained'][0]['ingredient'] = $data['ingredient'];
        }

        // Field opsional hanya dikirim bila ada isinya — SATUSEHAT menolak elemen
        // bertipe objek yang dikirim sebagai array kosong (lihat MedicationRequestTrait).
        foreach (['dosageInstruction', 'quantity', 'daysSupply'] as $kunciOpsional) {
            if (!empty($data[$kunciOpsional])) {
                $payload[$kunciOpsional] = $data[$kunciOpsional];
            }
        }

        // optional destination (e.g. ward, pharmacy)
        if (!empty($data['destinationId'])) {
            $payload['destination'] = [
                'reference' => "Location/{$data['destinationId']}",
                'display'   => $data['destinationDisplay'] ?? null
            ];
        }

        // optional note
        if (!empty($data['note'])) {
            $payload['note'] = [['text' => $data['note']]];
        }

        // send the request
        return $this->makeRequest('post', '/MedicationDispense', $payload);
    }
}
