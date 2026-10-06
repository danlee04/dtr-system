<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 10)->nullable();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('employment_status', 30);
            // A string, not a number: the device's user ID can carry leading
            // zeros, and to the device "0042" and "42" are different people.
            $table->string('biometric_id', 20)->nullable()->unique();
            $table->date('date_hired')->nullable();
            $table->date('date_separated')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
