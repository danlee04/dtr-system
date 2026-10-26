<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // An employee's own account, or an HR account linked to its owner's record.
            $table->foreignId('employee_id')->nullable()->unique()->after('role')->constrained()->nullOnDelete();
            $table->boolean('must_change_password')->default(false)->after('is_active');
            // Employees sign in with their employee number; many have no email.
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('must_change_password');
            $table->string('email')->nullable(false)->change();
        });
    }
};
