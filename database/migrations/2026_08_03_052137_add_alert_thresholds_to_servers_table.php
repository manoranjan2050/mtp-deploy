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
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedTinyInteger('cpu_alert_threshold')->nullable()->after('php_versions');
            $table->unsignedTinyInteger('memory_alert_threshold')->nullable()->after('cpu_alert_threshold');
            $table->unsignedTinyInteger('disk_alert_threshold')->nullable()->after('memory_alert_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['cpu_alert_threshold', 'memory_alert_threshold', 'disk_alert_threshold']);
        });
    }
};
