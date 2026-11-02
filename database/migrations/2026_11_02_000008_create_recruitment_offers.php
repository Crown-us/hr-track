<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('position_id');
            $t->foreign(['position_id', 'department_id'])->references(['id', 'department_id'])->on('positions')->restrictOnDelete();
            $t->string('employment_status', 32);
            $t->date('join_date');
            $t->date('response_deadline');
            $t->text('terms')->nullable();
            $t->string('status', 32)->default('pending');
            $t->date('response_date')->nullable();
            $t->text('response_note')->nullable();
            $t->string('evidence_path')->nullable()->unique();
            $t->unsignedBigInteger('evidence_size')->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE recruitment_offers ADD CONSTRAINT offers_status_check CHECK (status IN ('pending','accepted','declined','withdrawn')), ADD CONSTRAINT offers_employment_check CHECK (employment_status IN ('permanent','contract','probation','intern')), ADD CONSTRAINT offers_dates_check CHECK (join_date >= response_deadline)");
        Schema::create('recruitment_offer_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recruitment_offer_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        Schema::table('employees', function (Blueprint $t) {
            $t->foreignId('applicant_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('source_application_id')->nullable()->unique()->constrained('applications')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('recruitment_offers')->exists() || DB::table('employees')->whereNotNull('applicant_id')->exists()) {
            throw new RuntimeException('Rollback ditolak: riwayat penawaran/konversi masih tersimpan.');
        }
        Schema::table('employees', function (Blueprint $t) {
            $t->dropConstrainedForeignId('applicant_id');
            $t->dropConstrainedForeignId('source_application_id');
        });
        Schema::dropIfExists('recruitment_offer_events');
        Schema::dropIfExists('recruitment_offers');
    }
};
