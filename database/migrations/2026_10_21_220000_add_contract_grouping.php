<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // A contract was one service. It is now either a service, or a
            // collection that holds several — "Website hosting & domains"
            // with the hosting, the domain and the SSL inside it. The
            // collection owns the term: cycle, start date, notice period.
            $table->foreignId('parent_id')->nullable()->after('company_id')
                ->constrained('contracts')->nullOnDelete();
            $table->boolean('is_group')->default(false)->after('parent_id');

            $table->index('parent_id');
            $table->index('is_group');
        });

        // A collection totals its services, so it holds no price of its
        // own. Zero would be a price — and a wrong one — where null is the
        // truthful "this row does not carry money".
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable()->change();
            $table->decimal('sale_price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable(false)->default(0)->change();
            $table->decimal('sale_price', 12, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_group']);
            $table->dropColumn(['parent_id', 'is_group']);
        });
    }
};
