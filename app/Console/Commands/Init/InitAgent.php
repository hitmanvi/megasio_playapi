<?php

namespace App\Console\Commands\Init;

use App\Models\Agent;
use App\Models\AgentLink;
use Illuminate\Console\Command;

class InitAgent extends Command
{
    protected $signature = 'init:agent';

    protected $description = '初始化默认 agent（noagent）及 agent_link，用于无 agent 用户的绑定';

    public const NOAGENT_NAME = 'noagent';

    public function handle(): int
    {
        $this->info('初始化 noagent...');

        $agent = Agent::where('name', self::NOAGENT_NAME)->first();
        if (!$agent) {
            $data = [
                'name' => self::NOAGENT_NAME,
                'remark' => '默认 agent，用于无推广来源的用户',
                'status' => Agent::STATUS_ACTIVE,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('agents', 'account')) {
                $data['account'] = 'noagent';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('agents', 'password')) {
                $data['password'] = \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(16)));
            }
            $agent = Agent::create($data);
            $this->info('已创建 Agent: ' . self::NOAGENT_NAME);
        } else {
            $this->line('Agent ' . self::NOAGENT_NAME . ' 已存在');
        }

        $link = AgentLink::where('agent_id', $agent->id)
            ->where('name', self::NOAGENT_NAME)
            ->first();

        if (!$link) {
            $link = AgentLink::create([
                'agent_id' => $agent->id,
                'name' => self::NOAGENT_NAME,
                'promotion_code' => AgentLink::NOAGENT_PROMOTION_CODE,
                'status' => AgentLink::STATUS_ACTIVE,
            ]);
            $this->info('已创建 AgentLink: ' . self::NOAGENT_NAME . ' (promotion_code=' . AgentLink::NOAGENT_PROMOTION_CODE . ')');
        } else {
            $this->line('AgentLink ' . self::NOAGENT_NAME . ' 已存在');
        }

        $this->newLine();
        $this->info('✓ 初始化完成');
        $this->table(
            ['Agent ID', 'AgentLink ID', 'promotion_code'],
            [[$agent->id, $link->id, $link->promotion_code]]
        );

        return Command::SUCCESS;
    }
}
