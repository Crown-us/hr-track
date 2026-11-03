<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $t) {
            $t->string('clock_in_source', 8)->nullable();
            $t->string('clock_out_source', 8)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('attendance_days')->where('clock_in_source', 'web')->orWhere('clock_out_source', 'web')->exists()) {
            throw new RuntimeException('Rollback ditolak: sumber absensi web masih tersimpan.');
        }
        Schema::table('attendance_days', fn (Blueprint $t) => $t->dropColumn(['clock_in_source', 'clock_out_source']));
    }
};
