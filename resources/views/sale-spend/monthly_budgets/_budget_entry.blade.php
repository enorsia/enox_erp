{{--
    Recursive budget entry partial.
    Variables:
      $entry = ['platformId', 'platform', 'budget', 'isStructural', 'childEntries', 'hasChildren', 'childSum', 'ownBudget', 'total']
      $depth = 0 (root), 1 (child), 2 (grandchild), …
--}}
@php
    $platform     = $entry['platform'];
    $budget       = $entry['budget'];           // null for structural (virtual) nodes
    $isStructural = $entry['isStructural'];     // true = no own budget, just a group header
    $isRoot       = ($depth === 0);
@endphp

@if($isStructural)
    {{-- ── Structural / Virtual group header (platform has no own budget) ── --}}
    <div class="order-card
        {{ $isRoot
            ? 'bg-slate-50 dark:bg-slate-700/50 border border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-3.5'
            : 'bg-slate-50/70 dark:bg-slate-700/30 border border-dashed border-slate-200 dark:border-slate-600/60 rounded-lg p-2.5'
        }}
        {{ ($entry['hasChildren'] && $isRoot) ? 'border-l-2 border-l-slate-400 dark:border-l-slate-500' : '' }}">

        <div class="flex items-center gap-2 {{ $isRoot ? 'mb-1' : '' }}">
            {{-- Folder icon --}}
            <div class="{{ $isRoot ? 'w-5 h-5' : 'w-4 h-4' }} rounded bg-slate-200 dark:bg-slate-600 flex items-center justify-center shrink-0">
                <svg class="{{ $isRoot ? 'w-3 h-3' : 'w-2.5 h-2.5' }} text-slate-500 dark:text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                </svg>
            </div>
            <span class="{{ $isRoot ? 'text-[13px] font-semibold' : 'text-[12px] font-medium' }} text-slate-600 dark:text-slate-300">
                {{ $platform->name }}
            </span>
            <span class="text-[10px] text-slate-400 dark:text-slate-500 bg-slate-200/70 dark:bg-slate-600/60 px-1.5 py-0.5 rounded italic">
                no own budget
            </span>
            @if($entry['hasChildren'])
                <span class="text-[10px] text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">
                    {{ count($entry['childEntries']) }} {{ Str::plural('sub', count($entry['childEntries'])) }}
                </span>
            @endif
            <span class="ml-auto text-[11px] font-semibold text-slate-500 dark:text-slate-400 shrink-0">
                Group Total: {{ number_format($entry['total'], 2) }}
            </span>
        </div>

        {{-- Recursive children --}}
        @if($entry['hasChildren'])
            <div class="mt-2.5 {{ $isRoot ? 'ml-4' : 'ml-3' }} flex flex-col gap-1.5 border-l-2 border-slate-200 dark:border-slate-600/70 pl-3">
                @foreach ($entry['childEntries'] as $childEntry)
                    @include('sale-spend.monthly_budgets._budget_entry', [
                        'entry' => $childEntry,
                        'depth' => $depth + 1,
                    ])
                @endforeach
            </div>
        @endif
    </div>

@else
    {{-- ── Real budget entry ── --}}
    <div class="order-card
        {{ $isRoot
            ? 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5'
            : 'bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-700 rounded-lg p-2.5'
        }}
        {{ ($entry['hasChildren'] && $isRoot) ? 'border-l-2 border-l-accent-300 dark:border-l-accent-600' : '' }}">

        <div class="{{ $isRoot ? 'grid grid-cols-[1fr_auto] gap-3 items-start' : 'grid grid-cols-[1fr_auto] gap-2 items-center' }}">
            {{-- Left: Info --}}
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-{{ $isRoot ? '2 mb-1' : '1.5' }}">
                    @if($entry['hasChildren'] && $isRoot)
                        <div class="w-5 h-5 rounded bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center shrink-0">
                            <svg class="w-3 h-3 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                            </svg>
                        </div>
                    @elseif(!$isRoot)
                        <div class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-500 shrink-0"></div>
                    @endif

                    <span class="{{ $isRoot ? 'text-[13px] font-semibold text-slate-800 dark:text-slate-100' : 'text-[12px] font-medium text-slate-700 dark:text-slate-200 truncate' }}">
                        {{ $platform->name }}
                    </span>

                    @if($entry['hasChildren'])
                        <span class="text-[10px] text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">
                            {{ count($entry['childEntries']) }} {{ Str::plural('child', count($entry['childEntries'])) }}
                        </span>
                    @endif
                </div>

                @if($isRoot)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5">
                        @if($entry['hasChildren'])
                            <span class="text-[12px] text-slate-500 dark:text-slate-400">
                                Own: <strong>{{ number_format($budget->budget, 2) }} {{ $budget->currency }}</strong>
                            </span>
                            <span class="text-[12px] font-semibold text-accent-600 dark:text-accent-400">
                                Children Sum: {{ number_format($entry['childSum'], 2) }} {{ $budget->currency }}
                            </span>
                            <span class="text-[12px] font-bold text-slate-700 dark:text-slate-200">
                                Total: {{ number_format($entry['total'], 2) }} {{ $budget->currency }}
                            </span>
                        @else
                            <span class="text-[13px] font-semibold text-slate-700 dark:text-slate-200">
                                {{ number_format($budget->budget, 2) }} {{ $budget->currency }}
                            </span>
                        @endif
                        @if ($budget->notes)
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[200px]">{{ $budget->notes }}</span>
                        @endif
                    </div>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $budget->created_at?->diffForHumans() }}</p>
                @else
                    <div class="flex flex-wrap items-center gap-x-2">
                        @if($entry['hasChildren'])
                            <span class="text-[12px] text-slate-500 dark:text-slate-400">
                                Own: <strong>{{ number_format($budget->budget, 2) }} {{ $budget->currency }}</strong>
                            </span>
                            <span class="text-[12px] font-semibold text-accent-600 dark:text-accent-400">
                                +{{ number_format($entry['childSum'], 2) }}
                            </span>
                            <span class="text-[12px] font-bold text-slate-600 dark:text-slate-300">
                                = {{ number_format($entry['total'], 2) }} {{ $budget->currency }}
                            </span>
                        @else
                            <span class="text-[12px] font-semibold text-slate-600 dark:text-slate-300">
                                {{ number_format($budget->budget, 2) }} {{ $budget->currency }}
                            </span>
                        @endif
                        @if ($budget->notes)
                            <span class="text-[10px] text-slate-400 truncate max-w-[160px]">{{ $budget->notes }}</span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Right: Actions --}}
            <div class="flex gap-1 shrink-0">
                @can('general.monthly_budget.show')
                    <a href="{{ route('admin.monthly-budgets.show', $budget->id) }}"
                       class="{{ $isRoot ? 'w-7 h-7 rounded-lg' : 'w-6 h-6 rounded' }} border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                       title="View">
                        <svg class="{{ $isRoot ? 'w-3.5 h-3.5' : 'w-3 h-3' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </a>
                @endcan
                @can('general.monthly_budget.edit')
                    <a href="{{ route('admin.monthly-budgets.edit', $budget->id) }}"
                       class="{{ $isRoot ? 'w-7 h-7 rounded-lg' : 'w-6 h-6 rounded' }} border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                       title="Edit">
                        <svg class="{{ $isRoot ? 'w-3.5 h-3.5' : 'w-3 h-3' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                @endcan
                @can('general.monthly_budget.delete')
                    <button onclick="deleteData({{ $budget->id }})"
                            class="{{ $isRoot ? 'w-7 h-7 rounded-lg' : 'w-6 h-6 rounded' }} border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 hover:border-red-200 transition-colors"
                            title="Delete">
                        <svg class="{{ $isRoot ? 'w-3.5 h-3.5' : 'w-3 h-3' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <form id="delete-form-{{ $budget->id }}" method="POST"
                          action="{{ route('admin.monthly-budgets.destroy', $budget->id) }}"
                          style="display:none;">@csrf @method('DELETE')</form>
                @endcan
            </div>
        </div>

        {{-- Recursive children --}}
        @if($entry['hasChildren'])
            <div class="mt-2.5 {{ $isRoot ? 'ml-6' : 'ml-4' }} flex flex-col gap-1.5 border-l-2 border-slate-100 dark:border-slate-700 pl-3">
                @foreach ($entry['childEntries'] as $childEntry)
                    @include('sale-spend.monthly_budgets._budget_entry', [
                        'entry' => $childEntry,
                        'depth' => $depth + 1,
                    ])
                @endforeach
            </div>
        @endif
    </div>
@endif
