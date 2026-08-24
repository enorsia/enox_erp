<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellingChartDiscountHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellingChartController extends Controller
{
    public function getDiscountHistories(Request $request)
    {
        try {
            $search = $request->search;
            $platform = $request->platform;
            $status = $request->status;
            $action = $request->action;
            $perPage = $request->per_page ?? 30;

            $query = SellingChartDiscountHistory::query();
            $query->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('items->style', 'like', "%{$search}%")
                        ->orWhere('items->product_code', 'like', "%{$search}%");
                });
            })
                ->when($platform, function ($q) use ($platform) {
                    $q->where('items->platform', 'like', "%{$platform}%");
                })
                ->when($status !== null && $status !== '', function ($q) use ($status) {
                    $q->where('status', $status);
                });

            if ($action == 'export') {
                $response = [
                    'status' => true,
                    'message' => 'Selling chart discount histories fetch successfully.',
                    'data' => $query->get()
                ];
                return response()->json($response);
            }

            $platforms = SellingChartDiscountHistory::query()
                ->select('items')
                ->get()
                ->map(fn($item) => json_decode($item->items, true)['platform'] ?? null)
                ->filter()
                ->unique()
                ->values();

            $allCount = (clone $query)->count();

            $pendingCount = (clone $query)
                ->where('status', 0)
                ->count();

            $appliedCount = (clone $query)
                ->where('status', 1)
                ->count();

            $ignoreCount = (clone $query)
                ->where('status', 2)
                ->count();

            $discountHistories = $query->orderBy('id', 'desc')
                ->paginate($perPage);

            $response = [
                'status' => true,
                'message' => 'Selling chart discount histories fetch successfully.',
                'data' => $discountHistories->items(),
                'platforms' => $platforms,
                'total_count' => [
                    'all' => $allCount,
                    'applied' => $appliedCount,
                    'pending' => $pendingCount,
                    'ignore' => $ignoreCount,
                ],
                'pagination' => [
                    'current_page' => $discountHistories->currentPage(),
                    'per_page' => $discountHistories->perPage(),
                    'total' => $discountHistories->total(),
                    'last_page' => $discountHistories->lastPage(),
                    'start' => ($discountHistories->currentPage() - 1) * $discountHistories->perPage() + 1
                ]
            ];
            return response()->json($response);
        } catch (\Throwable $th) {
            Log::error('Selling chart discount histories fetch failed', ['error' => $th->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Selling chart discount histories fetch failed',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updateDiscountHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'user_name' => 'required|string',
            'status' => 'required|in:1,2',
        ]);

        if ($validator->fails()) {
            Log::error('Selling chart discount histories update failed', ['errors' => $validator->errors()]);
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            SellingChartDiscountHistory::whereIn('id', $request->ids)->update(
                [
                    'status' => $request->status,
                    'updated_by' => $request->user_name
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Selling chart discount histories updated successfully',
                'data' => $request->all()
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Selling chart discount histories update failed', ['error' => $th->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
