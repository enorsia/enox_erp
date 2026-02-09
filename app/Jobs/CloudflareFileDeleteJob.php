<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\CloudflareFileUploader;
use App\Models\CloudFlareDeletedImage;
use Illuminate\Support\Facades\Log;

class CloudflareFileDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CloudflareFileUploader;

    public $tries = 1;
    public $maxExceptions = 1;
    public $timeout = 1200;
    public $failOnTimeout = true;
    public $backoff = 10;

    public function __construct(public string $imageName){}

    public function handle()
    {
        try {
            $result = $this->deleteCfImage($this->imageName);
            if (isset($result['success']) && $result['success']){
                $this->recordDeleteImage(['image_id' => $this->imageName]);
            }else{
                Log::warning('CLOUDFLARE: Image delete failed', [
                    'image_id' => $this->imageName,
                    'error'    => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CLOUDFLARE: Image delete job crashed', [
                'image_id' => $this->imageName,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function recordDeleteImage( array $data ) : void
    {
        CloudFlareDeletedImage::create($data);
    }
}
