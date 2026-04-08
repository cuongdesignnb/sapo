<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'other_costs')) {
                $table->json('other_costs')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'other_costs_total')) {
                $table->decimal('other_costs_total', 15, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        $cols = array_filter(['other_costs', 'other_costs_total'], fn($c) => Schema::hasColumn('purchases', $c));
        if ($cols) {
            Schema::table('purchases', function (Blueprint $table) use ($cols) {
                $table->dropColumn(array_values($cols));
            });
        }
    }
};
