Your order has been delivered

Hello{{ $customerName ? ' '.$customerName : '' }},

Order {{ $order->order_number }} is marked as delivered@if ($deliveredAt) on {{ $deliveredAt }}@endif.

@if ($order->carrier)Carrier: {{ $order->carrier }}
@endif
@if ($order->tracking_number)Tracking number: {{ $order->tracking_number }}
@endif
@if ($order->tracking_url){{ $order->tracking_url }}
@endif
Items:
@foreach ($lines as $line)
- {{ $line['name'] }}@if (!empty($line['variant'])) ({{ $line['variant'] }})@endif x{{ $line['quantity'] }} = {{ $line['lineTotal'] }}
@endforeach

@foreach ($totals as $total){{ $total['label'] }}: {{ $total['value'] }}
@endforeach

If anything is missing or damaged, reply to this email and we will help.

{{ $storeName }}
