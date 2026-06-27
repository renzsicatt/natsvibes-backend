<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hangout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('attendance')->default('attended');
            $table->boolean('safety_concern')->default(false);
            $table->text('private_notes')->nullable();
            $table->timestamps();
            $table->unique(['hangout_id', 'reviewer_id', 'reviewed_user_id']);
            $table->index(['reviewed_user_id', 'attendance']);
        });

        Schema::create('moderation_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_status');
            $table->text('statement');
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_appeals');
        Schema::dropIfExists('peer_reviews');
    }
};
