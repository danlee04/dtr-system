<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            // Null while the assignment is still in effect.
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assignments');
    }
};
