<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A change to what a direct debit collects, waiting on the customer.
     *
     * Moving a service into or out of a contract changes what that contract
     * bills. The mandate itself survives — it is given to us, not to an
     * amount — so the Stripe subscription can be updated rather than torn
     * down and asked for again. What cannot survive is collecting a
     * different sum without being told it is coming, so the change sits
     * here until the customer says yes, and the old amount keeps running
     * in the meantime.
     */
    public function up(): void
    {
        Schema::create('contract_billing_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // The contract that is moving, for the sentence the customer
            // reads. Null once it has been deleted; the text is kept too.
            $table->foreignId('service_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('service_name')->nullable();

            $table->decimal('previous_amount', 12, 2);
            $table->decimal('new_amount', 12, 2);
            $table->string('currency', 3)->default('EUR');

            $table->string('reason');  // service_added | service_removed
            $table->string('status')->default('pending'); // pending | confirmed | declined | superseded
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_billing_changes');
    }
};
