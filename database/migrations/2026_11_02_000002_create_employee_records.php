<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unique(['department_id', 'name']);
            $table->unique(['id', 'department_id']);
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 50)->unique();
            $table->string('full_name')->index();
            $table->string('work_email')->unique();
            $table->string('phone', 32)->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('position_id');
            $table->foreign(['position_id', 'department_id'])->references(['id', 'department_id'])->on('positions')->restrictOnDelete();
            $table->string('employment_status', 20);
            $table->date('join_date')->index();
            $table->unsignedInteger('version')->default(1);
            $table->index(['department_id', 'employment_status']);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_check CHECK (employment_status IN ('permanent','contract','probation','intern','inactive'))");
        Schema::create('employee_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->string('document_path')->nullable()->unique();
            $table->unsignedInteger('document_size')->nullable();
            $table->timestamp('created_at');
            $table->index(['employee_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_events');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
