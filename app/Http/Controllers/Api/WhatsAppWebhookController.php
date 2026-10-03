<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppWebhookService;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        if ($request->query('hub_mode') !== 'subscribe' || !hash_equals((string) config('services.whatsapp.verify_token'), (string) $request->query('hub_verify_token'))) {
            abort(403);
        }
        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type','text/plain');
    }

    public function receive(Request $request, WhatsAppWebhookService $service)
    {
        $secret=(string) config('services.whatsapp.app_secret');
        if ($secret === '') abort(503, 'WhatsApp webhook is not configured.');

        $expected='sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) abort(401);

        $service->handle($request->json()->all());
        return response()->json(['ok'=>true]);
    }
}
