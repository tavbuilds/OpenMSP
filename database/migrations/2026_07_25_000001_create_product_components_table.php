<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bill of materials: links a (composed) product to its components,
        // which are themselves catalog products. Bundle cost is the sum of
        // components × quantity.
        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();     // the bundle
            $table->foreignId('component_id')->constrained('products')->cascadeOnDelete();   // the component
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['product_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
