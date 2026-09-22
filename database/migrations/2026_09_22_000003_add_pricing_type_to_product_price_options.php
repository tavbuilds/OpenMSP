<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_options', function (Blueprint $table) {
            $table->string('pricing_type')->nullable()->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_options', function (Blueprint $table) {
            $table->dropColumn('pricing_type');
        });
    }
};
