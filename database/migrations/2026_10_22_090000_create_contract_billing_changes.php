<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A record of every change to what a direct debit collects.
     *
     * An operator changing a contract is acting on the customer's own
     * instruction, so the change is made and the subscription re-priced
     * there and then. What the customer is owed is to be told: money
     * leaving their account by a different amount than last month is not
     * something to discover on a bank statement.
     *
     * This is the trail of those changes and the mails that went with
     * them — the one place to look when somebody asks why the amount
     * moved.
     */
    public function up(): void
    {
        Schema::create('contract_billing_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // The service behind the change. Null once it has been
            // deleted; the name is kept for the record.
            $table->foreignId('service_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('service_name')->nullable();

            $table->decimal('previous_amount', 12, 2);
            $table->decimal('new_amount', 12, 2);
            $table->string('currency', 3)->default('EUR');

            $table->string('reason');  // service_added | service_removed | amount_edited
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['contract_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_billing_changes');
    }
};
