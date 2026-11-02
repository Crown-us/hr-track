<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->dateTime('scheduled_start');
            $t->dateTime('scheduled_end');
            $t->dateTime('clock_in')->nullable();
            $t->dateTime('clock_out')->nullable();
            $t->unsignedSmallInteger('tolerance_minutes')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('voided_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'date']);
        });
        Schema::create('attendance_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_day_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 20);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        DB::statement('ALTER TABLE attendance_days ADD CONSTRAINT attendance_schedule CHECK (scheduled_end > scheduled_start AND TIMESTAMPDIFF(SECOND, scheduled_start, scheduled_end) <= 86400 AND tolerance_minutes <= 120), ADD CONSTRAINT attendance_punch CHECK (clock_out IS NULL OR (clock_in IS NOT NULL AND clock_out >= clock_in AND TIMESTAMPDIFF(SECOND, clock_in, clock_out) <= 86400))');
    }

    public function down(): void
    {
        if (DB::table('attendance_days')->exists() || DB::table('attendance_events')->exists()) {
            throw new RuntimeException('Rollback ditolak: catatan absensi atau riwayat masih tersimpan.');
        }
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_days');
    }
};
