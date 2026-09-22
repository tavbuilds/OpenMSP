<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('billing_term');
            $table->string('commitment_term');
            $table->unsignedSmallInteger('commitment_months')->default(1);
            $table->string('billing_cycle')->default('monthly');
            $table->string('unit_of_measure')->nullable();
            $table->string('charge_type')->nullable();
            $table->unsignedInteger('min_qty')->default(1);
            $table->unsignedInteger('max_qty')->nullable();
            $table->decimal('cost_price', 12, 4)->default(0);
            $table->decimal('sale_price', 12, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_options');
    }
};
