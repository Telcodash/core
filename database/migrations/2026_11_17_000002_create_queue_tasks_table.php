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
        Schema::create('queue_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('queue');
            $table->string('name');
            $table->string('status')->index();
            $table->json('payload');
            $table->json('options')->nullable();
            // String id so subjects may use integer or uuid keys.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('progress')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts_made')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_tasks');
    }
};
