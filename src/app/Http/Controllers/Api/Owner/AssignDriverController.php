<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Delivery\AssignDriverRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AssignDriverController extends ApiController
{
    public function __invoke(
        AssignDriverRequest $request,
        Order $order,
    ): JsonResponse {
        $driver = User::query()->findOrFail(
            $request->validated('driver_id'),
        );

        $delivery = Delivery::assignDriver(
            $request->user(),
            $order,
            $driver,
        );

        return $this->success(
            new DeliveryResource($delivery),
            'Driver berhasil ditugaskan.',
        );
    }
}
