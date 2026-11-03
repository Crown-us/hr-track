<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_day_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('day_version');
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 24);
            $t->json('original');
            $t->dateTime('clock_in');
            $t->dateTime('clock_out');
            $t->text('reason');
            $t->timestamps();
            $t->index(['employee_id', 'status']);
            $t->index(['attendance_day_id', 'status']);
        });
        Schema::create('attendance_correction_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_correction_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 20);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        DB::statement("ALTER TABLE attendance_corrections ADD CONSTRAINT correction_status CHECK (status IN ('pending_supervisor','pending_hr','approved','rejected','withdrawn')), ADD CONSTRAINT correction_time CHECK (clock_out > clock_in AND TIMESTAMPDIFF(SECOND,clock_in,clock_out) <= 86400)");
    }

    public function down(): void
    {
        if (DB::table('attendance_corrections')->exists() || DB::table('attendance_correction_events')->exists()) {
            throw new RuntimeException('Rollback ditolak: pengajuan koreksi masih tersimpan.');
        }
        Schema::dropIfExists('attendance_correction_events');
        Schema::dropIfExists('attendance_corrections');
    }
};
