<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\PlaceOrder;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $placeOrder->handle($user, $request->idempotencyKey());

        return (new OrderResource($result->order->load(['vendorOrders.items', 'vendorOrders.vendor'])))
            ->additional(['payment' => $result->paymentIntent ? [
                'provider_reference' => $result->paymentIntent->id,
                'client_secret' => $result->paymentIntent->clientSecret,
                'status' => $result->paymentIntent->status,
            ] : null])
            ->response()
            ->setStatusCode($result->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK)
            ->header('Idempotent-Replayed', $result->wasCreated ? 'false' : 'true');
    }
}
