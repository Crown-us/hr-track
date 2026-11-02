<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('trainer');
            $t->string('location');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('status', 32)->default('scheduled');
            $t->text('notes')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['status', 'starts_at']);
        });
        Schema::create('training_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('attendance', 32)->default('registered');
            $t->decimal('pre_test', 5, 2)->nullable();
            $t->decimal('post_test', 5, 2)->nullable();
            $t->text('notes')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['training_id', 'employee_id']);
        });
        Schema::create('training_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('path')->unique();
            $t->unsignedBigInteger('size_bytes');
            $t->timestamp('created_at');
            $t->unique(['training_id', 'version']);
        });
        Schema::create('training_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        DB::statement("ALTER TABLE trainings ADD CONSTRAINT training_status_check CHECK (status IN ('scheduled','completed','cancelled')), ADD CONSTRAINT training_time_check CHECK (ends_at > starts_at)");
        DB::statement("ALTER TABLE training_participants ADD CONSTRAINT training_attendance_check CHECK (attendance IN ('registered','attended','absent')), ADD CONSTRAINT training_pre_check CHECK (pre_test IS NULL OR pre_test BETWEEN 0 AND 100), ADD CONSTRAINT training_post_check CHECK (post_test IS NULL OR (post_test BETWEEN 0 AND 100 AND pre_test IS NOT NULL AND attendance = 'attended'))");
        DB::statement('ALTER TABLE training_materials ADD CONSTRAINT training_material_size_check CHECK (size_bytes > 0 AND size_bytes <= 5242880)');
    }

    public function down(): void
    {
        if (DB::table('trainings')->exists()) {
            throw new RuntimeException('Rollback ditolak: data dan riwayat pelatihan masih tersimpan.');
        }
        Schema::dropIfExists('training_events');
        Schema::dropIfExists('training_materials');
        Schema::dropIfExists('training_participants');
        Schema::dropIfExists('trainings');
    }
};
