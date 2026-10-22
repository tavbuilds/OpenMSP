<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A move waiting on the customer, and what it would cost them.
     *
     * Moving a service into or out of a contract changes what that contract
     * bills. The mandate itself survives — it is given to us, not to an
     * amount — so the Stripe subscription can be updated rather than torn
     * down and asked for again. The sum is still theirs to agree to, and
     * that agreement is the last step: the move described here has not
     * happened, and does not happen unless they approve it.
     */
    public function up(): void
    {
        Schema::create('contract_billing_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // The contract that would move. Carried out on approval, so
            // this is what to act on, not only what to say. Null once it
            // has been deleted; the name is kept for the record.
            $table->foreignId('service_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('service_name')->nullable();

            $table->decimal('previous_amount', 12, 2);
            $table->decimal('new_amount', 12, 2);
            $table->string('currency', 3)->default('EUR');

            $table->string('reason');  // service_added | service_removed
            $table->string('status')->default('pending'); // pending | confirmed | declined | withdrawn
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
