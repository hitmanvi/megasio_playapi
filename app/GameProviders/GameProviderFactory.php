<?php

namespace App\GameProviders;

use App\Contracts\GameProviderInterface;
use Illuminate\Support\Str;

/**
 * 游戏提供商工厂
 * 
 * 用于创建不同游戏提供商的实例
 */
class GameProviderFactory
{
    /**
     * 创建游戏提供商实例
     * 
     * @param string $providerName provider名称（如：funky, netflix）
     * @param string|null $currency 货币类型（某些 provider 需要，如 FunkyProvider）
     * @param array $config 其他配置参数
     * @return GameProviderInterface
     * @throws \InvalidArgumentException 当provider不存在时
     */
    public static function create(string $providerName, ?string $currency = null, array $config = []): GameProviderInterface
    {
        $providerName = strtolower($providerName);
        
        // 根据provider名称构建类名
        $className = self::getProviderClassName($providerName);
        
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Game provider '{$providerName}' not found. Class '{$className}' does not exist.");
        }
        
        // 实例化provider
        // 如果提供了 currency，优先使用 currency；否则使用 config 数组
        if ($currency !== null) {
            $provider = new $className($currency);
        } elseif (isset($config['currency'])) {
            $provider = new $className($config['currency']);
        } else {
            // 对于不需要 currency 的 provider，使用 config 数组
            $provider = new $className($config);
        }
        
        if (!($provider instanceof GameProviderInterface)) {
            throw new \InvalidArgumentException("Provider {$className} must implement GameProviderInterface");
        }
        
        return $provider;
    }

    /**
     * 根据provider名称获取类名
     * 
     * @param string $providerName
     * @return string
     */
    protected static function getProviderClassName(string $providerName): string
    {
        // 转换为驼峰命名，例如：netflix -> NetflixProvider
        $className = Str::studly($providerName) . 'Provider';
        return 'App\\GameProviders\\' . $className;
    }
}
