<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('viewer');
        });

        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('role', 20);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
