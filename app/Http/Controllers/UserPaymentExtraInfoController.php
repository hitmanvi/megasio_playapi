<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\PaymentMethod;
use App\Models\UserPaymentExtraInfo;
use App\Services\UserPaymentExtraInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserPaymentExtraInfoController extends Controller
{
    /**
     * GET /users/mine/readonly-payment-extra-info
     * 获取当前用户对指定支付方式、指定类型下已保存的扩展字段（不过滤 read_only；无行或过滤后无字段时 data 为 null）
     *
     * Query: payment_method_id (required), type = deposit|withdraw (required)
     */
    public function readonlyPaymentExtraInfo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $validator = Validator::make($request->query(), [
            'payment_method_id' => 'required|integer|min:1',
            'type' => [
                'required',
                'string',
                Rule::in([UserPaymentExtraInfo::TYPE_DEPOSIT, UserPaymentExtraInfo::TYPE_WITHDRAW]),
            ],
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        $paymentMethod = PaymentMethod::query()->find((int) $request->query('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        $service = new UserPaymentExtraInfoService();

        return $this->responseItem($service->getReadonlySavedExtraInfoForPaymentMethod(
            $user->id,
            $paymentMethod,
            (string) $request->query('type')
        ));
    }
}
