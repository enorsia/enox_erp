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

class CloudflareFileDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CloudflareFileUploader;

    public $tries = 3;

    public $timeout = 1200;

    public function __construct(public string $imageName){}

    public function handle()
    {
        $result = $this->deleteCfImage($this->imageName);
        if($result['success'] == true){
            $this->recordDeleteImage([
                'image_id' => $this->imageName,
            ]);
        }else{
            $this->fail($result['message']);
        }
    }

    protected function recordDeleteImage( array $data ) : void
    {
        CloudFlareDeletedImage::create($data);
    }
}
