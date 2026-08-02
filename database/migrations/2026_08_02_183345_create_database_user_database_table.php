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
        Schema::create('database_user_database', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('database_id')->constrained('databases')->cascadeOnDelete();
            $table->json('privileges');
            $table->timestamps();
            $table->unique(['database_user_id', 'database_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_user_database');
    }
};
