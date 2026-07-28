<?php

namespace App\Http\Controllers;

use App\Services\StyleStockReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StyleStockReportController extends Controller
{
    public function __construct(
        protected StyleStockReportService $service,
    ) {}

    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if ($request->input('action') === 'export_stock_analysis') {
            return $this->export($request);
        }

        $result = $this->service->getReport();

        if (! $result['success']) {
            notify()->error($result['error'], 'Error');
        }

        return view('style_stocks.index', [
            'style_stocks' => $result['style_stocks'],
            'load_error' => $result['error'],
        ]);
    }

    protected function export(Request $request): RedirectResponse|StreamedResponse
    {
        Gate::authorize('admin.style_alert_stocks.excel_export');

        $result = $this->service->exportReport($request->except(['_token']));

        if (! $result['success'] || $result['body'] === null) {
            notify()->error($result['error'] ?? 'Export failed.', 'Error');

            return redirect()->route('admin.style.stock.index');
        }

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['body'];
            },
            $result['filename'],
            [
                'Content-Type' => $result['content_type'],
            ],
        );
    }
}
