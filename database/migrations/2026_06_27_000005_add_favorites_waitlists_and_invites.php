<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('favoritable');
            $table->timestamps();
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id']);
        });

        Schema::table('hangouts', function (Blueprint $table): void {
            $table->string('invite_code', 16)->nullable()->unique();
        });
        DB::table('hangouts')->orderBy('id')->eachById(function (object $hangout): void {
            DB::table('hangouts')->where('id', $hangout->id)->update(['invite_code' => Str::lower(Str::random(12))]);
        });
    }

    public function down(): void
    {
        Schema::table('hangouts', fn (Blueprint $table) => $table->dropColumn('invite_code'));
        Schema::dropIfExists('favorites');
    }
};
