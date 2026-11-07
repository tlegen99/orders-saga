<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(CreateOrderRequest $request, OrderService $orderService): JsonResponse
    {
        $orderDto = $request->toDto();
        $order = $orderService->create($orderDto);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
