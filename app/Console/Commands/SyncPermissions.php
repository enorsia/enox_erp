<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
        {--no-delete : Do not delete permissions missing from config}';

    protected $description = 'Sync permissions from config/permissions.php';

    public function handle(): int
    {
        $this->info('🚀 Starting permissions sync');

        Cache::forget('permissions.available');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (app()->configurationIsCached()) {
            $this->warn('Config is cached. Run: php artisan config:clear');
        }

        $config = config('permissions');
        $defaultGuard = Arr::get($config, 'default_guard', 'web');
        $map = Arr::get($config, 'map', []);

        if (empty($map)) {
            $this->error('permissions.map is empty');
            return self::FAILURE;
        }

        $permissionNames = [];

        DB::beginTransaction();

        try {
            foreach ($map as $module => $resources) {
                foreach ($resources as $resource => $meta) {

                    $guard = Arr::get($meta, 'guard', $defaultGuard);
                    $actions = Arr::get($meta, 'actions', []);

                    foreach ($actions as $action) {
                        $name = "{$module}.{$resource}.{$action}";
                        $permissionNames[] = $name;

                        Permission::updateOrCreate(
                            ['name' => $name, 'guard_name' => $guard],
                            []
                        );

                        $this->line("✔ {$name}");
                    }
                }
            }

            if (! $this->option('no-delete')) {
                Permission::whereNotIn('name', $permissionNames)->delete();
                $this->info('🧹 Removed permissions missing from config');
            } else {
                $this->info('⏭ Skipped permission deletion');
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('[permissions:sync]', ['exception' => $e]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->syncSuperAdminRole();
        $this->ensureAdminUser();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('✅ Permissions synced successfully');
        return self::SUCCESS;
    }

    protected function syncSuperAdminRole(): void
    {
        $guard = 'web';

        $role = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $guard,
        ]);

        $permissions = Permission::where('guard_name', $guard)->get();
        $role->syncPermissions($permissions);

        $this->info("🔐 Assigned {$permissions->count()} permissions to super-admin");
    }

    protected function ensureAdminUser(): void
    {
        try {
            $admin = User::firstOrCreate(
                ['email' => env('ADMIN_DEFAULT_EMAIL', config('enoxsuite.super_admin_username'))],
                [
                    'name' => env('ADMIN_DEFAULT_NAME', 'SUPER ADMIN'),
                    'password' => Hash::make(config('enoxsuite.super_admin_password')),
                ]
            );

            if (! $admin->hasRole('super-admin')) {
                $admin->assignRole('super-admin');
                $this->info('👤 Super-admin user ready');
            }
        } catch (Throwable $e) {
            Log::error('[permissions:sync][admin]', ['exception' => $e]);
            $this->warn('Could not ensure admin user');
        }
    }
}
