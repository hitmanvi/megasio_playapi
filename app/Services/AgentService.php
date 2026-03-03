<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;

class AgentService
{
    /**
     * 根据用户选择对应的 Agent，用于事件上报时获取 pixel_id / access_token / app_id
     *
     * 留白：由业务实现具体选择逻辑，例如：
     * - 根据用户注册时使用的推广码
     * - 根据用户关联的 inviter 的 agent
     * - 根据用户 meta 中的 agent_id
     *
     * @param User $user 用户
     * @return Agent|null 未匹配到则返回 null，将使用全局配置
     */
    public static function getAgentForUser(User $user): ?Agent
    {
        // TODO: 实现 Agent 选择逻辑
        return null;
    }
}
