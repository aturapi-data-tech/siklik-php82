<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\Bpjs\BpjsHttp;
use Illuminate\Support\Facades\Validator;

use App\Http\Traits\customErrorMessagesTrait;
use Illuminate\Support\Facades\DB;


use Exception;

trait PcareTrait
{
    public function sendResponse($message, $data, $code = 200, $url, $requestTransferTime)
    {
        $response = [
            'response' => $data,
            'metadata' => [
                'message' => $message,
                'code' => $code,
            ],
        ];

        // Insert webLogStatus
        DB::table('web_log_status')->insert([
            'code' =>  $code,
            'date_ref' => Carbon::now(config('app.timezone')),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }
    public function sendError($error, $errorMessages = [], $code = 404, $url, $requestTransferTime)
    {
        $response = [
            'metadata' => [
                'message' => $error,
                'code' => $code,
            ],
        ];

        if (!empty($errorMessages)) {
            $response['response'] = $errorMessages;
        }
        // Insert webLogStatus
        DB::table('web_log_status')->insert([
            'code' =>  $code,
            'date_ref' => Carbon::now(config('app.timezone')),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }


    // API PCARE
    public function signature()
    {
        $cons_id =  config('bpjs.pcare.cons_id');
        $secretKey = config('bpjs.pcare.secret_key');
        $userkey = config('bpjs.pcare.user_key');


        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);

        $response = array(
            'X-cons-id' => $cons_id,
            'X-timestamp' => $tStamp,
            'X-signature' => $encodedSignature,
            'X-authorization' => "Basic " . base64_encode(config('bpjs.pcare.username') . ':' . config('bpjs.pcare.password') . ':' . '095'),
            'user_key' => $userkey,
            'decrypt_key' => $cons_id . $secretKey . $tStamp
        );
        return $response;
    }
    public function stringDecrypt($key, $string)
    {
        $encrypt_method = 'AES-256-CBC';
        $key_hash = hex2bin(hash('sha256', $key));
        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
        $output = \LZCompressor\LZString::decompressFromEncodedURIComponent($output);
        return $output;
    }

    public function response_decrypt($response, $signature, $url, $requestTransferTime)
    {
        if ($response->failed()) {
            return $this->sendError($response->reason(),  $response->json('response'), $response->status(), $url, $requestTransferTime);
        } else {
            // Check Response !200           -> metaData D besar
            $code = $response->json('metaData.code'); //code 200 -201 500 dll

            if ($code == 200 || $code == 201) {
                $decrypt = $this->stringDecrypt($signature['decrypt_key'], $response->json('response'));
                $data = json_decode($decrypt, true);
            } else {

                $data = json_decode($response, true);
            }
            return $this->sendResponse($response->json('metaData.message'), $data, $code, $url, $requestTransferTime);
        }
    }
    public function response_no_decrypt($response)
    {
        if ($response->failed()) {
            return $this->sendError($response->reason(),  $response->json('response'), $response->status(), null, null);
        } else {
            return $this->sendResponse($response->json('metaData.message'), $response->json('response'), $response->json('metaData.code'), null, null);
        }
    }







    // REFERENSI
    private function getPoliFktp($start, $end)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'start' => $start,
            'end' => $end,
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "start" => "required|numeric",
            "end" => "required|numeric",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "poli/fktp/" . $start . "/" . $end;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getKesadaran()
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = ['r' => ''];
        // lakukan validasis
        $validator = Validator::make($r, ["r" => ""], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "kesadaran";
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getDokter($start, $end)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'start' => $start,
            'end' => $end,
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "start" => "required|numeric",
            "end" => "required|numeric",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "dokter/" . $start . "/" . $end;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getSpesialis()
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = ['r' => ''];
        // lakukan validasis
        $validator = Validator::make($r, ["r" => ""], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "spesialis";
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getAlergi($alergi)
    {
        //parameter 1: 01:Makanan, 02:Udara, 03:Obat
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = ['r' => ''];
        // lakukan validasis
        $validator = Validator::make($r, ["r" => ""], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "alergi/jenis/" . $alergi;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getPrognosa()
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = ['r' => ''];
        // lakukan validasis
        $validator = Validator::make($r, ["r" => ""], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "prognosa";
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getStatusPulang($statusInap)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = ['statusInap' => $statusInap];
        // lakukan validasis
        $validator = Validator::make($r, ["statusInap" => "required|boolean"], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {
            $myStatusInap = $statusInap ? 'true' : 'false';
            $url = config('bpjs.pcare.url') . "statuspulang/rawatInap/" . $myStatusInap;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getProviderRayonisasi($start, $end)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'start' => $start,
            'end' => $end,
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "start" => "required|numeric",
            "end" => "required|numeric",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "provider/" . $start . "/" . $end;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getSarana()
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'r' => '',
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "r" => "",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "spesialis/sarana";
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getDiagnosa($kodeNamaDiagnosa, $start, $end)
    {
        // Parameter 1 : Kode atau nama diagnosa

        // Parameter 2 : Row data awal yang akan ditampilkan

        // Parameter 3 : Limit jumlah data yang akan ditampilkan
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'start' => $start,
            'end' => $end,
            'kodeNamaDiagnosa' => $kodeNamaDiagnosa
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "start" => "required|numeric",
            "end" => "required|numeric",
            "kodeNamaDiagnosa" => "required"
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "diagnosa/" . $kodeNamaDiagnosa . "/" . $start . "/" . $end;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }


    private function getReferensiSubSpesialis($kodeSpesialis)
    {
        // Content-Type: application/json; charset=utf-8

        // Parameter 1 : Kode Spesialis
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'kodeSpesialis' => $kodeSpesialis,

        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "kodeSpesialis" => "required",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {
            $url = config('bpjs.pcare.url') . "spesialis/" . $kodeSpesialis . "/subspesialis";

            $signature = $this->signature();
            $signature['Content-Type'] = 'application/json; charset=utf-8';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);

            // dd($response->getBody()->getContents()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getFaskesRujukanSubSpesialis($kodeSubspesialis, $kdSarana, $tglEstRujuk)
    {
        // Content-Type: application/json; charset=utf-8

        // Parameter 1 : Kode Sub Spesialis

        // Parameter 2 : Kode Sarana

        // Parameter 3 : Tanggal Estimasi Rujuk, format: dd-mm-yyyy
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'kodeSubspesialis' => $kodeSubspesialis,
            'kdSarana' => $kdSarana,
            'tglEstRujuk' => $tglEstRujuk,
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "kodeSubspesialis" => "required",
            // "kdSarana" => "required",
            "tglEstRujuk" => "required|date_format:d-m-Y",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {
            $url = config('bpjs.pcare.url') . "spesialis/rujuk/subspesialis/" . $kodeSubspesialis . "/sarana/" . $kdSarana . "/tglEstRujuk/" . $tglEstRujuk;

            $signature = $this->signature();
            // $signature['Content-Type'] = 'application/json; charset=utf-8';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);

            // dd($response->getBody()->getContents()); //Get Transfertime request
            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }


    // Get Peserta
    private function getPesertabyJenisKartu($jenisKartu, $nikNoka)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'jenisKartu' => $jenisKartu,
            'nikNoka' => $nikNoka,
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "jenisKartu" => "required",
            "nikNoka" => "required|min:11|max:16",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "peserta/" . $jenisKartu . "/" . $nikNoka;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getPeserta($noka)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            'noka' => $noka,
        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            "noka" => "required|min:11|max:16",
        ], $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "peserta/" . $noka;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }





    // PENDAFTARAN
    private function addPedaftaran(array $data = [])
    {
        //parameter 1: 01:Makanan, 02:Udara, 03:Obat
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = $data;
        $rules = [
            "kdProviderPeserta" => "bail|required",
            "tglDaftar" => "bail|required|date_format:d-m-Y",
            "noKartu" => "bail|required|digits:13",
            "kdPoli" => "bail|required",
            "keluhan" => "bail|required",
            "kunjSakit" => "bail|required",
            "sistole" => "bail|required|numeric",
            "diastole" => "bail|required|numeric",
            "beratBadan" => "bail|required|numeric",
            "tinggiBadan" => "bail|required|numeric",
            "respRate" => "bail|required|numeric",
            "lingkarPerut" => "bail|required|numeric",
            "heartRate" => "bail|required|numeric",
            "rujukBalik" => "bail|required",
            "kdTkp" => "bail|required"
        ];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 400, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "pendaftaran";
            $signature = $this->signature();
            $signature['Content-Type'] = 'text/plain';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->post($url, $r);


            // dd($response->getBody()->getContents()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function deletePedaftaran($noKartu, $tglDaftar, $noUrut, $kdPoli)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            "noKartu" => $noKartu,
            "tglDaftar" => $tglDaftar,
            "noUrut" => $noUrut,
            "kdPoli" => $kdPoli,
        ];
        $rules = [
            "noKartu" => "bail|required|digits:13",
            "tglDaftar" => "bail|required|date_format:d-m-Y",
            "noUrut" => "bail|required",
            "kdPoli" => "bail|required",
        ];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }

        // 2. Konversi tanggal ke format PCare (YYYY-MM-DD)
        try {
            $tglFormatted = Carbon::createFromFormat('d-m-Y', $tglDaftar)->format('d-m-Y');
        } catch (Exception $e) {
            return $this->sendError("Format tanggal tidak valid, harus d-m-Y.", [], 422, null, null);
        }

        try {

            $url = config('bpjs.pcare.url')
                . 'pendaftaran/peserta/' . $noKartu
                . '/tglDaftar/' . $tglFormatted
                . '/noUrut/' . $noUrut
                . '/kdPoli/' . $kdPoli;
            $signature = $this->signature();
            $signature['Content-Type'] = 'text/plain';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->delete($url, $r);
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 408, $url, null);
        }
    }

    private function getPendaftaranbyNomorUrut($noUrut, $tglDaftar)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            "noUrut" => $noUrut,
            "tglDaftar" => $tglDaftar,
        ];
        $rules = [
            "noUrut" => "bail|required",
            "tglDaftar" => "bail|required|date_format:d-m-Y",
        ];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "pendaftaran/noUrut/" . $noUrut . "/tglDaftar/" . $tglDaftar;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getPendaftaranProvider($tglDaftar, $start, $end)
    {
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = [
            "tglDaftar" => $tglDaftar,
            'start' => $start,
            'end' => $end,
        ];
        $rules = [
            "tglDaftar" => "bail|required|date_format:d-m-Y",
            "start" => "required|numeric",
            "end" => "required|numeric",
        ];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 201, null, null);
        }


        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "/pendaftaran/tglDaftar/" . $tglDaftar . "/" . $start . "/" . $end;
            $signature = $this->signature();
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function addKunjungan(array $data = [])
    {
        //parameter 1: 01:Makanan, 02:Udara, 03:Obat
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = $data;
        // $r['rujukLanjut']['subSpesialis']['kdSarana'] = 9;
        $r['rujukLanjut']['khusus'] = null;

        // dd($r['tglDaftar'], $r['tglPulang']);

        $rules = [
            "noKunjungan" => "",
            "noKartu" => "",
            "tglDaftar" => "",
            "kdPoli" => "",
            "keluhan" => "",
            "kdSadar" => "",
            "sistole" => "",
            "diastole" => "",
            "beratBadan" => "",
            "tinggiBadan" => "",
            "respRate" => "",
            "heartRate" => "",
            "lingkarPerut" => "",
            "kdStatusPulang" => "",
            "tglPulang" => [
                'nullable',
                'date_format:d-m-Y',
                function ($attribute, $value, $fail) use ($r) {


                    // kosong? skip
                    if (empty($value)) {
                        return;
                    }

                    try {
                        // parse kedua tanggal
                        $dtDaftar = Carbon::createFromFormat('d-m-Y', $r['tglDaftar']);
                        $dtPulang = Carbon::createFromFormat('d-m-Y', $value);
                    } catch (\Exception $e) {
                        // jika format salah, date_format sudah menangani error-nya
                        return;
                    }

                    // 1. tidak boleh sebelum tanggal daftar
                    if ($dtPulang->lt($dtDaftar)) {
                        $fail("Tanggal pulang tidak boleh sebelum tanggal daftar ({$dtDaftar->format('d-m-Y')}).");
                    }

                    // 2. tidak boleh setelah hari ini
                    if ($dtPulang->gt(Carbon::now())) {
                        $fail("Tanggal pulang tidak boleh lebih dari hari ini (" . Carbon::now()->format('d-m-Y') . ").");
                    }

                    // dd($dtPulang->lt($dtDaftar), [
                    //     'input_tglDaftar' => $r['tglDaftar'],
                    //     'input_tglPulang' => $value,
                    //     'parsed_tglDaftar' => Carbon::createFromFormat('d-m-Y', $r['tglDaftar'])->toDateString(),
                    //     'now'             => Carbon::now()->toDateString(),
                    // ]);
                },
            ],
            "kdDokter" => "",
            "kdDiag1" => "",
            "kdDiag2" => "",
            "kdDiag3" => "",
            "kdPoliRujukInternal" => "",

            "rujukLanjut.tglEstRujuk" => "",
            "rujukLanjut.kdppk" => "",
            "rujukLanjut.subSpesialis" => "",

            "rujukLanjut.khusus" => "",
            "rujukLanjut.khusus.kdKhusus" => "",
            "rujukLanjut.khusus.kdSubSpesialis1" => "",
            "rujukLanjut.khusus.catatan" => "",

            "kdTacc" => "",
            "alasanTacc" => "",
            "anamnesa" => "",
            "alergiMakan" => "",
            "alergiUdara" => "",
            "alergiObat" => "",
            "kdPrognosa" => "",
            "terapiObat" => "",
            "terapiNonObat" => "",
            "bmhp" => "",
            "suhu" => ""
        ];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 400, null, null);
        }

        // handler when time out and off line mode
        try {


            $url = config('bpjs.pcare.url') . "kunjungan";
            $signature = $this->signature();
            $signature['Content-Type'] = 'text/plain';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->post($url, $r);

            // dd($response->getBody()->getContents()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function editKunjungan(array $data = [])
    {
        //parameter 1: 01:Makanan, 02:Udara, 03:Obat
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = $data;
        $rules = [
            "noKunjungan" => "",
            "noKartu" => "",
            "tglDaftar" => "",
            "kdPoli" => "",
            "keluhan" => "",
            "kdSadar" => "",
            "sistole" => "",
            "diastole" => "",
            "beratBadan" => "",
            "tinggiBadan" => "",
            "respRate" => "",
            "heartRate" => "",
            "lingkarPerut" => "",
            "kdStatusPulang" => "",
            "tglPulang" => "",
            "kdDokter" => "",
            "kdDiag1" => "",
            "kdDiag2" => "",
            "kdDiag3" => "",
            "kdPoliRujukInternal" => "",

            "rujukLanjut.tglEstRujuk" => "",
            "rujukLanjut.kdppk" => "",
            "rujukLanjut.subSpesialis" => "",

            "rujukLanjut.khusus" => "",
            "rujukLanjut.khusus.kdKhusus" => "",
            "rujukLanjut.khusus.kdSubSpesialis1" => "",
            "rujukLanjut.khusus.catatan" => "",

            "kdTacc" => "",
            "alasanTacc" => "",
            "anamnesa" => "",
            "alergiMakan" => "",
            "alergiUdara" => "",
            "alergiObat" => "",
            "kdPrognosa" => "",
            "terapiObat" => "",
            "terapiNonObat" => "",
            "bmhp" => "",
            "suhu" => ""
        ];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "kunjungan";
            $signature = $this->signature();
            $signature['Content-Type'] = 'text/plain';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->put($url, $r);

            // dd($response->getBody()->getContents()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function deleteKunjungan(string $noKunjungan = '')
    {
        //parameter 1: 01:Makanan, 02:Udara, 03:Obat
        // customErrorMessages
        $messages = customErrorMessagesTrait::messages();

        // Masukkan Nilai dari parameter
        $r = ["noKunjungan" => $noKunjungan];
        $rules = ["noKunjungan" => "required"];
        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), $validator->errors()->all(), 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = config('bpjs.pcare.url') . "kunjungan/" . $noKunjungan;
            $signature = $this->signature();
            $signature['Content-Type'] = 'text/plain';
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->delete($url);

            // dd($response->getBody()->getContents()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return $this->response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), $validator->errors()->all(), 408, $url, null);
        }
    }

    private function getRiwayatKunjungan($noKartu)
    {
        // 1. Validasi input
        $messages = customErrorMessagesTrait::messages();
        $validator = Validator::make(
            ['noKartu' => $noKartu],
            ['noKartu' => 'bail|required|digits:13'],
            $messages
        );

        if ($validator->fails()) {
            return $this->sendError(
                $validator->errors()->first(),
                $validator->errors()->all(),
                422,
                null,
                null
            );
        }

        // 2. Siapkan URL
        $url = config('bpjs.pcare.url') . "kunjungan/peserta/" . $noKartu;

        // 3. Siapkan header (termasuk Content-Type)
        $signature = $this->signature();
        $signature['Content-Type'] = 'application/json; charset=utf-8';

        // 4. Panggil API dengan GET
        try {
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);
        } catch (Exception $e) {
            return $this->sendError(
                'Gagal terhubung ke PCare: ' . $e->getMessage(),
                [],
                408,
                $url,
                null
            );
        }

        // 5. Decrypt & handle response
        return $this->response_decrypt(
            $response,
            $signature,
            $url,
            $response->transferStats->getTransferTime()
        );
    }

    private function getRujukanKunjungan($noRujukan)
    {

        // 1. Validasi input
        $messages = customErrorMessagesTrait::messages();
        $validator = Validator::make(
            ['noRujukan' => $noRujukan],
            ['noRujukan' => 'bail|required'],
            $messages
        );

        if ($validator->fails()) {
            return $this->sendError(
                $validator->errors()->first(),
                $validator->errors()->all(),
                422,
                null,
                null
            );
        }

        // 2. Siapkan URL
        $url = config('bpjs.pcare.url') . "kunjungan/rujukan/" . $noRujukan;

        // 3. Siapkan headers (dengan Content-Type)
        $signature = $this->signature();
        $signature['Content-Type'] = 'application/json; charset=utf-8';

        // 4. Panggil API dengan GET
        try {
            $response = BpjsHttp::mulai()
                ->withHeaders($signature)
                ->get($url);
        } catch (Exception $e) {
            return $this->sendError(
                'Gagal terhubung ke PCare: ' . $e->getMessage(),
                [],
                408,
                $url,
                null
            );
        }

        // 5. Decrypt & handle response
        return $this->response_decrypt(
            $response,
            $signature,
            $url,
            $response->transferStats->getTransferTime()
        );
    }
}
