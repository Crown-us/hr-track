<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('job_title')->index();
            $table->string('employment_type', 20);
            $table->string('location');
            $table->text('description');
            $table->text('requirements');
            $table->date('opening_date');
            $table->date('closing_date');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'opening_date', 'closing_date']);
        });
        DB::statement('ALTER TABLE vacancies ADD CONSTRAINT vacancies_dates_check CHECK (closing_date >= opening_date)');
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT vacancies_status_check CHECK (status IN ('draft','published','closed'))");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT vacancies_type_check CHECK (employment_type IN ('full_time','part_time','contract','internship'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
