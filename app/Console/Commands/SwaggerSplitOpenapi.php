<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SwaggerSplitOpenapi extends Command
{
    protected $signature = 'swagger:split
                            {--source= : openapi.json 路径，默认 resources/swagger/openapi.json}
                            {--output= : 输出目录，默认 resources/swagger/split}';

    protected $description = '将 openapi.json 按 tag 分割成多个小文件，便于编辑和定位错误';

    public function handle(): int
    {
        $source = $this->option('source') ?: resource_path('swagger/openapi.json');
        $outputDir = $this->option('output') ?: resource_path('swagger/split');

        if (!File::exists($source)) {
            $this->error("源文件不存在: {$source}");
            return 1;
        }

        $json = File::get($source);
        $spec = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON 解析失败: ' . json_last_error_msg());
            return 1;
        }

        File::ensureDirectoryExists($outputDir);
        File::ensureDirectoryExists($outputDir . '/paths');

        // 1. 保存 base（info, servers, tags）
        $base = [
            'openapi' => $spec['openapi'] ?? '3.0.0',
            'info' => $spec['info'] ?? [],
            'servers' => $spec['servers'] ?? [],
            'tags' => $spec['tags'] ?? [],
        ];
        $this->writeJson($outputDir . '/base.json', $base);
        $this->line("✓ base.json");

        // 2. 按 tag 分割 paths
        $paths = $spec['paths'] ?? [];
        $pathsByTag = [];
        $untaggedPaths = [];

        foreach ($paths as $path => $pathItem) {
            $tags = $this->extractTags($pathItem);
            if (empty($tags)) {
                $untaggedPaths[$path] = $pathItem;
                continue;
            }
            $primaryTag = $tags[0];
            if (!isset($pathsByTag[$primaryTag])) {
                $pathsByTag[$primaryTag] = [];
            }
            $pathsByTag[$primaryTag][$path] = $pathItem;
        }

        foreach ($pathsByTag as $tag => $tagPaths) {
            $filename = $outputDir . '/paths/' . $tag . '.json';
            $this->writeJson($filename, $tagPaths);
            $this->line("✓ paths/{$tag}.json (" . count($tagPaths) . " paths)");
        }

        if (!empty($untaggedPaths)) {
            $this->writeJson($outputDir . '/paths/_untagged.json', $untaggedPaths);
            $this->line("✓ paths/_untagged.json (" . count($untaggedPaths) . " paths)");
        }

        // 3. 保存 components
        if (!empty($spec['components'])) {
            $this->writeJson($outputDir . '/components.json', $spec['components']);
            $this->line("✓ components.json");
        }

        $this->newLine();
        $this->info("分割完成，输出目录: {$outputDir}");
        $this->line("使用 php artisan swagger:merge 合并回 openapi.json");

        return 0;
    }

    protected function extractTags(array $pathItem): array
    {
        foreach (['get', 'post', 'put', 'patch', 'delete', 'options', 'head'] as $method) {
            if (isset($pathItem[$method]['tags']) && is_array($pathItem[$method]['tags'])) {
                return $pathItem[$method]['tags'];
            }
        }
        return [];
    }

    protected function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        File::put($path, $json . "\n");
    }
}
