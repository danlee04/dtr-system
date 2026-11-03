<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_uploads', function (Blueprint $table) {
            $table->id();
            // Null until HR chooses the employee for a name that matched nobody, or several people.
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('employee_name', 150);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('file_name');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'period_start']);
        });

        // A day's row on the DTR, as printed. Days with nothing printed have no row.
        Schema::create('dtr_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtr_upload_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('am_arrival')->nullable();
            $table->time('am_departure')->nullable();
            $table->time('pm_arrival')->nullable();
            $table->time('pm_departure')->nullable();
            $table->time('ot_arrival')->nullable();
            $table->time('ot_departure')->nullable();
            $table->string('remarks')->nullable();

            $table->unique(['dtr_upload_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_days');
        Schema::dropIfExists('dtr_uploads');
    }
};
