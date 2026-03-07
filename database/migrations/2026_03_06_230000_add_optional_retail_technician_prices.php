<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('price_books', function (Blueprint $table) {
            $table->boolean('enable_retail_price')->default(false)->after('cashier_warn_not_in_book');
            $table->boolean('enable_technician_price')->default(false)->after('enable_retail_price');
        });

        Schema::table('price_book_products', function (Blueprint $table) {
            $table->decimal('retail_price', 15, 2)->nullable()->after('price');
            $table->decimal('technician_price', 15, 2)->nullable()->after('retail_price');
        });
    }

    public function down(): void
    {
        Schema::table('price_book_products', function (Blueprint $table) {
            $table->dropColumn(['retail_price', 'technician_price']);
        });

        Schema::table('price_books', function (Blueprint $table) {
            $table->dropColumn(['enable_retail_price', 'enable_technician_price']);
        });
    }
};
