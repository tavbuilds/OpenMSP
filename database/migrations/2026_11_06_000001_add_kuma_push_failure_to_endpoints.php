<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->timestamp('kuma_push_failed_at')->nullable()->index();
            $table->text('kuma_push_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropIndex(['kuma_push_failed_at']);
            $table->dropColumn(['kuma_push_failed_at', 'kuma_push_error']);
        });
    }
};
