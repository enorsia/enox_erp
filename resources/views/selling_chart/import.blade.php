@extends('layouts.app')

@section('title', 'Import Selling Chart')

@section('content')
    {{-- Trigger selling-chart.js for import form validation --}}
    <div id="selling-chart-form-content"></div>

    <div class="max-w-2xl mx-auto px-5 py-6">

        {{-- PAGE HEADER --}}
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Import Selling Chart</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Upload an Excel sheet to import selling chart data
                </p>
            </div>
            <a href="{{ route('admin.selling_chart.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M15 19l-7-7 7-7" />
                </svg>
                Back to List
            </a>
        </div>

        {{-- Import error message --}}
        @if (session('import_msg'))
            <div
                class="mb-4 rounded-xl border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 px-4 py-3">
                <p class="text-[13px] font-semibold text-red-600 dark:text-red-400">{{ session('import_msg') }}</p>
                @if (session('in_value'))
                    <p class="text-[12px] text-red-500 dark:text-red-400 mt-1">{{ session('in_value') }}</p>
                @endif
            </div>
        @endif

        <form action="{{ route('admin.selling_chart.import') }}" method="POST"
            enctype="multipart/form-data" id="import_form">
            @csrf

            <div class="section-card">
                <div class="section-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload Excel File
                </div>
                <p class="section-desc">Select the Excel sheet containing the selling chart data to import.</p>

                <div>
                    <label class="f-label">Excel Sheet <span class="f-required">*</span></label>
                    <input type="file" name="sheet" required accept=".xlsx,.xls,.csv"
                        class="f-input @error('sheet') border-red-400 @enderror">
                    @error('sheet') <p class="f-error">{{ $message }}</p> @enderror
                    <p class="f-hint mt-1">Accepted formats: .xlsx, .xls, .csv</p>
                </div>
            </div>

            <div class="flex justify-end gap-2.5 mt-4">
                <a href="{{ route('admin.selling_chart.index') }}"
                    class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                    class="submit-btn inline-flex items-center gap-2 px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Import
                </button>
            </div>
        </form>
    </div>
@endsection
