New order {{ $order->order_number }}
Placed: {{ $placedAt }}

Customer: {{ $customerName ?? $order->email }}
Email: {{ $order->email }}
Order status: {{ ucfirst($order->status) }}
Payment: {{ $paymentLabel }}

Items:
@foreach ($lines as $line)
- {{ $line['name'] }}@if (!empty($line['variant'])) ({{ $line['variant'] }})@endif x{{ $line['quantity'] }} = {{ $line['lineTotal'] }}
@endforeach

@foreach ($totals as $total){{ $total['label'] }}: {{ $total['value'] }}
@endforeach
@if ($adminUrl)
Open in admin: {{ $adminUrl }}
@endif
@if ($shippingAddress)
Shipping to:
{{ $shippingAddress }}
@endif
