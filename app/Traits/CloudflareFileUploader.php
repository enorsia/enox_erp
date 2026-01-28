<?php
namespace App\Traits;

use App\Models\EcommerceProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait CloudflareFileUploader {

    public function uploadImage(string $path) : array
    {
        $authToken = config('cloudflare.auth_token');
        $accountId = config('cloudflare.account_id');

        $dirName = pathinfo($path)['dirname'];
        $path = public_path($path);
        $imageInfo = pathinfo($path);
        $fullImageName = $imageInfo['basename'];
        $fileExtension = $imageInfo['extension'];
        $contentType =  mime_content_type($path);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$authToken,
        ])->attach(
            'file', file_get_contents($path), $fullImageName, [
                'Content-Type' => $contentType,
            ]
        )->post("https://api.cloudflare.com/client/v4/accounts/$accountId/images/v1", [
            'id' => $fullImageName
        ]);

        if($response->successful()){
            $result = $response->json();
            if($result['success'] == true){
                return [
                    'success' => true,
                    'imageId' => $result['result']['id'],
                    'original_name' => $fullImageName,
                    'original_path' => $dirName == '.' ? null : $dirName
                ];
            }
        }else{
            $error = $response->json();
            return [
                'success' => false,
                'message' => $error['errors'][0]['message']
            ];
        }

    }

    public function deleteCfImage( string $imageName ) : array
    {
        $authToken = config('cloudflare.auth_token');
        $accountId = config('cloudflare.account_id');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$authToken,
        ])->delete("https://api.cloudflare.com/client/v4/accounts/$accountId/images/v1/" . $imageName);

        if($response->successful()){
            $result = $response->json();
            return ['success' => true];
        }else{
            $result = $response->json();
            return [
                'success' => false,
                'message' => $result['errors'][0]['message']
            ];
        }

    }

    public function uploadCfVideo( string $path ) : array
    {
        $authToken = config('cloudflare.auth_token');
        $accountId = config('cloudflare.account_id');

        $dirName = pathinfo($path)['dirname'];
        $path = public_path($path);
        $imageInfo = pathinfo($path);
        $fullImageName = $imageInfo['basename'];
        $fileExtension = $imageInfo['extension'];
        $contentType =  mime_content_type($path);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$authToken,
        ])->attach(
            'file', file_get_contents($path), $fullImageName, [
                'Content-Type' => $contentType,
            ]
        )->post("https://api.cloudflare.com/client/v4/accounts/$accountId/stream", [
            'uid' => $fullImageName
        ]);

        if($response->successful()){
            $result = $response->json();
            if($result['success'] == true){
                return [
                    'success' => true,
                    'uid' => $result['result']['uid'],
                    'original_name' => $fullImageName,
                    'original_path' => $dirName == '.' ? null : $dirName
                ];
            }
        }else{
            $error = $response->json();
            return [
                'success' => false,
                'message' => $error['errors'][0]['message']
            ];
        }
    }

    public function deleteCfVideo( string $uid ) : array
    {
        $authToken = config('cloudflare.auth_token');
        $accountId = config('cloudflare.account_id');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$authToken,
        ])->delete("https://api.cloudflare.com/client/v4/accounts/$accountId/stream/" . $uid);

        if($response->successful()){
            $result = $response->json();
            return ['success' => true];
        }else{
            $result = $response->json();
            return [
                'success' => false,
                'message' => $result['errors'][0]['message']
            ];
        }
    }


    public function uploadExistingImageToCloudFlare() : array
    {
        $files = [];
        $directory = 'upload';
        $basePath = $directory . '/';
        $directoryPath = public_path($directory);
        $contents = File::allFiles($directoryPath);

        foreach ($contents as $file) {
            $relativePath = Str::replaceFirst($directoryPath, '', $file);
            if (is_dir($file)) {
                $subDirectory = $basePath . $relativePath;
                $files = array_merge($files, listImageAndGifFiles($relativePath, $subDirectory));
            } else {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $files[] = $basePath . ltrim(str_replace('\\', '/', $relativePath), '/');
                }
            }
        }
        return $files;
    }


}
