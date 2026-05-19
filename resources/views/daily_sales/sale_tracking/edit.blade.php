@extends('layouts.app')

@section('title', 'Edit Sale Tracking — ' . \Carbon\Carbon::parse($month)->format('F Y'))

@section('content')
<div id="sale-tracking-page"></div>
<div class="px-5 py-6 pb-28">

    <!-- PAGE HEADER -->
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Sale Tracking</h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                Editing all entries for
                <strong class="text-slate-600 dark:text-slate-300">{{ \Carbon\Carbon::parse($month)->format('F Y') }}</strong>
            </p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <p class="text-xs font-semibold text-red-600 dark:text-red-400 mb-1">Please fix the following errors:</p>
        <ul class="text-sm text-red-600 dark:text-red-400 space-y-1">
            @foreach($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.sale-tracking.update', $saleTracking->id) }}" id="stForm">
        @csrf @method('PUT')

        <!-- Hidden month — locked to original record's month -->
        <input type="hidden" name="month" value="{{ $month }}" />

        <!-- Container for delete IDs -->
        <div id="delete-ids-container"></div>

        <!-- ── MONTH ROW (read-only display) ── -->
        <div class="section-card !mb-4">
            <div class="flex flex-wrap items-end gap-5">
                <div class="w-56">
                    <label class="f-label">Month</label>
                    <input type="text" class="f-input bg-slate-50 dark:bg-slate-700/50 cursor-default"
                           value="{{ \Carbon\Carbon::parse($month)->format('F Y') }}" readonly />
                </div>
                <div class="text-xs text-slate-400 dark:text-slate-500 pb-1.5">
                    <span class="font-medium text-slate-500 dark:text-slate-400">Record #{{ $saleTracking->id }}</span>
                    &nbsp;·&nbsp;
                    <span class="font-medium text-slate-500 dark:text-slate-400">Updated:</span>
                    {{ $saleTracking->updated_at ? $saleTracking->updated_at->diffForHumans() : 'N/A' }}
                </div>
            </div>
        </div>

        <!-- ── ENTRIES CONTAINER ── -->
        <div id="entries-container" class="space-y-2"></div>

        <!-- ── ADD MORE BUTTON ── -->
        <div class="mt-3 flex items-center gap-3">
            <button type="button" id="add-more-btn" class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl border border-dashed border-accent-400 text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/20 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Platform
            </button>
            <span class="text-xs text-slate-400 dark:text-slate-500" id="row-count-label"></span>
        </div>

        <!-- ── STICKY FOOTER ── -->
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="px-5 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Fields marked <span class="text-red-400 mx-1">*</span> are required · Total Cost, Net Revenue, ROI, ROAS are auto-computed
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.sale-tracking.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                            class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                        Update Records
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('js')
<script>
window.ST = {
    mode      : 'edit',
    platforms : @json($salePlatforms),
    entries   : @json(array_values(old('entries', $existingEntries))),
    deleteIds : @json(array_values(old('entries_delete', []))),
};
</script>
@endpush

