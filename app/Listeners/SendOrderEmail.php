<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderConfirmed $event): void
    {
        // Mock sending email
        Log::info("Sending confirmation email for order ID: {$event->order->id} to {$event->order->customer_email}");
    }
}
