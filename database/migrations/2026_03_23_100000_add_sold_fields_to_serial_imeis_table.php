<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serial_imeis', function (Blueprint $table) {
            if (!Schema::hasColumn('serial_imeis', 'sold_at')) {
                $table->timestamp('sold_at')->nullable();
            }
            if (!Schema::hasColumn('serial_imeis', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable();
            }
            if (!Schema::hasColumn('serial_imeis', 'warranty_expires_at')) {
                $table->timestamp('warranty_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $cols = array_filter(['sold_at', 'invoice_id', 'warranty_expires_at'], fn($c) => Schema::hasColumn('serial_imeis', $c));
        if ($cols) {
            Schema::table('serial_imeis', function (Blueprint $table) use ($cols) {
                $table->dropColumn(array_values($cols));
            });
        }
    }
};
