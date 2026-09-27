<?php

namespace App\Console\Commands;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\AttributionRules;
use App\Support\SessionTrafficAttribution;
use Illuminate\Console\Command;

class BackfillSessionAttribution extends Command
{
    protected $signature = 'tracker:backfill-session-attribution
                            {--chunk=500 : Rows per batch}
                            {--dry-run : Report only}';

    protected $description = 'Re-parse landing_page URLs and fix empty session utm_source (run before conversion backfill).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $updated = 0;
        $scanned = 0;

        ActivityEcomUser::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($sessions) use ($dryRun, &$updated, &$scanned) {
                foreach ($sessions as $session) {
                    $scanned++;

                    if (! AttributionRules::sessionUtmSourceIsEmpty($session->utm_source)) {
                        continue;
                    }

                    $landing = $session->landing_page;

                    if (! filled($landing)) {
                        $landing = ActivityEcomUserAction::query()
                            ->where('session_id', $session->session_id)
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->value('page_url');
                    }

                    if (! filled($landing)) {
                        continue;
                    }

                    $parsed = SessionTrafficAttribution::parseFromUrl((string) $landing);

                    if ($parsed === [] || ! isset($parsed['utm_source'])) {
                        continue;
                    }

                    if (! AttributionRules::sessionUtmSourceIsEmpty($session->utm_source)) {
                        continue;
                    }

                    $updates = [
                        'utm_source' => $parsed['utm_source'],
                        'utm_medium' => $parsed['utm_medium'] ?? $session->utm_medium,
                        'utm_campaign' => $parsed['utm_campaign'] ?? $session->utm_campaign,
                    ];

                    if (! $dryRun) {
                        $session->update($updates);
                    }

                    $updated++;
                }
            });

        $this->info(sprintf('Scanned %d sessions; %s %d', $scanned, $dryRun ? 'would update' : 'updated', $updated));

        return self::SUCCESS;
    }
}
