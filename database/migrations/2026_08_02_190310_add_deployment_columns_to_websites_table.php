<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('repository_url')->nullable()->after('ssl_status');
            $table->string('git_branch')->default('main')->after('repository_url');
            $table->string('webhook_token', 64)->nullable()->unique()->after('git_branch');
            $table->boolean('auto_deploy')->default(false)->after('webhook_token');
        });

        // Backfill a webhook token for any existing rows (there shouldn't be
        // any in production yet, but local dev/seeded data may have some).
        DB::table('websites')->whereNull('webhook_token')->orderBy('id')->get(['id'])->each(function ($website) {
            DB::table('websites')->where('id', $website->id)->update([
                'webhook_token' => Str::random(40),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['repository_url', 'git_branch', 'webhook_token', 'auto_deploy']);
        });
    }
};
