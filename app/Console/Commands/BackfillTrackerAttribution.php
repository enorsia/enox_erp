<?php

namespace App\Console\Commands;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\ConversionAttributionService;
use App\Services\VisitorSessionClockRepair;
use App\Support\AttributionRules;
use App\Support\SessionTrafficAttribution;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillTrackerAttribution extends Command
{
    private const CHUNK = 500;

    private const CONVERSION_CHUNK = 200;

    protected $signature = 'tracker:backfill-attribution';

    protected $description = 'Safe post-deploy backfill: fix only mis-merged sessions, fill empty session UTMs, refresh list_traffic where needed, fill missing conversion_* (does not overwrite good data).';

    public function handle(
        ConversionAttributionService $conversionAttribution,
        VisitorSessionClockRepair $sessionClockRepair,
    ): int {
        $this->line('Step 1/4: Session-clock repair (only sessions with >30m action span)...');
        $clock = $sessionClockRepair->repairAllMismergedSessions();
        $this->info(sprintf(
            '  Fixed %d session(s); moved %d action(s).',
            $clock['sessions_fixed'],
            $clock['moved'],
        ));

        $this->line('Step 2/4: Session attribution (empty utm_* / landing only)...');
        $this->runSessionAttribution();

        $this->line('Step 3/4: List traffic (missing or stale direct bucket only)...');
        $this->runListTrafficAttribution();

        $this->line('Step 4/4: Conversion (payment_success missing conversion_* only)...');
        $this->runConversionAttribution($conversionAttribution);

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function runSessionAttribution(): void
    {
        $updated = 0;
        $scanned = 0;

        ActivityEcomUser::query()
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($sessions) use (&$updated, &$scanned) {
                foreach ($sessions as $session) {
                    $scanned++;

                    if ($this->sessionNeedsNoBackfill($session)) {
                        continue;
                    }

                    if (SessionTrafficAttribution::backfillFromAllActions($session)) {
                        $updated++;
                    }
                }
            });

        $this->info(sprintf('  Scanned %d sessions; updated %d.', $scanned, $updated));
    }

    private function runListTrafficAttribution(): void
    {
        $scanned = 0;
        $updated = 0;

        $query = ActivityEcomUser::query()
            ->orderBy('id')
            ->where(function ($builder) {
                $builder->whereNull('list_traffic_utm_source')
                    ->orWhere('list_traffic_utm_source', '')
                    ->orWhere('list_traffic_utm_source', '(direct)');
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('  Nothing to update.');

            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(self::CHUNK, function ($sessions) use ($bar, &$scanned, &$updated) {
            foreach ($sessions as $session) {
                $scanned++;

                if (SessionTrafficAttribution::syncListTrafficAttributionColumns($session)) {
                    $updated++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('  Scanned %d sessions; updated %d.', $scanned, $updated));
    }

    private function runConversionAttribution(ConversionAttributionService $conversionAttribution): void
    {
        $processed = 0;
        $applied = 0;
        $skipped = 0;

        ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->orderBy('id')
            ->chunkById(self::CONVERSION_CHUNK, function ($actions) use ($conversionAttribution, &$processed, &$applied, &$skipped) {
                foreach ($actions as $action) {
                    $processed++;

                    $session = ActivityEcomUser::query()
                        ->where('session_id', $action->session_id)
                        ->first(['session_id', 'conversion_utm_source']);

                    if ($session !== null && ! AttributionRules::sessionUtmSourceIsEmpty($session->conversion_utm_source)) {
                        $skipped++;

                        continue;
                    }

                    $paymentAt = $action->created_at instanceof Carbon
                        ? $action->created_at->copy()->utc()
                        : Carbon::parse((string) $action->created_at)->utc();

                    $result = $conversionAttribution->applyForPaymentSuccess(
                        $action,
                        [],
                        $paymentAt,
                    );

                    if (($result['status'] ?? '') === 'ok') {
                        $applied++;
                    }
                }
            });

        $this->info(sprintf(
            '  Processed %d payment_success; applied %d; skipped %d (conversion already set).',
            $processed,
            $applied,
            $skipped,
        ));
    }

    private function sessionNeedsNoBackfill(ActivityEcomUser $session): bool
    {
        return ! AttributionRules::sessionUtmSourceIsEmpty($session->utm_source)
            && filled($session->utm_medium)
            && filled($session->utm_campaign);
    }
}
