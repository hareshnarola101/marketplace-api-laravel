<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Vendors\ReviewVendorApplication;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectVendorRequest;
use App\Http\Requests\Admin\UpdateCommissionRequest;
use App\Http\Resources\VendorResource;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['sometimes', Rule::enum(VendorStatus::class)]]);

        return VendorResource::collection(
            Vendor::query()
                ->with('user')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->oldest('id')
                ->paginate(25),
        );
    }

    public function approve(Request $request, Vendor $vendor, ReviewVendorApplication $review): VendorResource
    {
        /** @var User $admin */
        $admin = $request->user();

        return new VendorResource($review->approve($vendor, $admin)->load('user'));
    }

    public function reject(RejectVendorRequest $request, Vendor $vendor, ReviewVendorApplication $review): VendorResource
    {
        /** @var User $admin */
        $admin = $request->user();

        return new VendorResource($review->reject($vendor, $admin, $request->string('reason')->toString())->load('user'));
    }

    public function updateCommission(UpdateCommissionRequest $request, Vendor $vendor): VendorResource
    {
        $vendor->update(['commission_rate_bps' => $request->validated('commission_rate_bps')]);

        return new VendorResource($vendor->load('user'));
    }
}
