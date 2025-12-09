<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SopayService;
use Illuminate\Support\Facades\Log;
use App\Services\DepositService;
use App\Services\WithdrawService;

class SopayController extends Controller
{
    protected SopayService $sopayService;
    protected DepositService $depositService;
    protected WithdrawService $withdrawService;
    
    public function __construct()
    {
        $this->sopayService = new SopayService();
        $this->depositService = new DepositService();
        $this->withdrawService = new WithdrawService();
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

        if(isset($data['subject']) && $data['subject'] == 'deposit') {
            return $this->handleDeposit($data);
        } elseif(isset($data['subject']) && $data['subject'] == 'withdraw') {
            return $this->handleWithdraw($data);
        }
    }

    private function handleDeposit($data)
    {
        $status = $data['status'];
        $orderId = $data['out_trade_no'];
        $outId = $data['order_id'];
        $amount = $data['amount'];
        $result = $this->depositService->finishDeposit($status, $orderId, $outId, $amount);
        if(!$result) {
            return '';
        }
        return 'ok';
    }

    private function handleWithdraw($data)
    {
        $status = $data['status'];
        if($status == SopayService::SOPAY_STATUS_SUCCEED) {
            $orderId = $data['out_trade_no'];
            $outId = $data['order_id'];
            $amount = $data['amount'];
            $result = $this->withdrawService->finishWithdraw($orderId, $outId, $amount);
            if(!$result) {
                return '';
            }
            return 'ok';
        } else {
            return '';
        }
    }
}