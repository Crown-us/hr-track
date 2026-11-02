<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedSmallInteger('days');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['employee_id', 'year']);
        });
        Schema::create('leave_holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name', 150);
            $t->timestamp('created_at');
        });
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->string('type', 20);
            $t->string('status', 30);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->unsignedSmallInteger('year');
            $t->unsignedSmallInteger('days');
            $t->json('work_dates');
            $t->text('reason');
            $t->string('document_path')->nullable();
            $t->unsignedInteger('document_bytes')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['employee_id', 'year', 'status']);
            $t->index(['status', 'starts_on']);
        });
        Schema::create('leave_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_request_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        DB::statement('ALTER TABLE leave_balances ADD CONSTRAINT leave_balance_days CHECK (days <= 366)');
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT leave_request_values CHECK (days > 0 AND ends_on >= starts_on AND type IN ('annual','sick','permission') AND status IN ('pending_supervisor','pending_hr','approved','rejected','cancel_pending','cancelled'))");
    }

    public function down(): void
    {
        if (DB::table('leave_events')->exists() || DB::table('leave_requests')->exists() || DB::table('leave_balances')->exists() || DB::table('leave_holidays')->exists()) {
            throw new RuntimeException('Rollback ditolak: data cuti atau riwayat masih tersimpan.');
        }
        Schema::dropIfExists('leave_events');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_holidays');
        Schema::dropIfExists('leave_balances');
    }
};
