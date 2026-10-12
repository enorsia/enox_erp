<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\UserExport;
use App\Support\ExportTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserExportController extends Controller
{
    public function status(UserExport $export): JsonResponse
    {
        $this->authorizeExport($export);
        $this->releaseSession();

        return response()->json($export->toFrontendArray());
    }

    public function download(Request $request, UserExport $export): StreamedResponse
    {
        $this->authorizeExport($export);
        $this->releaseSession();

        if (! $request->hasValidSignature()) {
            abort(403, 'Download link has expired.');
        }

        if (! $export->isDownloadReady()) {
            abort(404, 'Export file is not available.');
        }

        $filename = $export->downloadDisplayFilename();
        $mimeType = $export->format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return Storage::download($export->file_path, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    public function cancel(UserExport $export): JsonResponse
    {
        $this->authorizeExport($export);
        $this->releaseSession();

        if ($export->status === UserExport::STATUS_CANCELLED) {
            return response()->json([
                'export_id' => $export->id,
                'status' => $export->status,
            ]);
        }

        $export = $export->isActive()
            ? $export->dismiss()
            : $export->dismiss();

        return response()->json([
            'export_id' => $export->id,
            'status' => $export->status,
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $this->releaseSession();

        $type = $request->query('type');

        if ($type && ! ExportTypeRegistry::exists($type)) {
            return response()->json(['export' => null]);
        }

        $export = UserExport::getActiveForUser($request->user()->id, $type);

        if (! $export) {
            return response()->json(['export' => null]);
        }

        return response()->json([
            'export' => $export->toFrontendArray(),
        ]);
    }

    private function authorizeExport(UserExport $export): void
    {
        $user = request()->user();

        if (! $user || $export->user_id !== $user->id) {
            abort(403);
        }

        $permission = ExportTypeRegistry::permission($export->type);
        if ($permission && ! $user->can($permission)) {
            abort(403);
        }
    }

    private function releaseSession(): void
    {
        if (session()->isStarted()) {
            session()->save();
        }
    }
}
