<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Thêm các trạng thái mới vào enum của orders table
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending', 'confirmed', 'processing', 'shipping', 'delivered', 'completed', 'cancelled', 'refunded',
            'ordered', 'approved', 'shipping_created'
        ) DEFAULT 'pending'");

        // Thêm cột shipping_method vào order_shipping table nếu chưa có
        if (!Schema::hasColumn('order_shipping', 'shipping_method')) {
            Schema::table('order_shipping', function (Blueprint $table) {
                $table->enum('shipping_method', ['third_party', 'self_delivery', 'pickup'])
                      ->nullable()
                      ->after('provider_id');
            });
        }

        // Cập nhật dữ liệu hiện tại (tùy chọn)
        // Chuyển các đơn 'pending' sang 'ordered' nếu muốn sử dụng quy trình mới
        // DB::table('orders')->where('status', 'pending')->update(['status' => 'ordered']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Khôi phục lại enum cũ
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending', 'confirmed', 'processing', 'shipping', 'delivered', 'completed', 'cancelled', 'refunded'
        ) DEFAULT 'pending'");

        // Xóa cột shipping_method
        if (Schema::hasColumn('order_shipping', 'shipping_method')) {
            Schema::table('order_shipping', function (Blueprint $table) {
                $table->dropColumn('shipping_method');
            });
        }
    }
};
