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

class CloudflareVideoUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CloudflareFileUploader;

    public $tries = 3;

    public $timeout = 1680;

    public function __construct(public string $id, public string $path){}

    public function handle()
    {
        $result = $this->uploadCfVideo($this->path);
        if($result['success'] == true){
            $this->jobSuccessRecord([
                'uid' => $result['uid'],
                'product_id' => $this->id,
            ]);
        }else{
            $this->fail($result['message']);
        }
        if ($this->attempts() % 5 === 0) {
            $this->release(2);
        }
    }

    protected function jobSuccessRecord( array $data ) : void
    {
        DB::table('cf_uploaded_videos')->insert(['product_id' => $data['product_id'], 'video_uid' => $data['uid']]);
    }
}
