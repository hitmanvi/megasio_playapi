<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UtilsController extends Controller
{
    public function timestamp()
    {
        return $this->responseItem([
            'timestamp' => time(),
        ]);
    }

    /**
     * 获取应用设置
     */
    public function settings()
    {
        $settingService = new SettingService();
        return $this->responseItem($settingService->getGroup('app'));
    }

    /**
     * 上传图片到 S3
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $file = $request->file('file');
        
        // 生成唯一文件名
        $extension = $file->getClientOriginalExtension();
        $path = 'uploads/' . date('Ymd') . '/' . Str::uuid() . '.' . $extension;

        try {
            // 上传到 S3
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $uploaded = $disk->put($path, file_get_contents($file), 'public');
            
            if (!$uploaded) {
                return $this->error(ErrorCode::INTERNAL_ERROR, 'Failed to upload file');
            }

            // 获取文件 URL
            $url = $disk->url($path);

            return $this->responseItem([
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return $this->error(ErrorCode::INTERNAL_ERROR, 'Upload failed: ' . $e->getMessage());
        }
    }
}