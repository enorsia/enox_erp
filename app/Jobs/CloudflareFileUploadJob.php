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

class CloudflareFileUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CloudflareFileUploader;

    public $tries = 3;

    public $timeout = 1200;

    public function __construct(public string $path){}

    public function handle()
    {
        $result = $this->uploadImage($this->path);

        if ($result['success'] == true) {
            $this->jobSuccessRecord([
                'cloude_flare_id' => $result['imageId'],
                'original_name' => $result['original_name'],
                'original_path' => $result['original_path'],
            ]);
        } else {
            $this->fail($result['message']);
        }

        if ($this->attempts() % 5 === 0) {
            $this->release(2);
        }
    }

    protected function jobSuccessRecord( array $data ) : void
    {
        CloudFlareUploadedFile::create($data);
    }
}
