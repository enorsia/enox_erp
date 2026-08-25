<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_backfill_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('job_name', 100);
            $table->string('chunk_key', 100);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedBigInteger('last_action_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['job_name', 'chunk_key'], 'uq_backfill_job_chunk');
            $table->index('status', 'idx_backfill_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_backfill_checkpoints');
    }
};
