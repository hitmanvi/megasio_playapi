<?php

namespace App\Enums;

/**
 * 游戏提供商枚举
 * 
 * 定义所有可用的游戏提供商标识
 */
enum GameProvider: string
{
    case FUNKY = 'funky';

    /**
     * 获取所有提供商的标识数组
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 获取所有提供商的名称数组
     *
     * @return array<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * 检查给定的字符串是否为有效的提供商
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * 获取提供商的显示名称
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match($this) {
            self::FUNKY => 'Funky',
        };
    }

    /**
     * 获取提供商的描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::FUNKY => 'Funky 游戏提供商',
        };
    }
}

