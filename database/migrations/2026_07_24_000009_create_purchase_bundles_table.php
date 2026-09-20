<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('reference')->nullable();

            // Fixed total cost of this purchased bundle (per billing cycle).
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            // monthly | quarterly | yearly | once
            $table->string('billing_cycle')->default('yearly');
            // Allocation key: currently 'even' (split across active contracts).
            $table->string('allocation_method')->default('even');

            $table->date('start_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::table('contracts', function (Blueprint $table) {
            // If set: cost comes from the bundle (auto-allocated), not from cost_price.
            $table->foreignId('purchase_bundle_id')->nullable()->after('vendor_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_bundle_id');
        });

        Schema::dropIfExists('purchase_bundles');
    }
};
