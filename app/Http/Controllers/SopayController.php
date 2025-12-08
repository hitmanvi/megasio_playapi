<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Enums\ErrorCode;
use App\Services\SopayService;
use Illuminate\Support\Facades\Log;

class SopayController extends Controller
{
    protected SopayService $sopayService;

    public function __construct(SopayService $sopayService)
    {
        $this->sopayService = $sopayService;
    }

    public function callback(Request $request)
    {
        $signature = $request->header('signature');
        if(!$signature) return '';

        $signData = $request->get('sign_data');
        if(!$signData) return '';

        Log::error('Sopay Callback Received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);
        
        if(!$this->sopayService->verifySign($signData, $signature)) {
            Log::error('Sopay Callback Signature Verification Failed', [
                'sign_data' => $signData,
                'signature' => $signature,
            ]);
            return '';
        }


        // if($this->sopayService->verifySign($signData, $signature)) {
        //     $data = $request->all();
        //     if(isset($data['subject']) && $data['subject'] == 'deposit') {
        //         $this->sopayService->depositSopay($data);
        //         return 'ok';
        //     }
        //     if(isset($data['subject']) && $data['subject'] == 'withdraw') {
        //         $result = CallbackService::withdrawSopay($data);
        //         if(!$result) {
        //             return '';
        //         } else {
        //             return 'ok';
        //         }
        //     }
        // }
        // return '';
        return 'ok';
    }
}