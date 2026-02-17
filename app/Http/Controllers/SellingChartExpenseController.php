<?php

namespace App\Http\Controllers;

use App\Models\SellingChartExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SellingChartExpenseController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('general.expense.index');
        $year = $request->input('year');
        $query = SellingChartExpense::query();

        $query->when($year, function ($query, $year) {
            return $query->where('year', 'like', "%{$year}%");
        });

        $data['expenses'] = $query->orderBy('created_at', 'desc')->paginate(10);
        $data['start'] = ($data['expenses']->currentPage() - 1) * $data['expenses']->perPage() + 1;

        return view('selling_chart.expense.index', $data);
    }

    public function create()
    {
        Gate::authorize('general.expense.create');
        return view('selling_chart.expense.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|string|max:4',
            'conversion_rate' => 'required|numeric',
            'commercial_expense' => 'required|numeric',
            'enorsia_expense_bd' => 'required|numeric',
            'enorsia_expense_uk' => 'required|numeric',
            'shipping_cost' => 'nullable|numeric',
        ]);

        try {
            $exp = SellingChartExpense::where('year', $request->year)->first();

            if ($exp) {
                notify()->error('Expense exist for this year', 'Error');
                return redirect()->route('admin.selling_chart.expense.index');
            }

            $expense = SellingChartExpense::create([
                'year' => $request->year,
                'conversion_rate' => $request->conversion_rate,
                'commercial_expense' => $request->commercial_expense,
                'enorsia_expense_bd' => $request->enorsia_expense_bd,
                'enorsia_expense_uk' => $request->enorsia_expense_uk,
                'shipping_cost' => $request->shipping_cost,
                'status' => $request->filled('status'),
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($expense)
                ->withProperties([
                    'year' => $expense->year,
                    'conversion_rate' => $expense->conversion_rate
                ])
                ->log('Created expense configuration for year ' . $expense->year . ' (Conversion Rate: ' . $expense->conversion_rate . ')');

            notify()->success('Expense created successfully', 'Success');
            return redirect()->route('admin.selling_chart.expense.index');
        } catch (\Throwable $th) {
            notify()->error('Expense creation failed', 'Error');
            Log::error('SellingChartExpense creation failed', [
                'message'   => $th->getMessage()
            ]);
            return redirect()->route('admin.selling_chart.expense.index');
        }
    }


    public function edit(int | string $id)
    {
        Gate::authorize('general.expense.edit');
        Session::put('backUrl', url()->previous());
        $expense = SellingChartExpense::findOrFail($id);
        return view('selling_chart.expense.edit', compact('expense'));
    }

    public function update(Request $request, int | string $id)
    {
        $request->validate([
            'year' => 'required|string|max:4',
            'conversion_rate' => 'required|numeric',
            'commercial_expense' => 'required|numeric',
            'enorsia_expense_bd' => 'required|numeric',
            'enorsia_expense_uk' => 'required|numeric',
            'shipping_cost' => 'nullable|numeric',
        ]);

        try {
            $exists = SellingChartExpense::where('year', $request->year)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                notify()->error('Expense already exists for this year', 'Error');
                return back();
            }

            $expense = SellingChartExpense::findOrFail($id);

            // Capture old values
            $oldValues = [
                'year' => $expense->year,
                'conversion_rate' => $expense->conversion_rate,
                'commercial_expense' => $expense->commercial_expense,
                'enorsia_expense_bd' => $expense->enorsia_expense_bd,
                'enorsia_expense_uk' => $expense->enorsia_expense_uk,
                'shipping_cost' => $expense->shipping_cost,
                'status' => $expense->status,
            ];

            $expense->update([
                'year' => $request->year,
                'conversion_rate' => $request->conversion_rate,
                'commercial_expense' => $request->commercial_expense,
                'enorsia_expense_bd' => $request->enorsia_expense_bd,
                'enorsia_expense_uk' => $request->enorsia_expense_uk,
                'shipping_cost' => $request->shipping_cost,
                'status' => $request->filled('status'),
            ]);

            // Capture new values
            $expense->refresh();
            $newValues = [
                'year' => $expense->year,
                'conversion_rate' => $expense->conversion_rate,
                'commercial_expense' => $expense->commercial_expense,
                'enorsia_expense_bd' => $expense->enorsia_expense_bd,
                'enorsia_expense_uk' => $expense->enorsia_expense_uk,
                'shipping_cost' => $expense->shipping_cost,
                'status' => $expense->status,
            ];

            // Detect changes
            $changes = [];
            foreach ($oldValues as $key => $oldValue) {
                if ($oldValue != $newValues[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValues[$key]
                    ];
                }
            }

            if (count($changes) > 0) {
                $changedFields = array_keys($changes);
                $description = 'Updated expense settings for year ' . $expense->year . ' (Changed: ' . implode(', ', array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $changedFields)) . ')';

                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($expense)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log($description);
            }

            notify()->success('Expense updated successfully', 'Success');
            return redirect(session('backUrl'));
        } catch (\Throwable $th) {
            notify()->error("Expense update failed.", "Error");
            Log::error('SellingChartExpense update failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }

    public function show($id) {}


    public function destroy(int | string $id): RedirectResponse
    {
        Gate::authorize('general.expense.delete');
        try {
            $expense = SellingChartExpense::findOrFail($id);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($expense)
                ->withProperties([
                    'year' => $expense->year,
                    'conversion_rate' => $expense->conversion_rate
                ])
                ->log('Deleted expense configuration for year ' . $expense->year);

            $expense->delete();
            notify()->success("Expense deleted successfully.", "Success");
            return back();
        } catch (\Throwable $th) {
            notify()->error("Expense deletion failed.", "Error");
            Log::error('SellingChartExpense delete failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }
}
