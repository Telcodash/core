<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users are now provisioned via the `user:create` command and authorised through a
 * global role, so email verification, team invitations, personal teams and the
 * per-team membership role are no longer needed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('team_invitations');

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_personal');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('slug');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('role')->default('member')->after('user_id');
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }
};
