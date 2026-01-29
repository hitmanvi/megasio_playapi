<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Services\PromotionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateDepositBonusTask implements ShouldQueue
{
    use InteractsWithQueue;

    protected PromotionService $promotionService;

    public function __construct()
    {
        $this->promotionService = new PromotionService();
    }

    /**
     * Handle the event.
     */
    public function handle(DepositCompleted $event): void
    {
        $this->promotionService->processDepositBonus($event->deposit);
    }
}
