<?php

namespace App\Http\Traits\SATUSEHAT;


trait AllergyIntoleranceTrait
{
    use SatuSehatTrait;

    /**
     * Mengirim data riwayat alergi pasien ke SATUSEHAT
     *
     * @param array $data
     * @return array
     */
    public function createAllergyIntolerance(array $data): array
    {
        // validasi wajib
        if (empty($data['patientId'])) {
            throw new \InvalidArgumentException('Patient ID wajib diset.');
        }
        if (empty($data['encounterId'])) {
            throw new \InvalidArgumentException('Encounter ID wajib diset.');
        }
        if (empty($data['code'])) {
            throw new \InvalidArgumentException('SNOMED code alergi wajib diset.');
        }
        if (empty($data['recorderId'])) {
            throw new \InvalidArgumentException('Recorder (Practitioner ID) wajib diset.');
        }

        $payload = [
            "resourceType"       => "AllergyIntolerance",
            "clinicalStatus"     => [
                "coding" => [[
                    "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                    "code"   => "active"
                ]]
            ],
            "verificationStatus" => [
                "coding" => [[
                    "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                    "code"   => "confirmed"
                ]]
            ],
            "code"               => [
                "coding" => [[
                    "system"  => "http://snomed.info/sct",
                    "code"    => $data['code'],
                    "display" => $data['display']
                ]],
                "text"   => $data['display']
            ],
            "patient"            => [
                "reference" => "Patient/{$data['patientId']}"
            ],
            // **wajib**: encounter reference
            "encounter"          => [
                "reference" => "Encounter/{$data['encounterId']}"
            ],
            // **wajib**: who recorded
            "recorder"           => [
                "reference" => "Practitioner/{$data['recorderId']}"
            ],
            "onsetDateTime"      => $data['onset']   ?? now()->toIso8601String(),
            "note"               => [["text" => $data['note']  ?? '']],
        ];

        // category WAJIB tanpa perkecualian — SATUSEHAT menolak tanpanya:
        //   "Element not found: AllergyIntolerance.category (RuleNumber: 10075)".
        // Pemanggil yang menentukan kategorinya (food / environment / medication);
        // 'medication' hanya nilai jatuhan supaya payload tetap sah.
        $payload['category'] = [$data['category'] ?? 'medication'];

        // type & criticality SENGAJA opsional — kirim null untuk MENGHILANGKANNYA.
        // Wajib dihilangkan untuk pernyataan "tidak ada alergi" (mis. SNOMED 716186003):
        // keduanya atribut alergi yang ADA. Dulu ketiganya di-HARDCODE
        // ('allergy'/'medication'/'low') sehingga setiap kiriman "tidak ada alergi"
        // membawa klaim yang tak pernah dibuat siapa pun — dan criticality='low'
        // untuk pasien yang justru TIDAK punya alergi sama sekali.
        if (!array_key_exists('type', $data) || $data['type'] !== null) {
            $payload['type'] = $data['type'] ?? 'allergy';
        }
        if (!array_key_exists('criticality', $data) || $data['criticality'] !== null) {
            $payload['criticality'] = $data['criticality'] ?? 'low';
        }

        return $this->makeRequest('post', '/AllergyIntolerance', $payload);
    }

    public function fetchAllergyIntoleranceByPatient(string $patientId): array
    {
        $this->initializeSatuSehat();

        // Gunakan makeRequest untuk GET dengan query patient
        $endpoint = "AllergyIntolerance?patient=Patient/{$patientId}";
        return $this->makeRequest('get', $endpoint);
    }
}
