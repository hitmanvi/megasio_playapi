<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SwaggerMergeOpenapi extends Command
{
    protected $signature = 'swagger:merge
                            {--source= : 分割文件目录，默认 resources/swagger/split}
                            {--output= : 输出文件路径，默认 resources/swagger/openapi.json}';

    protected $description = '将分割的 OpenAPI 文件合并回 openapi.json';

    public function handle(): int
    {
        $sourceDir = $this->option('source') ?: resource_path('swagger/split');
        $output = $this->option('output') ?: resource_path('swagger/openapi.json');

        if (!File::isDirectory($sourceDir)) {
            $this->error("分割目录不存在: {$sourceDir}");
            $this->line("请先运行: php artisan swagger:split");
            return 1;
        }

        // 1. 加载 base
        $basePath = $sourceDir . '/base.json';
        if (!File::exists($basePath)) {
            $this->error("base.json 不存在");
            return 1;
        }
        $base = $this->loadJson($basePath);
        if ($base === null) {
            return 1;
        }

        // 2. 合并 paths
        $pathsDir = $sourceDir . '/paths';
        $paths = [];
        if (File::isDirectory($pathsDir)) {
            $pathFiles = File::glob($pathsDir . '/*.json');
            foreach ($pathFiles as $file) {
                $tagPaths = $this->loadJson($file);
                if ($tagPaths !== null) {
                    $paths = array_merge($paths, $tagPaths);
                }
            }
        }
        ksort($paths);

        // 3. 加载 components
        $components = [];
        $componentsPath = $sourceDir . '/components.json';
        if (File::exists($componentsPath)) {
            $components = $this->loadJson($componentsPath) ?? [];
        }

        // 4. 合并
        $spec = array_merge($base, [
            'paths' => $paths,
            'components' => $components,
        ]);

        $json = json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        File::put($output, $json . "\n");

        $this->info("合并完成: {$output}");
        $this->line("共 " . count($paths) . " 个 paths");

        return 0;
    }

    protected function loadJson(string $path): ?array
    {
        $json = File::get($path);
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("JSON 解析失败 [{$path}]: " . json_last_error_msg());
            return null;
        }
        return $data;
    }
}
