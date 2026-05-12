@extends('layouts.app')

@section('title', 'Return Reason Types')

@section('content')
<div id="return-reason-type-page-content"></div>

<div x-data="{
    drawerOpen: false,
    exportOpen: false,
    exportCols: @js(\App\Exports\ReturnReasonTypeExport::allColumns()),
    selectedCols: @js(\App\Exports\ReturnReasonTypeExport::allColumns()),
    toggleAll(checked) { this.selectedCols = checked ? [...this.exportCols] : []; }
}" @keydown.escape.window="drawerOpen = false; exportOpen = false">

    {{-- Backdrop --}}
    <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="drawerOpen = false" class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

    {{-- Drawer Panel --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
         style="display:none;">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                Filters
            </div>
            <button @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="get" action="{{ route('admin.return-reason-types.index') }}" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Search</p>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" name="search" placeholder="Search by name or slug…" value="{{ request('search') }}"
                               class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors"/>
                    </div>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Status</p>
                    <select name="is_active" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Status">
                        <option value="">All Status</option>
                        <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                <a href="{{ route('admin.return-reason-types.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
                <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Apply Filters</button>
            </div>
        </form>
    </div>

    {{-- Export Modal --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-700"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Return Reason Types</h3>
                </div>
                <button @click="exportOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[12px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Select Columns</p>
                    <div class="flex gap-2">
                        <button type="button" @click="toggleAll(true)" class="text-[11px] text-accent-500 hover:text-accent-700 font-medium">All</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" @click="toggleAll(false)" class="text-[11px] text-slate-400 hover:text-slate-600 font-medium">None</button>
                    </div>
                </div>
                @php $exportLabels = \App\Exports\ReturnReasonTypeExport::columnLabels(); $exportCols = \App\Exports\ReturnReasonTypeExport::allColumns(); @endphp
                <div class="grid grid-cols-2 gap-2">
                    @foreach($exportCols as $col)
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
                            <input type="checkbox" :checked="selectedCols.includes('{{ $col }}')"
                                   @change="selectedCols.includes('{{ $col }}') ? selectedCols.splice(selectedCols.indexOf('{{ $col }}'), 1) : selectedCols.push('{{ $col }}')"
                                   class="w-3.5 h-3.5 rounded text-accent-400 border-slate-300 focus:ring-accent-400">
                            <span class="text-[12px] text-slate-600 dark:text-slate-300">{{ $exportLabels[$col] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2.5 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                <button @click="exportOpen = false" class="flex-1 py-2.5 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 transition-colors font-medium text-center">Cancel</button>
                <a :href="'{{ route('admin.return-reason-types.export') }}?' + new URLSearchParams(Object.assign({}, Object.fromEntries(new URLSearchParams('{{ http_build_query(request()->except('page')) }}')), {columns: selectedCols.join(',')})).toString()"
                   class="flex-[2] py-2.5 text-[13px] rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition-colors text-center flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </a>
            </div>
        </div>
    </div>

    <div class="p-5 lg:p-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Return Reason Types</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all return reason types and their settings</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="exportOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
                @php $activeFilters = collect([request('search'), request('is_active') !== null && request('is_active') !== '' ? request('is_active') : null])->filter()->count(); @endphp
                <button type="button" @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilters > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($activeFilters > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-semibold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilters }}</span>
                    @endif
                </button>
                @can('general.return_reason_type.create')
                    <a href="{{ route('admin.return-reason-types.create') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Create Return Reason Type
                    </a>
                @endcan
            </div>
        </div>

        {{-- Active filter tags --}}
        @if(request('search') || (request('is_active') !== null && request('is_active') !== ''))
            <div class="flex flex-wrap gap-2 mb-4">
                @if(request('search'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Search:</span> {{ request('search') }}
                        <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('is_active') !== null && request('is_active') !== '')
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Status:</span> {{ request('is_active') == '1' ? 'Active' : 'Inactive' }}
                        <a href="{{ request()->fullUrlWithQuery(['is_active' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                <a href="{{ route('admin.return-reason-types.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
                </a>
            </div>
        @endif

        {{-- Cards List --}}
        <div class="flex flex-col gap-3">
            @if (!$reasonTypes->isEmpty())
                @foreach ($reasonTypes as $key => $reasonType)
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-[1fr_auto] gap-3 items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $reasonType->name ?? '' }}</span>
                                @if ($reasonType->is_active)
                                    <span class="badge-custom badge-green">Active</span>
                                @else
                                    <span class="badge-custom badge-red">Inactive</span>
                                @endif
                            </div>
                            <p class="text-[12px] text-slate-400 dark:text-slate-500">Slug: {{ $reasonType->slug ?? '-' }}</p>
                            @if ($reasonType->description)
                                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">{{ strlen($reasonType->description) > 100 ? substr($reasonType->description, 0, 100) . '...' : $reasonType->description }}</p>
                            @endif
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                Sort Order: {{ $reasonType->sort_order ?? 0 }} • Created {{ $reasonType->created_at ? $reasonType->created_at?->diffForHumans() : '' }}
                            </p>
                        </div>
                        <div class="flex gap-1 flex-shrink-0">
                            @can('general.return_reason_type.show')
                                <a href="{{ route('admin.return-reason-types.show', $reasonType->id) }}" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors" title="View">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            @endcan
                            @can('general.return_reason_type.edit')
                                <a href="{{ route('admin.return-reason-types.edit', $reasonType->id) }}" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            @endcan
                            @can('general.return_reason_type.delete')
                                <button onclick="deleteData({{ $reasonType->id }})" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors" title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                <form id="delete-form-{{ $reasonType->id }}" method="POST" action="{{ route('admin.return-reason-types.destroy', $reasonType->id) }}" style="display:none;">
                                    @csrf @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No return reason types found.</p>
                </div>
            @endif
        </div>

        @include('layouts.pagination', ['paginator' => $reasonTypes])
    </div>
</div>
@endsection

@push('scripts')
<script>
    function deleteData(id) {
        if (confirm('Are you sure you want to delete this return reason type?')) {
            document.getElementById('delete-form-' + id).submit();
        }
    }
</script>
@endpush

