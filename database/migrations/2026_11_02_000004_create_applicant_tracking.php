<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->index();
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained()->restrictOnDelete();
            $table->date('application_date');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('screening');
            $table->unsignedInteger('version')->default(1);
            $table->unique(['applicant_id', 'vacancy_id']);
            $table->index(['vacancy_id', 'status']);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ('screening','interview','offering','accepted','rejected'))");
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['application_id', 'created_at', 'id']);
        });
        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path')->unique();
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
            $table->index(['applicant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('applicants');
    }
};
