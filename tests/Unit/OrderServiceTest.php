<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\OrderService;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService();
    }

    public function test_process_order_success()
    {
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'stock' => 10,
            'price' => 50.00,
        ]);

        $order = Order::factory()->create(['status' => 'pending']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 0,
        ]);

        $this->orderService->processOrder($order->id);

        $order->refresh();
        $product->refresh();

        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals(100.00, $order->total_amount);
        $this->assertEquals(8, $product->stock); // 10 - 2
    }

    public function test_process_order_insufficient_stock()
    {
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'stock' => 5,
            'price' => 50.00,
        ]);

        $order = Order::factory()->create(['status' => 'pending']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10, // More than available stock
            'price' => 0,
        ]);

        $this->orderService->processOrder($order->id);

        $order->refresh();
        $product->refresh();

        $this->assertEquals('failed', $order->status);
        $this->assertEquals(0, $order->total_amount);
        $this->assertStringContainsString('Insufficient stock', $order->fail_reason);
        $this->assertEquals(5, $product->stock); // Stock shouldn't be deducted
    }
}
