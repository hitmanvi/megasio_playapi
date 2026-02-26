<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Services\VipService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Currency;
use App\Models\User;

class UserController extends Controller
{
    protected VipService $vipService;

    public function __construct(VipService $vipService)
    {
        $this->vipService = $vipService;
    }

    /**
     * 获取当前用户信息
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return $this->responseItem([
            'uid' => $user->uid,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'invite_code' => $user->invite_code,
            'display_currencies' => $user->getDisplayCurrencies(),
            'base_currency' => $user->getBaseCurrency(),
            'current_currency' => $user->getCurrentCurrency(),
            'vip' => $this->vipService->getUserVipInfo($user),
            'is_first_time_set_password' => $user->isFirstTimeSetPassword(),
        ]);
    }

    /**
     * 更新用户货币偏好设置
     */
    public function updateCurrencyPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'display_currencies' => 'nullable|array',
            'display_currencies.*' => 'string|max:10',
            'base_currency' => 'nullable|string|max:10',
            'current_currency' => 'nullable|string|max:10',
        ]);

        $user = $request->user();
        $updated = false;

        // 更新展示货币列表
        if ($request->has('display_currencies')) {
            $currencies = $request->input('display_currencies', []);
            
            // 验证货币代码是否有效（如果不为空）
            if (!empty($currencies)) {
                $validCurrencies = Currency::whereIn('code', $currencies)
                    ->pluck('code')
                    ->toArray();

                if (count($validCurrencies) !== count($currencies)) {
                    return $this->error(ErrorCode::VALIDATION_ERROR, [
                        'display_currencies' => ['Some currency codes are invalid'],
                    ]);
                }
                $user->setDisplayCurrencies($validCurrencies);
            } else {
                $user->setDisplayCurrencies([]);
            }
            $updated = true;
        }

        // 更新基准币种
        if ($request->has('base_currency')) {
            $currency = $request->input('base_currency');
            if ($currency) {
                $currency = strtoupper($currency);
                $currencyModel = Currency::where('code', $currency)->first();
                if (!$currencyModel) {
                    return $this->error(ErrorCode::VALIDATION_ERROR, [
                        'base_currency' => ['Currency not found'],
                    ]);
                }
                $user->setBaseCurrency($currency);
            } else {
                $user->base_currency = null;
                $user->save();
            }
            $updated = true;
        }

        // 更新当前使用的币种
        if ($request->has('current_currency')) {
            $currency = $request->input('current_currency');
            if ($currency) {
                $currency = strtoupper($currency);
                $currencyModel = Currency::where('code', $currency)->first();
                if (!$currencyModel) {
                    return $this->error(ErrorCode::VALIDATION_ERROR, [
                        'current_currency' => ['Currency not found'],
                    ]);
                }
                $user->setCurrentCurrency($currency);
            } else {
                $user->current_currency = null;
                $user->save();
            }
            $updated = true;
        }

        if (!$updated) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'No fields to update');
        }

        return $this->responseItem([
            'display_currencies' => $user->getDisplayCurrencies(),
            'base_currency' => $user->getBaseCurrency(),
            'current_currency' => $user->getCurrentCurrency(),
        ]);
    }

    /**
     * 更新用户信息
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $rules = [];
        $updated = false;

        // 如果请求中包含 name，则验证并更新
        if ($request->has('name')) {
            $rules['name'] = 'required|string|max:255';
        }

        // 如果请求中包含 invite_code，则验证并更新
        if ($request->has('invite_code')) {
            $rules['invite_code'] = [
                'required',
                'string',
                'min:6',
                'max:8',
                'regex:/^[A-Z0-9]{6,8}$/',
            ];
        }

        if (!empty($rules)) {
            $request->validate($rules);
        }

        // 更新 name
        if ($request->has('name')) {
            $user->name = $request->input('name');
            $updated = true;
        }

        // 更新 invite_code
        if ($request->has('invite_code')) {
            $existingUser = User::where('invite_code', strtoupper($request->input('invite_code')))
                ->where('id', '!=', $user->id)
                ->first();
            if ($existingUser) {
                return $this->error(ErrorCode::INVITE_CODE_ALREADY_EXISTS);
            }
            $inviteCode = strtoupper($request->input('invite_code'));
            $user->invite_code = $inviteCode;
            $updated = true;
        }

        if (!$updated) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'No fields to update');
        }

        $user->save();

        return $this->responseItem([
            'name' => $user->name,
            'invite_code' => $user->invite_code,
        ]);
    }
}
