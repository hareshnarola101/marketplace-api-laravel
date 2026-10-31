<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendors\ApplyAsVendor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorApplicationRequest;
use App\Http\Resources\VendorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationController extends Controller
{
    public function show(Request $request): VendorResource
    {
        /** @var User $user */
        $user = $request->user();

        return new VendorResource($user->vendor()->firstOrFail());
    }

    public function store(StoreVendorApplicationRequest $request, ApplyAsVendor $apply): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{store_name: string, description?: string|null} $data */
        $data = $request->validated();

        return (new VendorResource($apply->handle($user, $data)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
