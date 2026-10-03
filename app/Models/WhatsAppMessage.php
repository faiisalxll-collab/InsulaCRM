<?php
namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $fillable=['tenant_id','conversation_id','wamid','direction','type','body','referral_source_type','referral_source_id','referral_source_url','ctwa_clid','payload','sent_at'];
    protected $casts=['payload'=>'array','sent_at'=>'datetime'];
    protected static function booted(): void { static::addGlobalScope(new TenantScope); }
    public function conversation(){ return $this->belongsTo(WhatsAppConversation::class,'conversation_id'); }
}
