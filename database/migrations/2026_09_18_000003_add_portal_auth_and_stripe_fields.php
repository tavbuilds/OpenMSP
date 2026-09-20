<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->rememberToken()->after('is_primary');
            $table->timestamp('portal_last_login_at')->nullable()->after('remember_token');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('notes');
            $table->index('stripe_customer_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('auto_collect')->default(false)->after('notes');
            $table->string('stripe_subscription_id')->nullable()->after('auto_collect');
            $table->string('stripe_subscription_item_id')->nullable()->after('stripe_subscription_id');
            $table->string('stripe_payment_method_id')->nullable()->after('stripe_subscription_item_id');
            $table->string('stripe_mandate_id')->nullable()->after('stripe_payment_method_id');
            // none | pending | active | past_due | cancelled | incomplete
            $table->string('stripe_payment_status')->nullable()->after('stripe_mandate_id');
            $table->timestamp('auto_collect_enabled_at')->nullable()->after('stripe_payment_status');

            $table->index('auto_collect');
            $table->index('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['auto_collect']);
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropColumn([
                'auto_collect',
                'stripe_subscription_id',
                'stripe_subscription_item_id',
                'stripe_payment_method_id',
                'stripe_mandate_id',
                'stripe_payment_status',
                'auto_collect_enabled_at',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['remember_token', 'portal_last_login_at']);
        });
    }
};
