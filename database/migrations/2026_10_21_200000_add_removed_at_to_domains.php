<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // The sync never deletes a domain — the customer link, the notes
            // and the history are worth more than a tidy table. It marks the
            // ones the registrar stopped returning instead, so a domain that
            // ran out of quarantine stops looking like one we still manage.
            $table->timestamp('removed_at')->nullable()->after('last_synced_at');
            $table->index('removed_at');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['removed_at']);
            $table->dropColumn('removed_at');
        });
    }
};
