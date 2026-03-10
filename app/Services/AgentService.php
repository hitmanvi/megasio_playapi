<?php

namespace App\Services;

use App\Models\AgentLink;
use App\Models\User;

class AgentService
{
    /**
     * 根据用户选择对应的 AgentLink，用于事件上报时获取 pixel_id / access_token / app_id
     *
     * 根据 users.agent_link_id（注册时通过 promotion_code 绑定）
     *
     * @param User $user 用户
     * @return AgentLink|null 未匹配到则返回 null，将使用全局配置
     */
    public static function getAgentLinkForUser(User $user): ?AgentLink
    {
        if (empty($user->agent_link_id)) {
            return null;
        }
        $link = $user->agentLink;
        return ($link && $link->status === AgentLink::STATUS_ACTIVE) ? $link : null;
    }
}
