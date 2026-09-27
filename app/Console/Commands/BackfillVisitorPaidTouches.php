<?php

namespace App\Console\Commands;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\VisitorPaidTouchService;
use App\Support\AttributionRules;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerSessionClock;
use App\Support\TrackerTime;
use Illuminate\Console\Command;

class BackfillVisitorPaidTouches extends Command
{
    protected $signature = 'tracker:backfill-visitor-paid-touches
                            {--chunk=500 : Actions per batch}
                            {--dry-run : Report only}';

    protected $description = 'Optional 7c: rebuild visitor_last_paid_touch from historical action page_urls.';

    public function handle(VisitorPaidTouchService $touchService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $processed = 0;
        $recorded = 0;

        ActivityEcomUserAction::query()
            ->whereNotNull('page_url')
            ->where('page_url', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($actions) use ($touchService, $dryRun, &$processed, &$recorded) {
                foreach ($actions as $action) {
                    $processed++;

                    $session = ActivityEcomUser::query()
                        ->where('session_id', $action->session_id)
                        ->first(['visitor_id', 'landing_page']);

                    $visitorId = trim((string) ($session?->visitor_id ?? ''));

                    if ($visitorId === '') {
                        continue;
                    }

                    $parsed = SessionTrafficAttribution::parseFromUrl($action->page_url);

                    if (! AttributionRules::isMarketingQualifyingTouch($parsed, $action->page_url)) {
                        continue;
                    }

                    if ($dryRun) {
                        $recorded++;

                        continue;
                    }

                    $eventAt = TrackerSessionClock::activityAt([
                        'created_at' => $action->created_at,
                        'end_time' => $action->end_time,
                    ]) ?? TrackerTime::nowUtc();

                    $touchService->recordFromIngestEvent(
                        $visitorId,
                        $action->session_id,
                        $action->page_url,
                        $action->referer,
                        $eventAt,
                        $action->event_id,
                        ['landing_page' => $session?->landing_page],
                    );

                    $recorded++;
                }
            });

        $this->info(sprintf(
            'Scanned %d actions; %s %d paid touches',
            $processed,
            $dryRun ? 'would record' : 'recorded',
            $recorded,
        ));

        return self::SUCCESS;
    }
}
