<?php

namespace App\Http\Traits\SATUSEHAT;

trait ServiceRequestTrait
{
    use SatuSehatTrait;

    /**
     * Build payload for a SatuSehat ServiceRequest
     *
     * @param array $data  Required keys:
     *- identifier: [ 'system' => string, 'value' => string ]
     *- status: string (e.g. 'active')
     *- intent: string (e.g. 'original-order')
     *- priority: string (e.g. 'routine')
     *- category: [ 'system' => string, 'code' => string, 'display' => string ]
     *- code: [ 'system' => string, 'code' => string, 'display' => string ]
     *- subject: string (reference, e.g. 'Patient/{id}')
     *- encounter: string (reference, e.g. 'Encounter/{id}')
     *- occurrenceDateTime: string (ISO8601)
     *- authoredOn: string (ISO8601)
     *- requester: string (reference, e.g. 'Practitioner/{id}')
     *- performer (opsional): string reference praktisi yang MENGERJAKAN pemeriksaan.
     *  Bila tidak diisi, requester dipakai sebagai pengganti — lihat catatan di
     *  buildServiceRequest(). Field ini wajib ada di payload (RuleNumber 10377).
     *- reasonCode (optional): array of either text or coding arrays
     *
     * @return array
     */
    protected function buildServiceRequest(array $data)
    {
        $payload = [
            'resourceType' => 'ServiceRequest',
            'identifier' => [[
                'system' => $data['identifier']['system'],
                'value'  => $data['identifier']['value'],
            ]],
            'status' => $data['status'] ?? 'active',
            'intent' => $data['intent'] ?? 'original-order',
            'priority'  => $data['priority'] ?? 'routine',
            'category'  => [[
                'coding' => [$data['category']],
            ]],
            'code' => [
                'coding' => [$data['code']],
                'text' => $data['code']['display'] ?? null,
            ],
            'subject' => ['reference' => $data['subject']],
            'encounter' => ['reference' => $data['encounter']],
            'occurrenceDateTime' => $data['occurrenceDateTime'] ?? now()->toIso8601String(),
            'authoredOn' => $data['authoredOn'] ?? now()->toIso8601String(),
            'requester' => ['reference' => $data['requester'], 'display' => $data['requesterDisplay']],
        ];

        // performer WAJIB — SATUSEHAT menolak tanpanya:
        //   "Reference is mandatory : ServiceRequest.performer (RuleNumber: 10377)"
        //
        // Menurut koleksi Postman resmi, isinya praktisi yang MENGERJAKAN pemeriksaan
        // (Practitioner_Id_Lab / Practitioner_Id_Rad), bukan dokter pengirim. Masalahnya
        // kita belum punya IHS untuk petugas penunjang: satu-satunya pemetaan ke
        // Practitioner di basis data adalah skmst_doctors.dr_uuid, sementara petugas
        // penunjang tersimpan tanpa uuid.
        //
        // Jadi bila pemanggil tidak menyebut performer, dokter pengirim dipakai sebagai
        // penggantinya — kiriman jalan, tapi nilainya BELUM AKURAT. Begitu ada master
        // dokter penanggung jawab penunjang (atau pemetaan petugas → IHS), pemanggil
        // tinggal mengirim performer sendiri dan baris ini tidak perlu diubah.
        $performer = $data['performer'] ?? $data['requester'];
        $performerDisplay = $data['performerDisplay'] ?? ($data['requesterDisplay'] ?? null);

        $payload['performer'] = [
            ['reference' => $performer, 'display' => $performerDisplay],
        ];

        if (!empty($data['reasonCode'])) {
            $payload['reasonCode'] = $data['reasonCode'];
        }

        return $payload;
    }

    /**
     * Create a ServiceRequest in SatuSehat
     *
     * @param array $data  Build parameters for the payload
     * @return array
     * @throws \Exception on API error
     */
    public function postServiceRequest(array $data)
    {
        $payload = $this->buildServiceRequest($data);
        return $this->makeRequest('post', '/ServiceRequest', $payload);
    }

    /**
     * Retrieve a ServiceRequest by its UUID
     *
     * @param string $id
     * @return array
     */
    public function getServiceRequest(string $id)
    {
        return $this->makeRequest('get', "/ServiceRequest/{$id}");
    }

    /**
     * Search for ServiceRequests with query parameters
     *
     * @param array $params  e.g. ['patient' => '{id}', 'date' => '2025-05-07']
     * @return array
     */
    public function searchServiceRequest(array $params = []): array
    {
        // makeRequest() hanya menerima 3 argumen; $params yang dulu dikirim sebagai
        // argumen KEEMPAT dibuang diam-diam oleh PHP, sehingga fungsi ini sebenarnya
        // mengambil SELURUH ServiceRequest tanpa filter apa pun. Query string kini
        // dirangkai ke endpoint, sebagaimana pencarian lain di trait-trait SATUSEHAT.
        $endpoint = '/ServiceRequest';
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        return $this->makeRequest('get', $endpoint);
    }
}
