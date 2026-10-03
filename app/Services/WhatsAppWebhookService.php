<?php
namespace App\Services;

use App\Models\Lead;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookService
{
    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value=$change['value'] ?? [];
                $phoneId=$value['metadata']['phone_number_id'] ?? null;
                if (!$phoneId) continue;

                $account=WhatsAppAccount::withoutGlobalScopes()->where('phone_number_id',$phoneId)->where('status','connected')->first();
                if (!$account) continue;
                $account->forceFill(['last_webhook_at'=>now()])->save();

                foreach ($value['messages'] ?? [] as $incoming) {
                    $this->storeIncoming($account,$value,$incoming);
                }
            }
        }
    }

    private function storeIncoming(WhatsAppAccount $account,array $value,array $incoming): void
    {
        $wamid=$incoming['id'] ?? null;
        $waId=$incoming['from'] ?? null;
        if (!$wamid || !$waId || WhatsAppMessage::withoutGlobalScopes()->where('wamid',$wamid)->exists()) return;

        DB::transaction(function () use ($account,$value,$incoming,$wamid,$waId) {
            $name=collect($value['contacts'] ?? [])->firstWhere('wa_id',$waId)['profile']['name'] ?? null;
            $lead=Lead::withoutGlobalScopes()->where('tenant_id',$account->tenant_id)->where('phone',$waId)->first();
            if (!$lead) {
                $parts=preg_split('/\s+/', trim((string)($name ?: 'WhatsApp')));
                $lead=Lead::withoutGlobalScopes()->create([
                    'tenant_id'=>$account->tenant_id,
                    'first_name'=>array_shift($parts) ?: 'WhatsApp',
                    'last_name'=>implode(' ',$parts) ?: 'Contact',
                    'phone'=>$waId,
                    'lead_source'=>'whatsapp',
                    'status'=>'new',
                    'temperature'=>'warm',
                ]);
            }

            $conversation=WhatsAppConversation::withoutGlobalScopes()->firstOrCreate(
                ['whatsapp_account_id'=>$account->id,'wa_id'=>$waId],
                ['tenant_id'=>$account->tenant_id,'lead_id'=>$lead->id,'contact_name'=>$name,'status'=>'open']
            );
            if (!$conversation->lead_id) $conversation->lead_id=$lead->id;
            $conversation->contact_name=$name ?: $conversation->contact_name;
            $conversation->last_message_at=isset($incoming['timestamp']) ? Carbon::createFromTimestamp((int)$incoming['timestamp']) : now();
            $conversation->save();

            $ref=$incoming['referral'] ?? [];
            WhatsAppMessage::withoutGlobalScopes()->create([
                'tenant_id'=>$account->tenant_id,
                'conversation_id'=>$conversation->id,
                'wamid'=>$wamid,
                'direction'=>'inbound',
                'type'=>$incoming['type'] ?? 'unknown',
                'body'=>$incoming['text']['body'] ?? null,
                'referral_source_type'=>$ref['source_type'] ?? null,
                'referral_source_id'=>$ref['source_id'] ?? null,
                'referral_source_url'=>$ref['source_url'] ?? null,
                'ctwa_clid'=>$ref['ctwa_clid'] ?? null,
                'payload'=>$incoming,
                'sent_at'=>$conversation->last_message_at,
            ]);
        });
    }
}
