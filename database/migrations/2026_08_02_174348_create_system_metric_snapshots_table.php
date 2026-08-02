<?php

declare(strict_types=1);

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
        Schema::create('system_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_supported')->default(true);
            $table->float('cpu_usage_percent')->nullable();
            $table->unsignedBigInteger('memory_used_bytes')->nullable();
            $table->unsignedBigInteger('memory_total_bytes')->nullable();
            $table->unsignedBigInteger('disk_used_bytes')->nullable();
            $table->unsignedBigInteger('disk_total_bytes')->nullable();
            $table->float('load_1min')->nullable();
            $table->float('load_5min')->nullable();
            $table->float('load_15min')->nullable();
            $table->unsignedBigInteger('network_rx_bytes')->nullable();
            $table->unsignedBigInteger('network_tx_bytes')->nullable();
            $table->timestamp('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metric_snapshots');
    }
};
