<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');                 // Service-/pakketnaam
            $table->string('reference')->nullable(); // Interne referentie / ordernummer
            // license | support | subscription | service | other
            $table->string('type')->default('license');

            $table->unsignedInteger('quantity')->default(1);
            // Snapshot van in-/verkoop op moment van vastleggen (per stuk)
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            // monthly | quarterly | yearly | once
            $table->string('billing_cycle')->default('yearly');

            $table->date('start_date');
            $table->date('renewal_date')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->default(0); // notice period
            $table->boolean('auto_renew')->default(true);

            // active | pending | cancelled | expired
            $table->string('status')->default('active');

            $table->date('next_invoice_date')->nullable();
            $table->date('cancelled_at')->nullable();

            // Stored encrypted (encrypted cast on the model)
            $table->text('license_keys')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('renewal_date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
