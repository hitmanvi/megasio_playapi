<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateInviteCodesForUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-invite-codes {--force : 强制为所有用户重新生成邀请码}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为现有用户生成邀请码';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');

        // 查询需要生成邀请码的用户
        if ($force) {
            $users = User::all();
            $this->info('强制模式：将为所有用户生成邀请码');
        } else {
            $users = User::whereNull('invite_code')->get();
        }

        if ($users->isEmpty()) {
            $this->info('没有需要生成邀请码的用户');
            return Command::SUCCESS;
        }

        $this->info("开始为 {$users->count()} 个用户生成邀请码...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        DB::beginTransaction();
        try {
            foreach ($users as $user) {
                try {
                    $inviteCode = User::generateInviteCode();
                    $user->update(['invite_code' => $inviteCode]);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    $this->newLine();
                    $this->error("用户 ID {$user->id} 生成邀请码失败: " . $e->getMessage());
                }
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();

            $this->info("成功为 {$successCount} 个用户生成邀请码");
            if ($failCount > 0) {
                $this->warn("{$failCount} 个用户生成邀请码失败");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error('生成邀请码失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
