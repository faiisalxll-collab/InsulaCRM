<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('transaction_type', 20)->default('sale')->after('property_type');
            $table->string('district')->nullable()->after('city');
            $table->string('plan_number')->nullable()->after('district');
            $table->decimal('area_sqm', 10, 2)->nullable()->after('plan_number');
            $table->string('facing', 30)->nullable()->after('area_sqm');
            $table->decimal('street_width_m', 6, 2)->nullable()->after('facing');
            $table->unsignedSmallInteger('property_age_years')->nullable()->after('street_width_m');
            $table->boolean('finance_eligible')->nullable()->after('property_age_years');
            $table->decimal('price_per_sqm', 12, 2)->nullable()->after('finance_eligible');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->index(['tenant_id', 'city', 'district']);
            $table->index(['tenant_id', 'transaction_type', 'property_type']);
        });

        Schema::create('property_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_type', 20);
            $table->string('property_type', 40);
            $table->string('city');
            $table->json('districts')->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->decimal('min_area_sqm', 10, 2)->nullable();
            $table->unsignedSmallInteger('min_bedrooms')->nullable();
            $table->decimal('min_street_width_m', 6, 2)->nullable();
            $table->json('preferred_facings')->nullable();
            $table->unsignedSmallInteger('max_property_age_years')->nullable();
            $table->boolean('finance_required')->default(false);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'city', 'property_type']);
        });

        Schema::create('property_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_request_id')->constrained('property_requests')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->unsignedTinyInteger('match_score');
            $table->boolean('hard_constraints_passed')->default(false);
            $table->unsignedTinyInteger('location_score')->default(0);
            $table->unsignedTinyInteger('price_score')->default(0);
            $table->unsignedTinyInteger('area_score')->default(0);
            $table->unsignedTinyInteger('features_score')->default(0);
            $table->unsignedTinyInteger('finance_score')->default(0);
            $table->json('match_reasons')->nullable();
            $table->json('rejection_reasons')->nullable();
            $table->string('status', 20)->default('eligible');
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'property_request_id', 'property_id'], 'property_matches_unique');
            $table->index(['tenant_id', 'match_score']);
            $table->index(['tenant_id', 'status', 'match_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_matches');
        Schema::dropIfExists('property_requests');
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'city', 'district']);
            $table->dropIndex(['tenant_id', 'transaction_type', 'property_type']);
            $table->dropColumn(['transaction_type','district','plan_number','area_sqm','facing','street_width_m','property_age_years','finance_eligible','price_per_sqm','latitude','longitude']);
        });
    }
};
