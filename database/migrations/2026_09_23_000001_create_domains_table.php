<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            // Which customer a domain belongs to is decided here, not at the
            // registrar: the sync never writes this column.
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            // The catalog product for this extension, for the purchase price.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();
            $table->string('extension')->nullable();
            $table->date('expires_at')->nullable();
            $table->date('renewal_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            // Off unless someone deliberately turns it on for one domain.
            $table->boolean('notes_visible_to_customer')->default(false);
            $table->string('source')->default('manual'); // manual | openprovider
            $table->string('source_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            // Reminders are internal only; a domain never mails the customer.
            $table->boolean('notify_30')->default(true);
            $table->boolean('notify_14')->default(true);
            $table->boolean('notify_7')->default(true);
            $table->boolean('notify_1')->default(true);
            $table->boolean('notify_expired')->default(true);
            $table->json('sent_offsets')->nullable();
            $table->timestamp('expired_notified_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->unique(['source', 'source_id']);
            $table->index('expires_at');
            $table->index('extension');
            $table->index('is_demo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
