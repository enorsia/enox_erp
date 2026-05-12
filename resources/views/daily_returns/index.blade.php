@extends('layouts.app')

@section('title', 'Daily Returns')

@section('content')
    <div id="daily-returns-page-content"></div>
    <div class="p-5 lg:p-6">
        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Daily Returns</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all daily returns records</p>
            </div>
            @can('general.daily_return.create')
                <a href="{{ route('admin.daily-returns.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Add Daily Return
                </a>
            @endcan
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="get" action="{{ route('admin.daily-returns.index') }}">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">
                <div class="flex-1 min-w-0 flex items-center gap-2 flex-wrap">

                    <!-- Platform filter -->
                    <div class="flex-1 sm:flex-none sm:w-48">
                        <select name="sale_platform_id" class="tom-select w-full h-9" data-placeholder="All Platforms">
                            <option value="">All Platforms</option>
                            @foreach($salePlatforms as $platform)
                                <option value="{{ $platform['id'] }}" {{ request('sale_platform_id') == $platform['id'] ? 'selected' : '' }}>
                                    {!! $platform['label'] !!}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reason Type filter -->
                    <div class="flex-1 sm:flex-none sm:w-48">
                        <select name="return_reason_type_id" class="tom-select w-full h-9" data-placeholder="All Reasons">
                            <option value="">All Reasons</option>
                            @foreach($reasonTypes as $reason)
                                <option value="{{ $reason->id }}" {{ request('return_reason_type_id') == $reason->id ? 'selected' : '' }}>
                                    {{ $reason->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Date From -->
                    <div class="flex-1 sm:flex-none sm:w-40">
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="w-full h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" />
                    </div>

                    <!-- Date To -->
                    <div class="flex-1 sm:flex-none sm:w-40">
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="w-full h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" />
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex items-center gap-2 sm:ml-3">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors whitespace-nowrap shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <span>Filter</span>
                    </button>
                    <a href="{{ route('admin.daily-returns.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                </div>
            </div>
        </form>

        <!-- ── TABLE ── -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Platform</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Reason</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Returns</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse ($dailyReturns as $key => $return)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-4 py-3 text-[12px] text-slate-400">{{ $start + $key }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[13px] font-medium text-slate-700 dark:text-slate-200">
                                        {{ $return->salePlatform->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-slate-600 dark:text-slate-300">
                                    {{ $return->date ? $return->date->format('d M Y') : 'N/A' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge-custom badge-blue text-[11px]">
                                        {{ $return->returnReasonType->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-800 dark:text-slate-100">
                                    {{ number_format($return->number_of_returns) }}
                                </td>
                                <td class="px-4 py-3 text-right text-[13px] text-slate-600 dark:text-slate-300">
                                    {{ number_format($return->number_of_return_quantities) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        @can('general.daily_return.show')
                                            <a href="{{ route('admin.daily-returns.show', $return->id) }}"
                                               class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                                               title="View">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                        @endcan
                                        @can('general.daily_return.edit')
                                            <a href="{{ route('admin.daily-returns.edit', $return->id) }}"
                                               class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                                               title="Edit">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        @endcan
                                        @can('general.daily_return.delete')
                                            <button onclick="deleteData({{ $return->id }})"
                                                    class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 hover:border-red-200 transition-colors"
                                                    title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                            <form id="delete-form-{{ $return->id }}" method="POST"
                                                  action="{{ route('admin.daily-returns.destroy', $return->id) }}" style="display: none;">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                                    No daily return records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── PAGINATION ── -->
        @include('layouts.pagination', ['paginator' => $dailyReturns])
    </div>
@endsection

@push('scripts')
    <script>
        function deleteData(id) {
            if (confirm('Are you sure you want to delete this daily return record?')) {
                document.getElementById('delete-form-' + id).submit();
            }
        }
    </script>
@endpush

