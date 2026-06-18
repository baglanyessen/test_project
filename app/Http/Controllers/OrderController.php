<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
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

    public function show(int $id): JsonResponse
    {
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json([
            'id' => $order->id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'fail_reason' => $order->fail_reason,
            'items' => $order->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ];
            }),
        ]);
    }
}
