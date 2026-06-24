<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    public function createPendingOrder(array $customerData, array $items): Order
    {
        return DB::transaction(function () use ($customerData, $items) {
            $order = Order::create([
                'customer_name' => $customerData['customer_name'],
                'customer_email' => $customerData['customer_email'] ?? null,
                'status' => 'pending',
                'total_amount' => 0,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => 0,
                ]);
            }

            return $order;
        });
    }

    public function processOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::with('items')->find($orderId);

            if (!$order) {
                return;
            }

            if ($order->status !== 'pending') {
                return;
            }

            $productIds = $order->items->pluck('product_id')->toArray();
            sort($productIds);

            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $totalAmount = 0;
            $failReason = null;

            foreach ($order->items as $item) {
                $product = $products->get($item->product_id);

                if (!$product) {
                    $failReason = "Product ID {$item->product_id} not found.";
                    break;
                }

                if ($product->stock < $item->quantity) {
                    $failReason = "Insufficient stock for product: {$product->name}. Requested: {$item->quantity}, Available: {$product->stock}.";
                    break;
                }

                $totalAmount += $product->price * $item->quantity;
            }

            if ($failReason) {
                $order->update([
                    'status' => 'failed',
                    'fail_reason' => $failReason,
                ]);
                return;
            }

            foreach ($order->items as $item) {
                $product = $products->get($item->product_id);
                $product->stock -= $item->quantity;
                $product->save();

                $item->update([
                    'price' => $product->price,
                ]);
            }

            $order->update([
                'status' => 'confirmed',
                'total_amount' => $totalAmount,
            ]);

            event(new \App\Events\OrderConfirmed($order));
        });
    }
}
