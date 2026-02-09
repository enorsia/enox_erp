<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\CloudflareFileUploader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloudflareVideoUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CloudflareFileUploader;

    public $tries = 1;
    public $maxExceptions = 1;
    public $timeout = 1680;
    public $failOnTimeout = true;
    public $backoff = 10;

    public function __construct(public string $id, public string $path){}

    public function handle()
    {

        try{
            $result = $this->uploadCfVideo($this->path);

            if (!empty($result['success'])) {
                $this->jobSuccessRecord([
                    'uid' => $result['uid'],
                    'product_id' => $this->id,
                ]);
                return;
            }
            Log::warning('CLOUDFLARE: Video upload failed', [
                'path'    => $this->path,
                'message' => $result['message'] ?? 'Unknown error',
            ]);
        }catch (\Throwable $e) {
            Log::critical('CLOUDFLARE: Video upload job crashed', [
                'path'      => $this->path,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function jobSuccessRecord( array $data ) : void
    {
        DB::table('cf_uploaded_videos')->insert(['product_id' => $data['product_id'], 'video_uid' => $data['uid']]);
    }
}
