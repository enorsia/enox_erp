<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->boolean('track_reach')            ->default(true)->after('show_in_sale_tracking');
            $table->boolean('track_impressions')      ->default(true)->after('track_reach');
            $table->boolean('track_clicks')           ->default(true)->after('track_impressions');
            $table->boolean('track_sessions')         ->default(true)->after('track_clicks');
            $table->boolean('track_engaged_sessions') ->default(true)->after('track_sessions');
            $table->boolean('track_users')            ->default(true)->after('track_engaged_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->dropColumn([
                'track_reach',
                'track_impressions',
                'track_clicks',
                'track_sessions',
                'track_engaged_sessions',
                'track_users',
            ]);
        });
    }
};

