<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('connection')->default('database');
            $table->string('queue')->default('default');
            $table->unsignedSmallInteger('processes')->default(1);
            $table->string('status')->default('stopped');
            $table->string('supervisor_program_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_workers');
    }
};
