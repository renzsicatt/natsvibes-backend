<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('role')->default('user')->index()->after('date_of_birth');
            $table->string('status')->default('pending_verification')->index()->after('role');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->softDeletes();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('going_out_style')->nullable();
            $table->string('availability')->nullable();
            $table->string('photo_review_status')->default('pending');
            $table->string('host_verification_status')->default('not_requested');
            $table->unique('user_id');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('description')->nullable();
            $table->string('city')->default('Makati')->after('area');
            $table->string('google_maps_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->json('opening_hours')->nullable();
            $table->text('reservation_notes')->nullable();
            $table->unsignedSmallInteger('group_capacity_min')->nullable();
            $table->unsignedSmallInteger('group_capacity_max')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->softDeletes();
            $table->index(['status', 'city', 'area']);
        });

        Schema::table('hangouts', function (Blueprint $table) {
            $table->text('rules')->nullable();
            $table->text('host_notes')->nullable();
            $table->dateTime('request_cutoff_at')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->string('previous_status')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->index(['status', 'date_time']);
            $table->index(['host_id', 'status']);
        });

        Schema::table('join_requests', function (Blueprint $table) {
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unique(['hangout_id', 'user_id']);
            $table->index(['hangout_id', 'status', 'created_at']);
        });

        Schema::table('hangout_members', function (Blueprint $table) {
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamp('left_at')->nullable();
            $table->unique(['hangout_id', 'user_id']);
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->string('type')->default('message');
            $table->timestamp('reported_at')->nullable();
            $table->softDeletes();
            $table->index(['hangout_id', 'id']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('reported_message_id')->nullable()->constrained('group_messages')->nullOnDelete();
            $table->string('severity')->default('medium');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->index(['status', 'severity', 'created_at']);
        });

        Schema::table('safety_checkins', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
        });

        Schema::create('hangout_vibe_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hangout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vibe_tag_id')->constrained()->cascadeOnDelete();
            $table->unique(['hangout_id', 'vibe_tag_id']);
        });

        Schema::create('attendance_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hangout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response');
            $table->timestamp('responded_at');
            $table->timestamps();
            $table->unique(['hangout_id', 'user_id']);
        });

        Schema::create('hangout_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hangout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('host_rating')->nullable();
            $table->unsignedTinyInteger('group_vibe_rating')->nullable();
            $table->unsignedTinyInteger('venue_rating')->nullable();
            $table->boolean('safety_concern')->default(false);
            $table->text('private_notes')->nullable();
            $table->timestamps();
            $table->unique(['hangout_id', 'reviewer_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('hangout_feedback');
        Schema::dropIfExists('attendance_responses');
        Schema::dropIfExists('hangout_vibe_tags');
    }
};
