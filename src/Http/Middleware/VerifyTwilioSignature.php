<?php

namespace Prasso\Messaging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow disabling in local/testing via env
        if (! (bool) env('TWILIO_VALIDATE_SIGNATURE', true)) {
            return $next($request);
        }

        $signature = $request->header('X-Twilio-Signature');
        if (empty($signature)) {
            return response('Missing signature', 403);
        }

        $authToken = config('twilio.auth_token') ?: env('TWILIO_AUTH_TOKEN');
        if (empty($authToken)) {
            return response('Twilio auth token not configured', 500);
        }

        $validator = new RequestValidator($authToken);

        // Twilio signs the full URL and POST params
        $url = $request->fullUrl();
        $params = $request->post();

        if (! $validator->validate($signature, $url, $params)) {
            return response('Invalid signature', 403);
        }

        return $next($request);
    }
}
