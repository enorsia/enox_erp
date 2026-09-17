<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\UserExport;
use App\Services\EcomActivityExportQuery;
use App\Services\Exports\Async\AsyncEcomActivityExportService;
use App\Support\ExportLogger;
use App\Support\ExportTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserExportController extends Controller
{
    public function __construct(
        private AsyncEcomActivityExportService $activityExportService,
        private EcomActivityExportQuery $exportQuery,
    ) {}

    public function startEcomActivityReport(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.activity.index');

        $validated = $request->validate([
            'format' => 'required|in:xlsx,csv',
            'query' => 'nullable|array',
        ]);

        $queryParams = collect($validated['query'] ?? $request->except(['format', 'query']))
            ->except(['page', 'fragment'])
            ->all();

        $queryParams = $this->exportQuery->normalizeQueryParams($queryParams);

        $range = $this->exportQuery->resolveRange($queryParams);

        $filters = [
            'query' => $queryParams,
            'range_label' => $range['label'],
        ];

        if (session()->isStarted()) {
            session()->save();
        }

        $export = $this->activityExportService->start(
            $request->user()->id,
            $filters,
            $validated['format'],
        );

        ExportLogger::info('Ecom activity export requested', [
            'export_id' => $export->id,
            'user_id' => $request->user()->id,
            'format' => $validated['format'],
        ]);

        return response()->json([
            'export_id' => $export->id,
            'status' => $export->status,
            'total_rows' => $export->total_rows,
        ], 202);
    }

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
            ? $this->activityExportService->cancel($export)
            : $export->dismiss();

        ExportLogger::info('Export dismissed/cancelled', [
            'export_id' => $export->id,
            'user_id' => auth()->id(),
            'status' => $export->status,
        ]);

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
