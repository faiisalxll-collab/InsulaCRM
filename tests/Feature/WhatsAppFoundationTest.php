<?php
namespace Tests\Feature;

use App\Models\Lead;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Tests\TestCase;

class WhatsAppFoundationTest extends TestCase
{
    public function test_whatsapp_settings_are_realestate_admin_only(): void
    {
        $this->actingAsAdmin(['business_mode'=>'realestate']);
        $this->get('/settings/whatsapp')->assertOk()->assertSee('ربط واتساب المكتب');

        $this->actingAsAdmin(['business_mode'=>'wholesale']);
        $this->get('/settings/whatsapp')->assertNotFound();
    }

    public function test_verified_webhook_creates_tenant_scoped_lead_conversation_and_message(): void
    {
        $this->actingAsAdmin(['business_mode'=>'realestate']);
        config(['services.whatsapp.app_secret'=>'secret']);

        $account=WhatsAppAccount::withoutGlobalScopes()->create([
            'tenant_id'=>$this->tenant->id,'phone_number_id'=>'pn-1','waba_id'=>'waba-1','status'=>'connected','coexistence'=>true,
        ]);
        $payload=['entry'=>[['changes'=>[['value'=>[
            'metadata'=>['phone_number_id'=>'pn-1'],
            'contacts'=>[['wa_id'=>'966500000001','profile'=>['name'=>'عميل اختبار']]],
            'messages'=>[['from'=>'966500000001','id'=>'wamid.test.1','timestamp'=>(string)time(),'type'=>'text','text'=>['body'=>'استفسار عن العقار']]],
        ]]]]]];
        $json=json_encode($payload);
        $sig='sha256='.hash_hmac('sha256',$json,'secret');

        $this->call('POST','/api/whatsapp/webhook',[],[],[],[
            'CONTENT_TYPE'=>'application/json','HTTP_X_HUB_SIGNATURE_256'=>$sig
        ],$json)->assertOk();

        $lead=Lead::withoutGlobalScopes()->where('tenant_id',$this->tenant->id)->where('phone','966500000001')->firstOrFail();
        $this->assertSame('whatsapp',$lead->lead_source);
        $this->assertDatabaseHas('whatsapp_conversations',['tenant_id'=>$this->tenant->id,'whatsapp_account_id'=>$account->id,'lead_id'=>$lead->id]);
        $this->assertDatabaseHas('whatsapp_messages',['tenant_id'=>$this->tenant->id,'wamid'=>'wamid.test.1','direction'=>'inbound']);

        $this->call('POST','/api/whatsapp/webhook',[],[],[],[
            'CONTENT_TYPE'=>'application/json','HTTP_X_HUB_SIGNATURE_256'=>$sig
        ],$json)->assertOk();
        $this->assertSame(1,WhatsAppMessage::withoutGlobalScopes()->where('wamid','wamid.test.1')->count());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.whatsapp.app_secret'=>'secret']);
        $this->postJson('/api/whatsapp/webhook',['entry'=>[]],['X-Hub-Signature-256'=>'sha256=bad'])->assertUnauthorized();
    }
}
