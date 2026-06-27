<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table): void {
            $table->foreignId('reply_to_id')->nullable()->constrained('group_messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['group_message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::table('group_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('edited_at');
        });
    }
};
