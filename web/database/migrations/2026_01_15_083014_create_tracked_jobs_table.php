<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracked_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');           // start_service, stop_service, restart_service, enable_service, disable_service
            $table->string('subject');        // service name: nginx, mysql, redis, etc.
            $table->string('status');         // pending, processing, completed, failed
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracked_jobs');
    }
};
