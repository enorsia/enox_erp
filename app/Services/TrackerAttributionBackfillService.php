<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\AttributionRules;
use App\Support\SessionTrafficAttribution;
use Carbon\Carbon;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Safe post-deploy repair: mis-merged session clocks, empty session UTMs, list_traffic, conversion_*.
 */
class TrackerAttributionBackfillService
{
    private const CHUNK = 500;

    private const CONVERSION_CHUNK = 200;

    public function run(?OutputInterface $output = null): void
    {
        $line = static function (string $message) use ($output): void {
            if ($output !== null) {
                $output->writeln($message);
            }
        };

        $info = static function (string $message) use ($output): void {
            if ($output !== null) {
                $output->writeln('<info>'.$message.'</info>');
            }
        };

        $line('Step 1/4: Session-clock repair (only sessions with >30m action span)...');
        $clock = app(VisitorSessionClockRepair::class)->repairAllMismergedSessions();
        $info(sprintf(
            '  Fixed %d session(s); moved %d action(s).',
            $clock['sessions_fixed'],
            $clock['moved'],
        ));

        $line('Step 2/4: Session attribution (empty utm_* / landing only)...');
        $this->runSessionAttribution($output, $info);

        $line('Step 3/4: List traffic (missing or stale direct bucket only)...');
        $this->runListTrafficAttribution($output, $info);

        $line('Step 4/4: Conversion (payment_success missing conversion_* only)...');
        $this->runConversionAttribution(app(ConversionAttributionService::class), $info);
    }

    private function runSessionAttribution(?OutputInterface $output, callable $info): void
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

        $info(sprintf('  Scanned %d sessions; updated %d.', $scanned, $updated));
    }

    private function runListTrafficAttribution(?OutputInterface $output, callable $info): void
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
            $info('  Nothing to update.');

            return;
        }

        $bar = null;
        if ($output !== null && $output->isDecorated()) {
            $bar = new ProgressBar($output, $total);
            $bar->start();
        }

        $query->chunkById(self::CHUNK, function ($sessions) use ($bar, &$scanned, &$updated) {
            foreach ($sessions as $session) {
                $scanned++;

                if (SessionTrafficAttribution::syncListTrafficAttributionColumns($session)) {
                    $updated++;
                }

                $bar?->advance();
            }
        });

        $bar?->finish();
        if ($bar !== null && $output !== null) {
            $output->writeln('');
        }

        $info(sprintf('  Scanned %d sessions; updated %d.', $scanned, $updated));
    }

    private function runConversionAttribution(ConversionAttributionService $conversionAttribution, callable $info): void
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

        $info(sprintf(
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
