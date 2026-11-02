<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('reference_number', 100)->unique();
            $t->string('kind', 24);
            $t->date('incident_on');
            $t->string('subject');
            $t->text('description');
            $t->date('follow_up_on')->nullable();
            $t->string('status', 24)->default('open');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['status', 'follow_up_on']);
            $t->index(['employee_id', 'incident_on']);
        });
        Schema::create('discipline_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('disciplinary_case_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('path')->unique();
            $t->unsignedBigInteger('size_bytes');
            $t->timestamp('created_at');
            $t->unique(['disciplinary_case_id', 'version']);
        });
        Schema::create('discipline_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('disciplinary_case_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        DB::statement("ALTER TABLE disciplinary_cases ADD CONSTRAINT discipline_kind_check CHECK (kind IN ('violation','sp1','sp2','sp3','counseling')), ADD CONSTRAINT discipline_status_check CHECK (status IN ('open','resolved','voided')), ADD CONSTRAINT discipline_follow_up_check CHECK (follow_up_on IS NULL OR follow_up_on >= incident_on)");
        DB::statement('ALTER TABLE discipline_documents ADD CONSTRAINT discipline_size_check CHECK (size_bytes > 0 AND size_bytes <= 5242880)');
    }

    public function down(): void
    {
        if (DB::table('disciplinary_cases')->exists()) {
            throw new RuntimeException('Rollback ditolak: catatan pelanggaran dan audit masih tersimpan.');
        }
        Schema::dropIfExists('discipline_events');
        Schema::dropIfExists('discipline_documents');
        Schema::dropIfExists('disciplinary_cases');
    }
};
