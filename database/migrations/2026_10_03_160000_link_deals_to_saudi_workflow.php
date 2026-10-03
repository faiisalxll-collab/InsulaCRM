<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            // Real-estate stages must be extensible. The original schema used
            // a wholesale-only enum that can reject valid Saudi workflow stages.
            $table->string('stage', 40)->default('new_lead')->change();

            $table->foreignId('property_id')
                ->nullable()
                ->after('lead_id')
                ->constrained('properties')
                ->nullOnDelete();

            $table->foreignId('property_request_id')
                ->nullable()
                ->after('property_id')
                ->constrained('property_requests')
                ->nullOnDelete();

            $table->index(['tenant_id', 'property_id'], 'deals_tenant_property_idx');
            $table->index(['tenant_id', 'property_request_id'], 'deals_tenant_request_idx');
            $table->unique(
                ['tenant_id', 'property_request_id', 'property_id'],
                'deals_request_property_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropUnique('deals_request_property_unique');
            $table->dropIndex('deals_tenant_property_idx');
            $table->dropIndex('deals_tenant_request_idx');
            $table->dropConstrainedForeignId('property_request_id');
            $table->dropConstrainedForeignId('property_id');
        });

        $legacyStages = [
            'new_lead', 'prospecting', 'contacting', 'engaging', 'contacted',
            'offer_made', 'offer_presented', 'negotiating', 'under_contract',
            'dispositions', 'assigned', 'closing', 'closed_won', 'closed_lost',
        ];

        DB::table('deals')
            ->whereNotIn('stage', $legacyStages)
            ->update(['stage' => 'negotiating']);

        Schema::table('deals', function (Blueprint $table) {
            $table->enum('stage', [
                'new_lead', 'prospecting', 'contacting', 'engaging', 'contacted',
                'offer_made', 'offer_presented', 'negotiating', 'under_contract',
                'dispositions', 'assigned', 'closing', 'closed_won', 'closed_lost',
            ])->default('new_lead')->change();
        });
    }
};
