<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showings', function (Blueprint $table) {
            $table->foreignId('property_request_id')
                ->nullable()
                ->after('deal_id')
                ->constrained('property_requests')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'property_request_id', 'showing_date'],
                'showings_tenant_request_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('showings', function (Blueprint $table) {
            $table->dropIndex('showings_tenant_request_date_idx');
            $table->dropConstrainedForeignId('property_request_id');
        });
    }
};
