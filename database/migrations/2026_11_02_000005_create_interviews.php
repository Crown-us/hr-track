<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained()->restrictOnDelete();
            $t->foreignId('interviewer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('location')->nullable();
            $t->string('meeting_url', 2048)->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('cancel_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['interviewer_id', 'starts_at', 'ends_at']);
            $t->index(['application_id', 'starts_at']);
        });
        DB::statement('ALTER TABLE interviews ADD CONSTRAINT interviews_time_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
