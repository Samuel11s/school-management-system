<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('enrolled')->index();
            $table->timestamp('enrolled_at');
            $table->timestamp('dropped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('letter_grade', 2)->nullable();
            $table->timestamps();

            // At most one enrollment row per student and section. Re-enrolling
            // after a drop reactivates the row, keeping its history attached.
            $table->unique(['student_id', 'section_id']);
            $table->index(['section_id', 'status']);
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('enrollments');
    }
};
