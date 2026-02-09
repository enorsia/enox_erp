<?php

namespace App\Jobs;

use App\Models\CloudFlareUploadedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\CloudflareFileUploader;
use Illuminate\Support\Facades\Log;

class CloudflareFileUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CloudflareFileUploader;

    public $tries = 1;
    public $maxExceptions = 1;
    public $timeout = 1200;
    public $failOnTimeout = true;
    public $backoff = 10;

    public function __construct(public string $path){}

    public function handle()
    {
        try{
            $result = $this->uploadImage($this->path);

            if (!empty($result['success'])) {
                $this->jobSuccessRecord([
                    'cloude_flare_id' => $result['imageId'],
                    'original_name' => $result['original_name'],
                    'original_path' => $result['original_path'],
                ]);
                return;
            }
            Log::warning('CLOUDFLARE: Cloudflare image upload failed', [
                'path'    => $this->path,
                'message' => $result['message'] ?? 'Unknown error',
            ]);
        }catch (\Throwable $e) {
            Log::critical('CLOUDFLARE: Cloudflare upload job crashed', [
                'path'      => $this->path,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function jobSuccessRecord( array $data ) : void
    {
        CloudFlareUploadedFile::create($data);
    }
}
