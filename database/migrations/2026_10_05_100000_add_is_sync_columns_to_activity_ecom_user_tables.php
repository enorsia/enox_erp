<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SYNC_PENDING = 0;

    private const SYNC_DONE = 1;

    private const SYNC_FAILED = 2;

    public function up(): void
    {
        $this->addSyncColumns('activity_ecom_user');
        $this->addSyncColumns('activity_ecom_user_actions');

        $this->migrateLegacyActionSyncColumns();

        $this->dropLegacyActionSyncColumns();
    }

    public function down(): void
    {
        $this->removeSyncColumns('activity_ecom_user_actions');
        $this->removeSyncColumns('activity_ecom_user');
    }

    private function migrateLegacyActionSyncColumns(): void
    {
        $table = 'activity_ecom_user_actions';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sync_status')) {
            return;
        }

        if (! Schema::hasColumn($table, 'is_sync')) {
            return;
        }

        DB::table($table)
            ->where('sync_status', self::SYNC_DONE)
            ->update(['is_sync' => self::SYNC_DONE]);

        DB::table($table)
            ->where('sync_status', self::SYNC_FAILED)
            ->update(['is_sync' => self::SYNC_FAILED]);

        DB::table($table)
            ->where('sync_status', self::SYNC_PENDING)
            ->update(['is_sync' => self::SYNC_PENDING]);

        if (Schema::hasColumn($table, 'sync_attempts') && Schema::hasColumn($table, 'sync_try')) {
            DB::table($table)
                ->whereNotNull('sync_attempts')
                ->where('sync_attempts', '>', 0)
                ->update(['sync_try' => DB::raw('sync_attempts')]);
        }

        if (Schema::hasColumn($table, 'sync_claimed_at') && Schema::hasColumn($table, 'sync_at')) {
            DB::table($table)
                ->whereNotNull('sync_claimed_at')
                ->update(['sync_at' => DB::raw('sync_claimed_at')]);
        }
    }

    private function dropLegacyActionSyncColumns(): void
    {
        $table = 'activity_ecom_user_actions';

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (Schema::hasIndex($table, 'aeua_sync_queue_idx')) {
                $blueprint->dropIndex('aeua_sync_queue_idx');
            }

            $legacy = array_values(array_filter(
                ['sync_status', 'sync_attempts', 'sync_claimed_at'],
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($legacy !== []) {
                $blueprint->dropColumn($legacy);
            }
        });
    }

    private function addSyncColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (! Schema::hasColumn($table, 'is_sync')) {
                $blueprint->unsignedTinyInteger('is_sync')
                    ->default(self::SYNC_PENDING)
                    ->comment('0=pending, 1=done, 2=fail');
            }

            if (! Schema::hasColumn($table, 'sync_try')) {
                $blueprint->unsignedSmallInteger('sync_try')->default(0);
            }

            if (! Schema::hasColumn($table, 'sync_at')) {
                $blueprint->timestamp('sync_at')->nullable();
            }
        });

        $index = $table === 'activity_ecom_user_actions'
            ? 'aeua_is_sync_queue_idx'
            : 'aeau_is_sync_queue_idx';

        if (Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->index(['is_sync', 'id'], $index);
        });
    }

    private function removeSyncColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $index = $table === 'activity_ecom_user_actions'
            ? 'aeua_is_sync_queue_idx'
            : 'aeau_is_sync_queue_idx';

        Schema::table($table, function (Blueprint $blueprint) use ($table, $index) {
            if (Schema::hasIndex($table, $index)) {
                $blueprint->dropIndex($index);
            }

            $columns = array_values(array_filter(
                ['is_sync', 'sync_try', 'sync_at'],
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($columns !== []) {
                $blueprint->dropColumn($columns);
            }
        });
    }
};
