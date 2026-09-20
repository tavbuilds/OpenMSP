<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('notify_renewals')->default(true)->after('notes');
            $table->boolean('is_demo')->default(false)->after('notify_renewals');
            $table->index('is_demo');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('notify_renewals')->default(true)->after('notes');
            $table->boolean('is_demo')->default(false)->after('notify_renewals');
            $table->index('is_demo');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });

        Schema::table('purchase_bundles', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['notify_renewals', 'is_demo']);
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['notify_renewals', 'is_demo']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
        Schema::table('purchase_bundles', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
