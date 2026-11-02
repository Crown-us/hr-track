<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('contract_number', 100)->unique();
            $t->string('type', 16);
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['voided_at', 'ends_on']);
            $t->index(['employee_id', 'starts_on']);
        });
        Schema::create('contract_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_contract_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('path')->unique();
            $t->unsignedBigInteger('size_bytes');
            $t->timestamp('created_at');
            $t->unique(['employee_contract_id', 'version']);
        });
        Schema::create('contract_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_contract_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->json('changes');
            $t->timestamp('created_at');
        });
        DB::statement("ALTER TABLE employee_contracts ADD CONSTRAINT contract_period_check CHECK ((type = 'pkwt' AND ends_on IS NOT NULL AND ends_on >= starts_on) OR (type = 'pkwtt' AND ends_on IS NULL))");
        DB::statement('ALTER TABLE contract_documents ADD CONSTRAINT contract_document_size_check CHECK (size_bytes > 0 AND size_bytes <= 5242880)');
    }

    public function down(): void
    {
        if (DB::table('employee_contracts')->exists()) {
            throw new RuntimeException('Rollback ditolak: kontrak dan riwayat masih tersimpan.');
        }
        Schema::dropIfExists('contract_events');
        Schema::dropIfExists('contract_documents');
        Schema::dropIfExists('employee_contracts');
    }
};
