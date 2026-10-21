<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // A signed URL is valid until it expires, however many times it
            // is opened. The mail promises one use, so the link now carries a
            // token that is consumed on arrival. Stored hashed: a dump of
            // this table must not hand anyone a working sign-in link.
            $table->string('magic_link_token', 64)->nullable()->after('remember_token');
            $table->timestamp('magic_link_expires_at')->nullable()->after('magic_link_token');
            $table->index('magic_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['magic_link_token']);
            $table->dropColumn(['magic_link_token', 'magic_link_expires_at']);
        });
    }
};
