<?php

namespace Database\Seeders;

use App\Models\ArticleGroup;
use App\Models\Article;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建分组
        $faqGroup = ArticleGroup::create([
            'name' => '常见问题',
            'icon' => 'fas fa-question-circle',
            'parent_id' => 0,
            'enabled' => true,
            'sort_id' => 1,
        ]);

        $depositGroup = ArticleGroup::create([
            'name' => '存款相关',
            'icon' => 'fas fa-money-bill-wave',
            'parent_id' => $faqGroup->id,
            'enabled' => true,
            'sort_id' => 1,
        ]);

        $withdrawGroup = ArticleGroup::create([
            'name' => '提款相关',
            'icon' => 'fas fa-hand-holding-usd',
            'parent_id' => $faqGroup->id,
            'enabled' => true,
            'sort_id' => 2,
        ]);

        $gameGroup = ArticleGroup::create([
            'name' => '游戏相关',
            'icon' => 'fas fa-gamepad',
            'parent_id' => $faqGroup->id,
            'enabled' => true,
            'sort_id' => 3,
        ]);

        $accountGroup = ArticleGroup::create([
            'name' => '账户相关',
            'icon' => 'fas fa-user-circle',
            'parent_id' => 0,
            'enabled' => true,
            'sort_id' => 2,
        ]);

        // 创建文章 - 存款相关
        Article::create([
            'title' => '如何存款？',
            'content' => '您可以通过以下方式存款：\n1. 登录您的账户\n2. 进入存款页面\n3. 选择支付方式\n4. 输入存款金额\n5. 完成支付',
            'group_id' => $depositGroup->id,
            'enabled' => true,
            'sort_id' => 1,
        ]);

        Article::create([
            'title' => '存款需要多长时间到账？',
            'content' => '存款到账时间取决于您选择的支付方式：\n- 银行卡：通常1-3个工作日\n- 电子钱包：即时到账\n- 加密货币：通常10-30分钟',
            'group_id' => $depositGroup->id,
            'enabled' => true,
            'sort_id' => 2,
        ]);

        Article::create([
            'title' => '存款有最低限额吗？',
            'content' => '是的，不同支付方式有不同的最低存款限额。请查看存款页面上的具体说明。',
            'group_id' => $depositGroup->id,
            'enabled' => true,
            'sort_id' => 3,
        ]);

        // 创建文章 - 提款相关
        Article::create([
            'title' => '如何提款？',
            'content' => '提款步骤：\n1. 登录您的账户\n2. 进入提款页面\n3. 选择提款方式\n4. 输入提款金额\n5. 提交申请\n6. 等待审核',
            'group_id' => $withdrawGroup->id,
            'enabled' => true,
            'sort_id' => 1,
        ]);

        Article::create([
            'title' => '提款需要多长时间？',
            'content' => '提款处理时间：\n- 审核时间：通常1-2个工作日\n- 到账时间：取决于您选择的提款方式，通常1-5个工作日',
            'group_id' => $withdrawGroup->id,
            'enabled' => true,
            'sort_id' => 2,
        ]);

        Article::create([
            'title' => '提款有手续费吗？',
            'content' => '部分提款方式可能会收取手续费，具体费用会在提款页面显示。我们建议您查看提款页面上的详细说明。',
            'group_id' => $withdrawGroup->id,
            'enabled' => true,
            'sort_id' => 3,
        ]);

        // 创建文章 - 游戏相关
        Article::create([
            'title' => '如何开始游戏？',
            'content' => '开始游戏的步骤：\n1. 登录您的账户\n2. 浏览游戏列表\n3. 选择您喜欢的游戏\n4. 点击"开始游戏"按钮\n5. 享受游戏乐趣',
            'group_id' => $gameGroup->id,
            'enabled' => true,
            'sort_id' => 1,
        ]);

        Article::create([
            'title' => '游戏支持哪些设备？',
            'content' => '我们的游戏支持以下设备：\n- Windows PC\n- Mac\n- iOS 设备（iPhone/iPad）\n- Android 设备\n- 网页浏览器',
            'group_id' => $gameGroup->id,
            'enabled' => true,
            'sort_id' => 2,
        ]);

        Article::create([
            'title' => '游戏有免费试玩吗？',
            'content' => '是的，大部分游戏都提供免费试玩模式。您可以在不登录的情况下体验游戏。',
            'group_id' => $gameGroup->id,
            'enabled' => true,
            'sort_id' => 3,
        ]);

        // 创建文章 - 账户相关
        Article::create([
            'title' => '如何注册账户？',
            'content' => '注册步骤：\n1. 点击网站上的"注册"按钮\n2. 填写注册信息（用户名、邮箱、密码等）\n3. 验证邮箱\n4. 完成注册',
            'group_id' => $accountGroup->id,
            'enabled' => true,
            'sort_id' => 1,
        ]);

        Article::create([
            'title' => '忘记密码怎么办？',
            'content' => '如果您忘记了密码：\n1. 点击登录页面的"忘记密码"链接\n2. 输入您的注册邮箱\n3. 查收重置密码邮件\n4. 按照邮件中的指示重置密码',
            'group_id' => $accountGroup->id,
            'enabled' => true,
            'sort_id' => 2,
        ]);

        Article::create([
            'title' => '如何修改个人信息？',
            'content' => '修改个人信息的步骤：\n1. 登录您的账户\n2. 进入"个人中心"\n3. 点击"编辑资料"\n4. 修改您想要更改的信息\n5. 保存更改',
            'group_id' => $accountGroup->id,
            'enabled' => true,
            'sort_id' => 3,
        ]);
    }
}
