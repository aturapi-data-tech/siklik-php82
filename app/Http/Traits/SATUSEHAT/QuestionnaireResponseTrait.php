<?php

namespace App\Http\Traits\SATUSEHAT;

/**
 * Pengirim QuestionnaireResponse ke SATUSEHAT.
 *
 * Sengaja tipis: pohon `item` dibangun kelas pemetaan per kuesioner (mis.
 * [[\App\Support\Terminologi\TelaahResepQ0007]]), bukan di sini — tiap kuesioner
 * Kemkes punya linkId & bentuk bersarang sendiri, dan menaruhnya di trait berarti
 * satu berkas membengkak tiap ada kuesioner baru.
 */
trait QuestionnaireResponseTrait
{
    use SatuSehatTrait;

    /**
     * @param array $data
     *   - questionnaire  (string)  canonical URL kuesioner
     *   - patientId      (string)  IHS pasien
     *   - patientName    (string)  optional, jadi subject.display
     *   - encounterId    (string)
     *   - authorId       (string)  IHS nakes pengisi (apoteker)
     *   - authorName     (string)  optional
     *   - authored       (string)  ISO8601
     *   - item           (array)   pohon item dari kelas pemetaan
     */
    public function createQuestionnaireResponse(array $data): array
    {
        foreach (['questionnaire', 'patientId', 'encounterId', 'item'] as $wajib) {
            if (empty($data[$wajib])) {
                throw new \InvalidArgumentException("Parameter {$wajib} wajib untuk QuestionnaireResponse.");
            }
        }

        $payload = [
            'resourceType'  => 'QuestionnaireResponse',
            'questionnaire' => $data['questionnaire'],
            'status'        => $data['status'] ?? 'completed',
            'subject'       => array_filter([
                'reference' => 'Patient/' . $data['patientId'],
                'display'   => $data['patientName'] ?? null,
            ]),
            'encounter'     => ['reference' => 'Encounter/' . $data['encounterId']],
            'authored'      => $data['authored'] ?? now()->toIso8601String(),
            // source = dari siapa jawabannya berasal. Pada telaah resep yang dinilai
            // adalah resep milik pasien, jadi contoh resmi pun menunjuk Patient.
            'source'        => ['reference' => 'Patient/' . $data['patientId']],
            'item'          => $data['item'],
        ];

        // author opsional: kalau IHS apotekernya belum terdaftar, lebih baik
        // kuesionernya tetap terkirim tanpa penulis daripada tidak terkirim sama
        // sekali — elemen objek kosong justru ditolak validator.
        if (!empty($data['authorId'])) {
            $payload['author'] = array_filter([
                'reference' => 'Practitioner/' . $data['authorId'],
                'display'   => $data['authorName'] ?? null,
            ]);
        }

        return $this->makeRequest('post', 'QuestionnaireResponse', $payload);
    }
}
