<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Models\Product;
use App\Jobs\ProcessOrderJob;
use App\Models\Order;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_order()
    {
        Queue::fake();

        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 100.00,
        ]);

        $response = $this->postJson('/orders', [
            'customer_name' => 'John Doe',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $response->assertStatus(202)
                 ->assertJsonStructure(['message', 'order_id', 'status']);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'John Doe',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessOrderJob::class);
    }

    public function test_can_get_order_status()
    {
        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total_amount' => 200.00,
        ]);

        $response = $this->getJson('/orders/' . $order->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'id' => $order->id,
                     'status' => 'confirmed',
                     'total_amount' => "200.00",
                 ]);
    }
}
