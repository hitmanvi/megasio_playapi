<?php

namespace Database\Seeders;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvitationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        if ($users->count() < 2) {
            $this->command->warn('Need at least 2 users to create invitations. Please create users first.');
            return;
        }

        $this->command->info('Creating invitation relationships...');

        // 获取已存在的邀请关系，避免重复
        $existingInviteeIds = Invitation::pluck('invitee_id')->toArray();
        
        // 过滤掉已经被邀请的用户
        $availableInvitees = $users->reject(function ($user) use ($existingInviteeIds) {
            return in_array($user->id, $existingInviteeIds);
        });

        if ($availableInvitees->isEmpty()) {
            $this->command->warn('All users have already been invited. No new invitations to create.');
            return;
        }

        $invitationCount = 0;
        $maxInvitations = min(50, $availableInvitees->count()); // 最多创建50个邀请关系

        DB::transaction(function () use ($users, $availableInvitees, $existingInviteeIds, &$invitationCount, $maxInvitations) {
            foreach ($availableInvitees->take($maxInvitations) as $invitee) {
                // 随机选择一个邀请人（不能是自己）
                $potentialInviters = $users->reject(function ($user) use ($invitee) {
                    return $user->id === $invitee->id;
                });

                if ($potentialInviters->isEmpty()) {
                    continue;
                }

                $inviter = $potentialInviters->random();

                // 创建邀请关系
                try {
                    Invitation::create([
                        'inviter_id' => $inviter->id,
                        'invitee_id' => $invitee->id,
                    ]);
                    $invitationCount++;
                } catch (\Exception $e) {
                    // 如果已存在（虽然理论上不应该），跳过
                    continue;
                }
            }
        });

        $this->command->info("Created {$invitationCount} invitation relationships.");
    }
}
