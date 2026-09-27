<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 20);
            $table->string('room', 50)->nullable();
            $table->string('schedule', 100)->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();

            $table->unique(['course_id', 'academic_term_id', 'code']);
            $table->index(['academic_term_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
