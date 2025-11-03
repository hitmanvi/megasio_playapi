# 游戏提供商（Game Providers）

## 目录结构

```
app/GameProviders/
├── GameProviderFactory.php  # 提供商工厂类
├── NetflixProvider.php      # Netflix提供商实现（示例）
└── ...                      # 其他提供商实现
```

## 创建新的提供商

1. 在 `app/GameProviders/` 目录下创建新的provider类
2. 类名格式：`{ProviderName}Provider`，例如：`DisneyProvider`
3. 实现 `App\Contracts\GameProviderInterface` 接口
4. 必须实现两个方法：
   - `demo(string $gameId, array $params = []): ?string`
   - `session(string $gameId, array $params = []): array`

## 使用示例

```php
use App\GameProviders\GameProviderFactory;

// 创建provider实例
$provider = GameProviderFactory::create('netflix', [
    'api_url' => 'https://api.netflix.com',
    'api_key' => 'your-api-key',
    'secret' => 'your-secret',
]);

// 获取demo地址
$demoUrl = $provider->demo('game123', ['language' => 'zh-CN']);

// 创建游戏会话
$session = $provider->session('game123', [
    'user_id' => 1,
    'currency' => 'USD',
    'language' => 'en',
]);
```

## 命名约定

- Provider类名：`{ProviderName}Provider`，首字母大写
- Factory会根据provider名称自动查找对应的类
- 例如：`netflix` -> `NetflixProvider`，`disney` -> `DisneyProvider`
