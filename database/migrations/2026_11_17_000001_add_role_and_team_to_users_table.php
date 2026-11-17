<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users now have a single global role and belong to exactly one team.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('advisor')->after('password');
        });

        // Carry existing data over: the user's current team becomes their team,
        // and team owners become managers.
        DB::table('users')->update(['role' => 'advisor']);

        $ownerIds = DB::table('team_members')->where('role', 'owner')->pluck('user_id');
        DB::table('users')->whereIn('id', $ownerIds)->update(['role' => 'manager']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_team_id');
            $table->dropColumn('email_verified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('team_members')->orderBy('id')->get() as $membership) {
            DB::table('users')
                ->where('id', $membership->user_id)
                ->whereNull('team_id')
                ->update(['team_id' => $membership->team_id]);
        }

        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_members');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_personal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed.');
    }
};
