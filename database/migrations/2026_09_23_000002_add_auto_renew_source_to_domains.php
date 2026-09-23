<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // What the registrar literally said: on, off, or default. The
            // boolean beside it is what that means for this account, which
            // only the operator can tell us.
            $table->string('auto_renew_source')->nullable()->after('auto_renew');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('auto_renew_source');
        });
    }
};
