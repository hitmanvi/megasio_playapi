<?php

namespace App\Http\Controllers;

use App\Models\SopayCallbackLog;
use App\Services\DepositService;
use App\Services\SopayService;
use App\Services\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $signData = $request->get('sign_data');
        $data = $request->all();

        // 初始化日志数据
        $logData = [
            'order_id' => $data['order_id'] ?? null,
            'out_trade_no' => $data['out_trade_no'] ?? null,
            'subject' => $data['subject'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'request_headers' => $request->headers->all(),
            'request_body' => $data ?? [],
            'sign_data' => $signData,
            'signature' => $signature,
            'signature_valid' => false,
            'process_result' => null,
            'process_error' => null,
            'ip' => $request->ip(),
        ];

        if (!$signature) {
            $logData['process_error'] = 'Missing signature header';
            SopayCallbackLog::log($logData);
            return '';
        }

        if (!$signData) {
            $logData['process_error'] = 'Missing sign_data';
            SopayCallbackLog::log($logData);
            return '';
        }

        if (!$this->sopayService->verifySign($signData, $signature)) {
            Log::error('Sopay Callback Signature Verification Failed', [
                'sign_data' => $signData,
                'signature' => $signature,
            ]);
            $logData['process_error'] = 'Signature verification failed';
            SopayCallbackLog::log($logData);
            return '';
        }

        $logData['signature_valid'] = true;

        if (!$data) {
            Log::error('Sopay Callback Invalid sign_data JSON', ['sign_data' => $signData]);
            $logData['process_error'] = 'Invalid sign_data JSON';
            SopayCallbackLog::log($logData);
            return '';
        }

        try {
            $result = '';
            if (isset($data['subject']) && $data['subject'] == 'deposit') {
                $result = $this->handleDeposit($data);
            } elseif (isset($data['subject']) && $data['subject'] == 'withdraw') {
                $result = $this->handleWithdraw($data);
            }

            $logData['process_result'] = $result ?: 'empty';
            SopayCallbackLog::log($logData);
            return $result;
        } catch (\Exception $e) {
            $logData['process_error'] = $e->getMessage();
            SopayCallbackLog::log($logData);
            return '';
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
        $orderId = $data['out_trade_no'];
        $outId = $data['order_id'];
        $amount = $data['amount'] ?? 0;
        $errorMessage = $data['error_message'] ?? null;

        if ($status == SopayService::SOPAY_STATUS_SUCCEED) {
            $result = $this->withdrawService->finishWithdraw($orderId, $outId, $amount);
            if (!$result) {
                return '';
            }
            return 'ok';
        } elseif ($status == SopayService::SOPAY_STATUS_FAILED || $status == SopayService::SOPAY_STATUS_REJECT) {
            $result = $this->withdrawService->failWithdraw($orderId, $outId, $errorMessage, $status);
            if (!$result) {
                return '';
            }
            return 'ok';
        }

        return '';
    }
}