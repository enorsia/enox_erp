<?php

namespace App\Console\Commands;

use App\Models\ActivityEcomUserAction;
use App\Services\ConversionAttributionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillConversionAttribution extends Command
{
    protected $signature = 'tracker:backfill-conversion-attribution
                            {--chunk=200 : Actions per batch}
                            {--dry-run : Report only}';

    protected $description = 'Re-apply conversion_* for historical payment_success rows (run after session attribution backfill).';

    public function handle(ConversionAttributionService $conversionAttribution): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(25, (int) $this->option('chunk'));
        $processed = 0;
        $applied = 0;

        ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->orderBy('id')
            ->chunkById($chunk, function ($actions) use ($conversionAttribution, $dryRun, &$processed, &$applied) {
                foreach ($actions as $action) {
                    $processed++;

                    if ($dryRun) {
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
            'Processed %d payment_success actions; %s %d conversions',
            $processed,
            $dryRun ? 'would apply' : 'applied',
            $dryRun ? 0 : $applied,
        ));

        return self::SUCCESS;
    }
}
