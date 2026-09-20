<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            // license | support | subscription | service | other
            $table->string('type')->default('license');
            $table->decimal('default_cost_price', 12, 2)->default(0);
            $table->decimal('default_sale_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            // monthly | quarterly | yearly | once
            $table->string('billing_cycle')->default('yearly');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
