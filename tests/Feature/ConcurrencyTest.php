<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_reservations_do_not_oversell()
    {
        // For testing concurrency in PHPUnit, it's tricky because PHP is single-threaded.
        // But we can simulate a race condition using database transactions and forcing a wait,
        // or we can test the lock isolation explicitly.

        $product = Product::factory()->create([
            'stock' => 1,
            'price' => 100.00,
        ]);

        $order1 = Order::factory()->create(['status' => 'pending']);
        OrderItem::factory()->create(['order_id' => $order1->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 0]);

        $order2 = Order::factory()->create(['status' => 'pending']);
        OrderItem::factory()->create(['order_id' => $order2->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 0]);

        // We run processOrder for both.
        // In a real environment, they run in parallel and lockForUpdate prevents overselling.
        // Here we run sequentially. Order 1 gets the stock, Order 2 fails.
        $service = new OrderService();
        $service->processOrder($order1->id);
        $service->processOrder($order2->id);

        $order1->refresh();
        $order2->refresh();
        $product->refresh();

        $this->assertEquals('confirmed', $order1->status);
        $this->assertEquals('failed', $order2->status);
        $this->assertEquals(0, $product->stock);
    }
}
