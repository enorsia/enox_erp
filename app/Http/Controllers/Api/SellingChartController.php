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
            $perPage = $request->per_page ?? 30;

            $all_histories = SellingChartDiscountHistory::get();
            $platforms =  $all_histories->map(fn($item) => json_decode($item->items, true))
                ->pluck('platform')
                ->unique()
                ->values();

            $all_count = $all_histories->count() ?? 0;
            $applied_count = $all_histories->where('status', 1)->count() ?? 0;
            $pending_count = $all_histories->where('status', 0)->count() ?? 0;

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

            $discountHistories = $query->orderBy('id', 'desc')
                ->paginate($perPage);

            $response = [
                'status' => true,
                'data' => $discountHistories->items(),
                'platforms' => $platforms,
                'total_count' => [
                    'all' => $all_count,
                    'applied' => $applied_count,
                    'pending' => $pending_count,
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
            'ids' => 'required|array'
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
                    'status' => 1,
                    'updated_by' => $request->user_name ?? null
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
