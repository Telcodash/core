<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->unsignedSmallInteger('level')->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insert(array_map(fn (array $role) => [
            ...$role,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['name' => 'advisor', 'label' => 'Advisor', 'level' => 10],
            ['name' => 'advisor_plus', 'label' => 'Advisor Plus', 'level' => 20],
            ['name' => 'manager', 'label' => 'Manager', 'level' => 30],
            ['name' => 'administrator', 'label' => 'Administrator', 'level' => 40],
        ]));

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('password')
                ->constrained('roles')
                ->restrictOnDelete();
        });

        // Existing users get the least-privileged role; promote them explicitly afterwards.
        DB::table('users')->update([
            'role_id' => DB::table('roles')->where('name', 'advisor')->value('id'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
