<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('commission_status', 20)
                ->default('pending')
                ->after('brokerage_split_pct');
            $table->timestamp('commission_paid_at')
                ->nullable()
                ->after('commission_status');

            $table->index(
                ['tenant_id', 'commission_status'],
                'deals_tenant_commission_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('deals_tenant_commission_status_idx');
            $table->dropColumn(['commission_status', 'commission_paid_at']);
        });
    }
};
