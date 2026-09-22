<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_demo');
            $table->string('source_id')->nullable()->after('source');
            $table->unique(['source', 'source_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_demo');
            $table->string('source_id')->nullable()->after('source');
            $table->decimal('suggested_sale_price', 12, 2)->nullable()->after('default_sale_price');
            $table->unique(['source', 'source_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_demo');
            $table->string('source_id')->nullable()->after('source');
            $table->unique(['source', 'source_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_demo');
            $table->string('source_id')->nullable()->after('source');
            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id', 'suggested_sale_price']);
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id']);
        });
    }
};
