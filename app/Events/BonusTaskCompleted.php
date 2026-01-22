<?php

namespace App\Events;

use App\Models\BonusTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BonusTaskCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Bonus 任务
     */
    public BonusTask $task;

    /**
     * Create a new event instance.
     */
    public function __construct(BonusTask $task)
    {
        $this->task = $task;
    }
}
