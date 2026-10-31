<?php

namespace App\Http\Controllers;

use App\Actions\Payments\HandlePaymentWebhook;
use App\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, HandlePaymentWebhook $handler): JsonResponse
    {
        // The raw body is required: re-encoding the parsed JSON would break the signature.
        $event = $gateway->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));

        $handler->handle($event);

        return response()->json(['received' => true]);
    }
}
