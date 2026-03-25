<?php

namespace App\Jobs;

use App\Services\UserPaymentExtraInfoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkPaymentExtraInfoDuplicateUniqueValuesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  \App\Models\UserPaymentExtraInfo::TYPE_*  $type
     * @param  array<string, mixed>  $extraInfo
     */
    public function __construct(
        public string $paymentMethodName,
        public string $type,
        public array $extraInfo
    ) {}

    public function handle(UserPaymentExtraInfoService $service): void
    {
        $service->markDuplicateUniqueValuesAcrossUsers(
            $this->paymentMethodName,
            $this->type,
            $this->extraInfo
        );
    }
}
