<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $t) {
            $t->date('issued_on')->nullable()->after('incident_on');
            $t->date('valid_until')->nullable()->after('issued_on');
            $t->index(['employee_id', 'kind', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $t) {
            $t->dropIndex(['employee_id', 'kind', 'valid_until']);
            $t->dropColumn(['issued_on', 'valid_until']);
        });
    }
};
