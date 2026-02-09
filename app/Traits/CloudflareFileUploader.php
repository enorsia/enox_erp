<?php
namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


trait CloudflareFileUploader {

    public function uploadImage(string $path) : array
    {
        try {

            $authToken = config('cloudflare.auth_token');
            $accountId = config('cloudflare.account_id');
            $publicPath = public_path($path);

            if (!file_exists($publicPath)) {
                Log::warning('CLOUDFLARE: Image upload skipped - file not found', [
                    'path' => $path,
                ]);
                return [
                    'success' => false,
                    'message' => 'CLAUDFLARE: Image upload skipped - file not found',
                ];
            }

            $imageInfo = pathinfo($publicPath);
            $dirName   = $imageInfo['dirname'] ?? null;
            $fileName  = $imageInfo['basename'];
            $mimeType  = mime_content_type($publicPath) ?: 'application/octet-stream';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$authToken,
            ])->attach(
                'file', file_get_contents($publicPath), $fileName, [
                    'Content-Type' => $mimeType,
                ]
            )->post("https://api.cloudflare.com/client/v4/accounts/$accountId/images/v1", [
                'id' => $fileName
            ]);

            if ($response->successful()) {
                $json = $response->json();

                if (!empty($json['success']) && !empty($json['result']['id'])) {
                    return [
                        'success'        => true,
                        'imageId'        => $json['result']['id'],
                        'original_name'  => $fileName,
                        'original_path'  => $dirName === '.' ? null : str_replace(public_path(), '', $dirName),
                    ];
                }
            }
            return [
                'success' => false,
                'message' => $response->json('errors.0.message')
                    ?? 'CLOUDFLARE: Image upload failed',
            ];

        }catch(\Throwable $e){
            Log::error('CLOUDFLARE: Image upload API request failed', [
                'path'      => $path,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'CLOUDFLARE: Image upload API request failed',
            ];
        }

    }

    public function deleteCfImage( string $imageName ) : array
    {
        try {
            $authToken = config('cloudflare.auth_token');
            $accountId = config('cloudflare.account_id');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$authToken,
            ])->delete("https://api.cloudflare.com/client/v4/accounts/$accountId/images/v1/" . $imageName);

            if($response->successful()){
                return ['success' => true];
            }
            return [
                'success' => false,
                'message' => $response->json('errors.0.message') ?? 'CLOUDFLARE: Image delete API request failed',
            ];
        }catch(\Throwable $e){
            Log::error('CLOUDFLARE: Image delete API request failed', [
                'image_id' => $imageName,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'CLOUDFLARE: Image delete API request failed',
            ];
        }
    }

    public function uploadCfVideo( string $path ) : array
    {
        try{
            $authToken = config('cloudflare.auth_token');
            $accountId = config('cloudflare.account_id');
            $publicPath = public_path($path);

            if (!file_exists($publicPath)) {
                Log::warning('CLOUDFLARE: Video upload skipped: file not found', [
                    'path' => $path,
                ]);
                return [
                    'success' => false,
                    'message' => 'CLOUDFLARE: Video upload skipped: file not found',
                ];
            }

            $imageInfo = pathinfo($publicPath);
            $dirName   = $imageInfo['dirname'] ?? null;
            $fileName  = $imageInfo['basename'];
            $mimeType  = mime_content_type($publicPath) ?: 'application/octet-stream';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$authToken,
            ])->attach(
                'file', file_get_contents($publicPath), $fileName, [
                    'Content-Type' => $mimeType,
                ]
            )->post("https://api.cloudflare.com/client/v4/accounts/$accountId/stream", [
                'uid' => $fileName
            ]);

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['success']) && !empty($json['result']['uid'])) {
                    return [
                        'success' => true,
                        'uid' => $json['result']['uid'],
                        'original_name' => $fileName,
                        'original_path' => $dirName == '.' ? null : $dirName
                    ];
                }
            }
            return [
                'success' => false,
                'message' => $response->json('errors.0.message')
                    ?? 'CLOUDFLARE: Video upload failed',
            ];

        }catch(\Throwable $e){
            Log::error('CLOUDFLARE: Video upload request failed', [
                'video_name' => $publicPath,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'CLOUDFLARE: Video upload request failed',
            ];
        }
    }

    public function deleteCfVideo( string $uid ) : array
    {
        try{
            $authToken = config('cloudflare.auth_token');
            $accountId = config('cloudflare.account_id');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$authToken,
            ])->delete("https://api.cloudflare.com/client/v4/accounts/$accountId/stream/" . $uid);

            if($response->successful()){
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => $response->json('errors.0.message')
                    ?? 'CLOUDFLARE: Video delete failed',
            ];

        }catch(\Throwable $e){
            Log::error('CLOUDFLARE: Video delete API request failed', [
                'video_id' => $uid,
                'exception' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'CLOUDFLARE: Video delete API request failed',
            ];
        }
    }
}
