<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorOrderResource;
use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return VendorOrderResource::collection(
            $user->approvedVendorOrFail()->vendorOrders()
                ->with(['order', 'items'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->latest('id')
                ->paginate(15),
        );
    }

    public function ship(VendorOrder $vendorOrder): VendorOrderResource
    {
        Gate::authorize('ship', $vendorOrder);

        $vendorOrder->markAsShipped();

        return new VendorOrderResource($vendorOrder->load(['order', 'items']));
    }
}
