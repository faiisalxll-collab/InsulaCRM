<?php
namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    protected $fillable=['tenant_id','whatsapp_account_id','lead_id','property_id','property_request_id','wa_id','contact_name','status','last_message_at'];
    protected $casts=['last_message_at'=>'datetime'];
    protected static function booted(): void { static::addGlobalScope(new TenantScope); }
    public function account(){ return $this->belongsTo(WhatsAppAccount::class,'whatsapp_account_id'); }
    public function lead(){ return $this->belongsTo(Lead::class); }
    public function property(){ return $this->belongsTo(Property::class); }
    public function propertyRequest(){ return $this->belongsTo(PropertyRequest::class); }
    public function messages(){ return $this->hasMany(WhatsAppMessage::class,'conversation_id'); }
}
