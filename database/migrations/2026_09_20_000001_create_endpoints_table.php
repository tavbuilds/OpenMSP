<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('kind')->default('certificate'); // certificate | domain | other
            $table->string('hostname')->nullable();
            $table->string('url')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('source')->default('manual'); // manual | webhook
            $table->string('webhook_token', 64)->unique();
            $table->string('last_status')->nullable(); // ok | warning | expired | unknown
            $table->timestamp('last_checked_at')->nullable();
            $table->json('last_payload')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('notify_30')->default(true);
            $table->boolean('notify_14')->default(true);
            $table->boolean('notify_7')->default(true);
            $table->boolean('notify_1')->default(true);
            $table->boolean('notify_expired')->default(true);
            $table->boolean('notify_customer')->default(false);
            $table->json('sent_offsets')->nullable();
            $table->timestamp('expired_notified_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index('expires_at');
            $table->index('kind');
            $table->index('is_demo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endpoints');
    }
};
