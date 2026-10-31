<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class VendorOrderReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly VendorOrder $vendorOrder) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $vendorOrder = $this->vendorOrder->loadMissing('items', 'order');

        $mail = (new MailMessage)
            ->subject("New paid order #{$vendorOrder->order_id}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Order #{$vendorOrder->order_id} has been paid and is ready to ship:");

        foreach ($vendorOrder->items as $item) {
            $mail->line("{$item->quantity} × {$item->product_name} ({$item->variant_name}, {$item->sku})");
        }

        return $mail->line('Your earnings after commission: '.$vendorOrder->money($vendorOrder->vendor_earnings)->format().' '.$vendorOrder->order->currency);
    }
}
