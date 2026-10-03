<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable()->unique();
            $table->string('display_phone_number')->nullable();
            $table->string('status', 30)->default('disconnected');
            $table->boolean('coexistence')->default(true);
            $table->text('access_token')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique('tenant_id');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('property_request_id')->nullable()->constrained('property_requests')->nullOnDelete();
            $table->string('wa_id');
            $table->string('contact_name')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'wa_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('wamid')->unique();
            $table->string('direction', 20);
            $table->string('type', 40)->default('text');
            $table->text('body')->nullable();
            $table->string('referral_source_type')->nullable();
            $table->string('referral_source_id')->nullable();
            $table->text('referral_source_url')->nullable();
            $table->string('ctwa_clid')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'conversation_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_accounts');
    }
};
