<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $t) {
            $t->string('source', 20)->default('internal');
            $t->string('submitted_name')->nullable();
            $t->string('submitted_phone', 32)->nullable();
        });
        Schema::table('application_status_histories', fn (Blueprint $t) => $t->unsignedBigInteger('actor_id')->nullable()->change());
        Schema::table('applicant_documents', function (Blueprint $t) {
            $t->unsignedBigInteger('uploaded_by')->nullable()->change();
            $t->foreignId('application_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('applications')->where('source', 'public')->exists() || DB::table('application_status_histories')->whereNull('actor_id')->exists() || DB::table('applicant_documents')->whereNull('uploaded_by')->exists()) {
            throw new RuntimeException('Reconcile public applications and their audit actors before reversing this migration.');
        }
        Schema::table('applicant_documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('application_id');
            $t->unsignedBigInteger('uploaded_by')->nullable(false)->change();
        });
        Schema::table('application_status_histories', fn (Blueprint $t) => $t->unsignedBigInteger('actor_id')->nullable(false)->change());
        Schema::table('applications', fn (Blueprint $t) => $t->dropColumn(['source', 'submitted_name', 'submitted_phone']));
    }
};
