<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('attended_on');
            $table->string('status', 20);
            $table->string('remarks', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One record per student, class and session date.
            $table->unique(['section_id', 'student_id', 'attended_on']);
            $table->index(['section_id', 'attended_on']);
            $table->index(['student_id', 'attended_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
