<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_tasks', function (Blueprint $table) {
            $table->boolean('notify_30')->default(true)->after('notes');
            $table->boolean('notify_14')->default(true);
            $table->boolean('notify_7')->default(true);
            $table->boolean('notify_1')->default(true);
            $table->boolean('notify_expired')->default(true);
            $table->json('sent_offsets')->nullable();
            $table->timestamp('expired_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('planned_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'notify_30', 'notify_14', 'notify_7', 'notify_1', 'notify_expired',
                'sent_offsets', 'expired_notified_at',
            ]);
        });
    }
};
