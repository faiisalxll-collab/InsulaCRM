<?php
namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;

class WhatsAppSettingsController extends Controller
{
    public function index()
    {
        $tenant=auth()->user()->tenant;
        abort_unless(($tenant->business_mode ?? 'wholesale') === 'realestate',404);
        $account=WhatsAppAccount::withoutGlobalScopes()->where('tenant_id',$tenant->id)->first();
        return view('settings.whatsapp',[
            'account'=>$account,
            'embeddedSignupReady'=>filled(config('services.whatsapp.app_id')) && filled(config('services.whatsapp.config_id')),
        ]);
    }
}
