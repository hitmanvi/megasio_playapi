<?php

namespace App\Listeners;

use App\Events\FirstDepositCompleted;
use App\Models\Tag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AddPaidTagOnFirstDeposit implements ShouldQueue
{
    use InteractsWithQueue;

    public const PAID_TAG_NAME = 'Paid';

    public function handle(FirstDepositCompleted $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $user = $deposit->user;

        if (!$user) {
            return;
        }

        if ($user->hasTag(self::PAID_TAG_NAME)) {
            return;
        }

        $tag = Tag::firstOrCreate(
            ['name' => self::PAID_TAG_NAME],
            [
                'display_name' => 'Paid',
                'enabled' => true,
                'sort_id' => 0,
            ]
        );

        $user->addTag($tag->id, 'first_deposit', '首次充值');
    }
}
