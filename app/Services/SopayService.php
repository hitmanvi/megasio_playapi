<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Enums\ErrorCode;
use App\Exceptions\Exception;

class SopayService
{
    const ErrorCode = [
        0 => ErrorCode::SUCCESS,
        10000 => ErrorCode::PAY_DEPOSIT_FAILED,
        10500 => ErrorCode::PAY_DEPOSIT_FAILED,
        10501 => ErrorCode::PAY_DEPOSIT_FAILED,
        10502 => ErrorCode::PAY_DEPOSIT_FAILED,
        10503 => ErrorCode::PAY_DEPOSIT_FAILED,
        10504 => ErrorCode::PAY_DEPOSIT_FAILED,
        10505 => ErrorCode::PAY_INVALID_TIME,
        10506 => ErrorCode::PAY_DEPOSIT_FAILED,
        10507 => ErrorCode::PAY_DEPOSIT_FAILED,
        10508 => ErrorCode::PAY_ATTEMPT_TOO_MANY,
        10509 => ErrorCode::PAY_DEPOSIT_FAILED,
        10510 => ErrorCode::PAY_INVALID_AMOUNT,
        10511 => ErrorCode::PAY_DEPOSIT_FAILED,
        10512 => ErrorCode::PAY_DEPOSIT_FAILED,
        10513 => ErrorCode::PAY_DEPOSIT_FAILED,
        10514 => ErrorCode::PAY_UNAVAILABLE,
        10516 => ErrorCode::PAY_UNAVAILABLE,
        10517 => ErrorCode::PAY_DEPOSIT_FAILED,
        10518 => ErrorCode::PAY_INVALID_PARAM,
        10519 => ErrorCode::PAY_UNAVAILABLE,
        10520 => ErrorCode::PAY_IFSC,
        10521 => ErrorCode::PAY_INVALID_AMOUNT,
        10522 => ErrorCode::PAY_INVALID_PARAM,
        10523 => ErrorCode::PAY_EXPIRED,
    ];


    protected $endpoint;
    protected $appKey;
    protected $appId;
    protected $callbackUrl;

    public function __construct()
    {
        $this->endpoint    = config('services.sopay.endpoint');
        $this->appId       = config('services.sopay.app_id');
        $this->appKey      = config('services.sopay.app_key');
        $this->callbackUrl = config('services.sopay.callback_url');
    }

    public function getDepositInfo($amount, $paymentMethod)
    {
        $params = [
            'symbol'     => $paymentMethod->currency,
            'coin_type'  => $paymentMethod->currency_type,
            'amount'     => $amount,
            'payment_id' => $paymentMethod->key,
            'method'     => 'deposit_info',
        ];
        $params = $this->sign($params);
        $url    = $this->endpoint . '/api/orders/deposit/info';
        $resp   = Http::post($url, $params);
        $res    = $resp->json();

        if(!$res) {
            throw new Exception(ErrorCode::INTERNAL_ERROR);
        }
        if ($res['code'] != 0) {
            throw new Exception(self::ErrorCode[$res['code']]);
        }

        $fields                   = [];
        $d                        = $res['data'];
        $fields['channel_id']     = $d['channel_id'];
        $fields['need_info']      = $d['need_info'];
        $fields['deposit_fields'] = $d['need_fields'];
        $fields['native_support'] = $d['native_support'] ?? false;
        foreach($fields['deposit_fields'] as $k => $v) {
            $fields['deposit_fields'][$k]['title']       = $v['field'];
            $fields['deposit_fields'][$k]['placeholder'] = $v['field'];
        }

        return $fields;
    }

    public function getWithdrawInfo($amount, $paymentMethod)
    {
        if (!$paymentMethod->is_fiat) {
            $fields['withdraw_fields'] = [
                [
                    'field'       => 'address',
                    'require'     => true,
                    'type'        => 'string',
                    'title'       => 'Address',
                    'placeholder' => 'Address',
                ],
            ];
            return $fields;
        }

        $params = [
            'symbol'     => $paymentMethod->currency,
            'coin_type'  => $paymentMethod->currency_type,
            'amount'     => $amount,
            'payment_id' => $paymentMethod->key,
            'method'     => 'withdraw_info',
        ];
        $params = $this->sign($params);
        $url    = $this->endpoint . '/api/orders/withdraw/info';
        $resp   = Http::post($url, $params);
        $res    = $resp->json();

        if(!$res) {
            throw new Exception(ErrorCode::INTERNAL_ERROR);
        }
        if ($res['code'] != 0) {
            throw new Exception(self::ErrorCode[$res['code']]);
        }

        $codes = [];
        $fields                    = [];
        $d                         = $res['data'];
        $fields['channel_id']      = $d['channel_id'];
        $fields['withdraw_fields'] = $d['withdraw_info']['withdraw_fields'];
        if (isset($d['withdraw_info']['extra']['bank_code'])) {
            foreach ($d['withdraw_info']['extra']['bank_code'] as $key => $t) {
                if ($paymentMethod->currency == 'IDR' && isset($t['bank_id'])) {
                    $codes[$key]['name']      = $t['bank_code'];
                    $codes[$key]['value']     = $t['bank_code'];
                    $codes[$key]['bank_info'] = $t;
                    $codes[$key]['value_type']   = '1';
                } else {
                    if (isset($t['bank_name'])) $codes[$key]['name'] = $t['bank_name'];
                    if (isset($t['bank_code'])) $codes[$key]['value'] = $t['bank_code'];
                    if (isset($t['bank_icon'])) $codes[$key]['icon'] = $t['bank_icon'];
                }

            }
        }

        foreach ($fields['withdraw_fields'] as $k => $v) {
            if ($v['field'] == 'bank_code') {
                $fields['withdraw_fields'][$k]['list'] = $codes;
            }
            if ($v['field'] == 'bank_type' && isset($d['withdraw_info']['extra']['bank_type'])) {
                $fields['withdraw_fields'][$k]['list'] = $d['withdraw_info']['extra']['bank_type'];
            }
            if ($v['field'] == 'pix_type' && isset($d['withdraw_info']['extra']['pix_type'])) {
                $fields['withdraw_fields'][$k]['list'] = $d['withdraw_info']['extra']['pix_type'];
            }
            if ($v['field'] == 'wallet_type' && isset($d['withdraw_info']['extra']['wallet_type'])) {
                $fields['withdraw_fields'][$k]['list'] = $d['withdraw_info']['extra']['wallet_type'];
            }
            if ($v['field'] == 'account_type' && isset($d['withdraw_info']['extra']['account_type'])) {
                $fields['withdraw_fields'][$k]['list'] = $d['withdraw_info']['extra']['account_type'];
            }
            if ($paymentMethod->currency == 'IDR') {
                if ($v['field'] == 'bank_id' || $v['field'] == 'bank_name') {
                    unset($fields['withdraw_fields'][$k]);
                }
            }
            foreach($fields['withdraw_fields'] as $kk => $vv) {
                $fields['withdraw_fields'][$kk]['title']       = $vv['field'];
                $fields['withdraw_fields'][$kk]['placeholder'] = $vv['field'];
            }
        }

        return $fields;
    }

    public function deposit($deposit, $payment, $nativeApp)
    {
        $data = $this->getDepositData($deposit, $payment, $nativeApp);
        $url  = $this->endpoint . '/api/orders/deposit';
        $resp = Http::post($url, $data);
        $res  = $resp->json();

        if (!$res) {
            return null;
        }
        if ($res['code'] != 0) {
            throw new Exception(self::ErrorCode[$res['code']]);
        }

        $resData = $res['data'];
        $deposit->update(['out_trade_no' => $resData['order_id']]);

        return [
            'url'        => $resData['url'],
            'extra_info' => ($resData['extra_info'] ?? null) ?: null,
            'datetime'   => time(),
            'html' => $resData['html'] ?? null,
        ];
    }

    private function sign($params)
    {
        $params['app_id']    = $this->appId;
        $params['timestamp'] = time();
        ksort($params);
        $presign        = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $presign        = md5($presign);
        $sign           = hash_hmac('sha256', $presign, $this->appKey);
        $params['sign'] = $sign;
        return $params;
    }

    private function getDepositData($deposit, $payment, $nativeApp)
    {
        $extraInfo = $deposit->extra_info;

        $data = [
            'amount'         => $deposit->amount,
            'type'           => $payment->is_fiat ? 2 : 1,
            'symbol'         => $payment->currency,
            'coin_type'      => $payment->currency_type,
            'subject'        => 'deposit',
            'out_trade_no'   => $deposit->order_no,
            'user_ip'        => $deposit->user_ip,
            'payment_id'     => $payment->key,
            'channel_id'     => $deposit->deposit_info['channel_id'] ?? 0,
            'has_native_app' => $nativeApp,
            'method'         => 'deposit',
            'callback_url'   => $this->callbackUrl,
            'ua' => request()->header('User-Agent'),
            'user_id' => $deposit->user->user_id,
        ];

        if ($payment->is_fiat) {
            $data['payment_id'] = (int)$payment->key;
            $channelId          = $deposit->deposit_info['channel_id'] ?? 0;
            
            if ($channelId) {
                $data['channel_id'] = (int)$channelId;
            }
        }
        $data['return_url'] = $this->getReturnUrl($deposit);
        if (count($extraInfo) > 0) {
            $data['extra_info'] = $this->trimValue($extraInfo);
        }

        $data = $this->sign($data);

        return $data;
    }

    private function getReturnUrl($deposit)
    {
        // TODO: 获取返回URL
        return '';
    }

    private function trimValue(array $info)
    {
        $data = [];
        foreach ($info as $k => $v) {
            $data[$k] = trim($v);
        }
        return $data;
    }
}
