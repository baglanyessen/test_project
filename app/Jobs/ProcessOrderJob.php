<?php

namespace App\Jobs;

use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
}
