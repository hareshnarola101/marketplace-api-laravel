<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrder;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return OrderResource::collection(
            $user->orders()->with(['vendorOrders.items', 'vendorOrders.vendor', 'invoice'])->latest('id')->paginate(15),
        );
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['vendorOrders.items', 'vendorOrders.vendor', 'invoice']));
    }

    public function cancel(Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($cancelOrder->handle($order)->load('vendorOrders.items'));
    }
}
