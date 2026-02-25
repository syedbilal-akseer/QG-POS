<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_prices', function (Blueprint $table) {
            $table->decimal('previous_price', 15, 2)->nullable()->after('list_price');
            $table->boolean('price_changed')->default(false)->after('previous_price');
            $table->timestamp('price_updated_at')->nullable()->after('price_changed');
            $table->bigInteger('price_updated_by')->nullable()->after('price_updated_at');
            $table->string('price_type')->nullable()->after('price_updated_by'); // corporate, wholesaler, HBM
            
            $table->foreign('price_updated_by')->references('id')->on('users');
            $table->index(['price_changed', 'price_updated_at']);
            $table->index(['price_type', 'price_changed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_prices', function (Blueprint $table) {
            $table->dropForeign(['price_updated_by']);
            $table->dropIndex(['price_changed', 'price_updated_at']);
            $table->dropIndex(['price_type', 'price_changed']);
            $table->dropColumn([
                'previous_price',
                'price_changed',
                'price_updated_at',
                'price_updated_by',
                'price_type'
            ]);
        });
    }
};