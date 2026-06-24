<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\ProcessOrderJob;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = $this->orderService->createPendingOrder(
            [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
            ],
            $validated['items']
        );

        ProcessOrderJob::dispatch($order->id);

        return response()->json([
            'message' => 'Order created and is pending processing.',
            'order_id' => $order->id,
            'status' => $order->status,
        ], 202);
    }

    public function show(int $id)
    {
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return new OrderResource($order);
    }
}
