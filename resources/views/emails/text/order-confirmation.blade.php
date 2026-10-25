Thank you for your order

Hello{{ $customerName ? ' '.$customerName : '' }},

We have received your order.

Order number: {{ $order->order_number }}
Payment: {{ $paymentLabel }}

Items:
@foreach ($lines as $line)
- {{ $line['name'] }}@if (!empty($line['variant'])) ({{ $line['variant'] }})@endif x{{ $line['quantity'] }} = {{ $line['lineTotal'] }}
@endforeach

@foreach ($totals as $total){{ $total['label'] }}: {{ $total['value'] }}
@endforeach
@if ($orderUrl)
View your order: {{ $orderUrl }}
@endif
@if ($shippingAddress)
Shipping to:
{{ $shippingAddress }}
@endif

{{ $storeName }}
