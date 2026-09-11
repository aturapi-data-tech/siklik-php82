<?php
// app/Http/Traits/SATUSEHAT/SnomedTrait.php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\Http;

trait SnomedTrait
{
    /**
     * Base URL of the FHIR server, defaults to tx.fhir.org
     * Defined in config/txfhir.base_url
     * Example: http://tx.fhir.org/r4
     *
     * @var string
     */
    protected string $txFhirBaseUrl;

    /**
     * Pin edisi SNOMED (URI version, config txfhir.snomed_version).
     * Edisi SATUSEHAT lebih tua dari tx.fhir.org — tanpa pin, pencarian bisa
     * mengembalikan konsep baru yang ditolak validator Kemkes (RuleNumber 10003).
     *
     * @var string|null
     */
    protected ?string $snomedVersion = null;

    /**
     * Initialize the FHIR base URL from config
     */
    public function initializeTxFhir(): void
    {
        $this->txFhirBaseUrl = config('txfhir.base_url', 'http://tx.fhir.org/r4');
        $this->snomedVersion = config('txfhir.snomed_version') ?: null;
    }

    /**
     * Perform a $lookup operation for a SNOMED CT concept by code
     *
     * @param string $code SNOMED CT concept ID (e.g. 22298006)
     * @return array The raw "parameter" array from FHIR Parameters resource
     * @throws \Exception on HTTP error
     */
    public function lookupSnomedConcept(string $code): array
    {
        $params = [
            'system' => 'http://snomed.info/sct',
            'code'   => $code,
        ];
        if ($this->snomedVersion) {
            $params['version'] = $this->snomedVersion;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/fhir+json',
        ])->get($this->txFhirBaseUrl . '/CodeSystem/$lookup', $params);

        // if (!$response->successful()) {
        //     throw new \Exception(
        //         'SNOMED lookup failed: HTTP ' . $response->status() . ' - ' . $response->body()
        //     );
        // }

        $json = $response->json();
        return $json['parameter'] ?? [];
    }

    /**
     * Cek sebuah kode dikenal pada edisi SNOMED yang di-pin (txfhir.snomed_version).
     *
     * @return bool|null true = ada; false = tidak ada di edisi ini (not-found);
     *                   null = tak bisa dipastikan (server error/timeout) — JANGAN
     *                   diperlakukan sebagai tidak ada.
     */
    public function snomedCodeExists(string $code): ?bool
    {
        $params = [
            'system' => 'http://snomed.info/sct',
            'code'   => $code,
        ];
        if ($this->snomedVersion) {
            $params['version'] = $this->snomedVersion;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/fhir+json',
        ])->get($this->txFhirBaseUrl . '/CodeSystem/$lookup', $params);

        $json = $response->json() ?? [];

        if ($response->successful() && !empty($json['parameter'])) {
            return true;
        }

        if (($json['resourceType'] ?? '') === 'OperationOutcome') {
            $issueCode = $json['issue'][0]['code'] ?? '';
            return $issueCode === 'not-found' ? false : null;
        }

        return null;
    }

    /**
     * Extract display string from FHIR "parameter" array
     *
     * @param array $parameters
     * @return string|null
     */
    public function extractDisplay(array $parameters): ?string
    {
        foreach ($parameters as $param) {
            if (($param['name'] ?? '') === 'display') {
                return $param['valueString'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract definition (if present) from FHIR "parameter" array
     *
     * @param array $parameters
     * @return string|null
     */
    public function extractDefinition(array $parameters): ?string
    {
        foreach ($parameters as $param) {
            if (($param['name'] ?? '') === 'definition') {
                return $param['valueString'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract Fully Specified Name (FSN) from FHIR "parameter" array
     *
     * @param array $parameters
     * @return string|null
     */
    public function extractFsn(array $parameters): ?string
    {
        foreach ($parameters as $param) {
            if (($param['name'] ?? '') === 'property' && isset($param['part']) && is_array($param['part'])) {
                foreach ($param['part'] as $part) {
                    if (($part['name'] ?? '') === 'valueString') {
                        return $part['valueString'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Convenience method: lookup and return code, display, definition, and FSN
     *
     * @param string $code SNOMED CT concept ID
     * @return array [
     *     'code' => string,
     *     'display' => ?string,
     *     'definition' => ?string,
     *     'fsn' => ?string,
     * ]
     */
    public function getSnomedConcept(string $code): array
    {
        $parameters = $this->lookupSnomedConcept($code);

        return [
            'code'       => $this->extractDisplay($parameters) ? $code : null,
            'display'    => $this->extractDisplay($parameters),
            'definition' => $this->extractDefinition($parameters),
            'fsn'        => $this->extractFsn($parameters),
        ];
    }




    /**
     * Search SNOMED CT concepts by term using implicit ValueSet expansion
     */
    // ValueSet Id	Deskripsi	URL
    // FHIR Build
    // FHIR Build
    // condition-code	Clinical findings (dasar is-a 404684003)	http://hl7.org/fhir/ValueSet/condition-code
    // procedure-code	Procedures (dasar is-a 71388002)	http://hl7.org/fhir/ValueSet/procedure-code
    // observation-code	Observable entities (dasar is-a 258天然380006)	http://hl7.org/fhir/ValueSet/observation-code
    // substance-code	Substances (dasar is-a 105590001)	http://hl7.org/fhir/ValueSet/substance-code
    // body-site	Body sites (dasar is-a 442083009)	http://hl7.org/fhir/ValueSet/body-site
    // bodystructure-code	Anatomical body structures (dasar is-a 442083009)	http://hl7.org/fhir/ValueSet/bodystructure-code
    // bodystructure-laterality	Modifiers untuk laterality pada struktur tubuh	http://hl7.org/fhir/ValueSet/bodystructure-laterality
    // bodystructure-relative-location	Modifiers untuk lokasi relatif struktur tubuh	http://hl7.org/fhir/ValueSet/bodystructure-relative-location
    // bodystructure-bodylandmarkorientation-clockface-position	Posisi orientasi landmark tubuh (clockface)	http://hl7.org/fhir/ValueSet/bodystructure-bodylandmarkorientation-clockface-position
    // snomed-intl-gps	Global Patient Set subset (ex tensional)	http://terminology.hl7.org/ValueSet/snomed-intl-gps
    public function searchSnomedConcepts(string $term, int $limit = 10, ?string $valueSetId = 'procedure-code'): array
    {

        if ($valueSetId) {
            $url = "{$this->txFhirBaseUrl}/ValueSet/{$valueSetId}/\$expand";
            $params = [
                'filter' => $term,
                'count'  => $limit,
                'offset' => 0,
            ];
        } else {
            $url = "{$this->txFhirBaseUrl}/ValueSet/\$expand";
            $params = [
                'identifier' => 'http://snomed.info/sct',
                'filter'     => $term,
                'count'      => $limit,
                'offset'     => 0,
            ];
        }
        if ($this->snomedVersion) {
            $params['system-version'] = 'http://snomed.info/sct|' . $this->snomedVersion;
        }
        $response = Http::withHeaders([
            'Accept' => 'application/fhir+json',
        ])->get($url, $params);
        if (! $response->successful()) {
            throw new \Exception(
                "SNOMED search failed: HTTP {$response->status()} - {$response->body()}"
            );
        }

        return $response->json()['expansion']['contains'] ?? [];
    }
}
