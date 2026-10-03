<?php
namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    protected $fillable = ['tenant_id','waba_id','phone_number_id','display_phone_number','status','coexistence','access_token','connected_at','last_webhook_at','meta'];
    protected $hidden = ['access_token'];
    protected $casts = ['coexistence'=>'boolean','access_token'=>'encrypted','connected_at'=>'datetime','last_webhook_at'=>'datetime','meta'=>'array'];
    protected static function booted(): void { static::addGlobalScope(new TenantScope); }
    public function tenant(){ return $this->belongsTo(Tenant::class); }
    public function conversations(){ return $this->hasMany(WhatsAppConversation::class); }
}
