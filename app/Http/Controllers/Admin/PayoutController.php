<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payouts\RecordPayout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordPayoutRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PayoutController extends Controller
{
    public function store(RecordPayoutRequest $request, Vendor $vendor, RecordPayout $recordPayout): JsonResponse
    {
        $amount = Money::of($request->integer('amount'), config()->string('marketplace.currency'));

        return (new LedgerEntryResource($recordPayout->handle($vendor, $amount)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
