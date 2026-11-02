<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->foreignId('employee_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('auth_version')->default(1);
        });
        Schema::table('employees', fn (Blueprint $t) => $t->foreignId('manager_employee_id')->nullable()->constrained('employees')->restrictOnDelete());
        Schema::table('account_invitations', function (Blueprint $t) {
            $t->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
        });
        Schema::create('account_administration_lock', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('account_administration_lock')->insert(['id' => 1]);
        Schema::create('account_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('invitation_id')->nullable()->constrained('account_invitations')->restrictOnDelete();
            $t->string('action', 40);
            $t->json('changes');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('account_events')->exists() || DB::table('users')->whereNotNull('employee_id')->exists() || DB::table('employees')->whereNotNull('manager_employee_id')->exists()) {
            throw new RuntimeException('Rollback ditolak: pengaturan akun atau riwayat masih tersimpan.');
        }
        Schema::dropIfExists('account_events');
        Schema::dropIfExists('account_administration_lock');
        Schema::table('account_invitations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('employee_id');
            $t->dropColumn('version');
        });
        Schema::table('employees', fn (Blueprint $t) => $t->dropConstrainedForeignId('manager_employee_id'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('employee_id');
            $t->dropColumn(['is_active', 'version', 'auth_version']);
        });
    }
};
