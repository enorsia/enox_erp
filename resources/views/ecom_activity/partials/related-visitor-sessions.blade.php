@php
    use App\Support\EcomTrackerViewData;
    use App\Support\TrackerTime;

    $related = $relatedVisitorSessions ?? collect();
    $visitorId = trim((string) ($activityUser->visitor_id ?? ''));
    $currentSessionId = (string) ($activityUser->session_id ?? '');
@endphp

@if ($visitorId !== '' && $related->count() > 1)
    <div class="section-card">
        <div class="section-title">Other sessions (same visitor)</div>
        <ul class="space-y-2">
            @foreach ($related as $relatedSession)
                @php
                    $fullId = $relatedSession->session_id;
                    $isCurrent = $fullId === $currentSessionId;
                    $when = TrackerTime::formatFromStorage(
                        $relatedSession->last_active_at ?? $relatedSession->created_at,
                        'd M Y, h:i A',
                    );
                    $showUrl = EcomTrackerViewData::activityShowUrlFromRequest(request(), $fullId);
                    $activeClasses = 'border-accent-400/70 bg-accent-500/10 dark:bg-accent-500/15 ring-1 ring-accent-500/25';
                    $idleClasses = 'border-slate-200/80 dark:border-slate-600/50 bg-slate-50/50 dark:bg-slate-800/30 hover:border-accent-400/40 hover:bg-accent-500/5 dark:hover:bg-accent-500/10';
                @endphp
                <li>
                    @if ($isCurrent)
                        <div
                            class="etd-related-session-link etd-related-session-link--active block rounded-lg border px-3 py-2.5 {{ $activeClasses }}"
                            aria-current="page"
                            title="{{ $fullId }}"
                        >
                            <span class="block font-mono text-[12px] text-accent-700 dark:text-accent-300 truncate">
                                {{ $fullId }}
                            </span>
                            <span class="mt-1 block text-[12px] text-accent-600/80 dark:text-accent-200/80 truncate">
                                {{ $when }}
                            </span>
                        </div>
                    @else
                        @can('ecom_tracker.activity.show')
                            <a
                                href="{{ $showUrl }}"
                                class="etd-related-session-link group block rounded-lg border px-3 py-2.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 {{ $idleClasses }}"
                                title="{{ $fullId }}"
                            >
                                <span class="block font-mono text-[12px] text-slate-700 dark:text-slate-200 truncate group-hover:text-accent-600 dark:group-hover:text-accent-400">
                                    {{ $fullId }}
                                </span>
                                <span class="mt-1 block text-[12px] text-slate-500 dark:text-slate-400 truncate group-hover:text-slate-600 dark:group-hover:text-slate-300">
                                    {{ $when }}
                                </span>
                            </a>
                        @else
                            <div class="rounded-lg border px-3 py-2.5 min-w-0 {{ $idleClasses }}">
                                <span class="block font-mono text-[12px] text-slate-700 dark:text-slate-200 truncate" title="{{ $fullId }}">{{ $fullId }}</span>
                                <span class="mt-1 block text-[12px] text-slate-500 dark:text-slate-400 truncate">{{ $when }}</span>
                            </div>
                        @endcan
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
