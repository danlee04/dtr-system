<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Times HR copied into slots the DTR printed blank, from the signed paper.
        Schema::create('manual_taps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('slot', 16);
            $table->time('time');
            $table->string('reason', 32);
            $table->string('remarks')->nullable();
            $table->foreignId('filled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // An employee's date has one A.M. arrival, whichever upload shows it.
            $table->unique(['employee_id', 'date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_taps');
    }
};
