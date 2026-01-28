<?php

namespace App\Http\Controllers;

use App\Models\SellingChartExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SellingChartExpenseController extends Controller
{
    public function index(Request $request)
    {
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

            SellingChartExpense::create([
                'year' => $request->year,
                'conversion_rate' => $request->conversion_rate,
                'commercial_expense' => $request->commercial_expense,
                'enorsia_expense_bd' => $request->enorsia_expense_bd,
                'enorsia_expense_uk' => $request->enorsia_expense_uk,
                'shipping_cost' => $request->shipping_cost,
                'status' => $request->filled('status'),
            ]);

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

            SellingChartExpense::findOrFail($id)->update([
                'year' => $request->year,
                'conversion_rate' => $request->conversion_rate,
                'commercial_expense' => $request->commercial_expense,
                'enorsia_expense_bd' => $request->enorsia_expense_bd,
                'enorsia_expense_uk' => $request->enorsia_expense_uk,
                'shipping_cost' => $request->shipping_cost,
                'status' => $request->filled('status'),
            ]);

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
        try {
            SellingChartExpense::findOrFail($id)->delete();
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
