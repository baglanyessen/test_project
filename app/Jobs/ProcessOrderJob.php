<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;

    public int $tries = 3;

    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle(OrderService $orderService): void
    {
        $orderService->processOrder($this->orderId);
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::find($this->orderId);

        if ($order && $order->status === 'pending') {
            $order->update([
                'status' => 'failed',
                'fail_reason' => 'Processing failed after all retry attempts: ' . $exception->getMessage(),
            ]);
        }
    }
}
