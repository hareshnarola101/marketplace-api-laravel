<?php

namespace App\Http\Controllers\Vendor;

use App\Billing\VendorBalance;
use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\PayoutSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutSummaryController extends Controller
{
    public function __invoke(Request $request, VendorBalance $balance): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vendor = $user->approvedVendorOrFail();

        return (new PayoutSummaryResource($balance->summarize($vendor)))
            ->additional(['recent_entries' => LedgerEntryResource::collection(
                $vendor->ledgerEntries()->latest('id')->limit(20)->get(),
            )])
            ->response();
    }
}
