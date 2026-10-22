<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which language to write to someone in.
     *
     * The choice lived in a cookie, which is a fine place for it right up
     * until something writes to you without a browser in the room. A
     * reminder goes out from the scheduler at eight in the morning, finds
     * no cookie, and falls back to English for everyone.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 5)->nullable()->after('email');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('language', 5)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('language'));
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('language'));
    }
};
