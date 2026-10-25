{{--
    Delivery confirmation.

    Sent only when the order actually enters the delivered state. The date
    comes from `delivered_at`, recorded at that transition, and tracking
    details appear only when the store entered them.
--}}
@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:19px; font-weight:700; color:#15181d;">Your order has been delivered</h1>

    <p style="margin:0 0 16px 0;">Hello{{ $customerName ? ' '.$customerName : '' }},</p>
    <p style="margin:0 0 20px 0;">Order <strong>{{ $order->order_number }}</strong> is marked as delivered@if ($deliveredAt) on {{ $deliveredAt }}@endif.</p>

    @if ($order->carrier || $order->tracking_number)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; font-size:14px;">
            <tr>
                <td style="padding:12px 16px; background-color:#f8f9fb; border:1px solid #e4e6eb; border-radius:8px;">
                    @if ($order->carrier)Carrier: {{ $order->carrier }}<br>@endif
                    @if ($order->tracking_number)Tracking number: {{ $order->tracking_number }}@endif
                    @if ($order->tracking_url)<br><a href="{{ $order->tracking_url }}" style="color:{{ $brandColor }};">Track this shipment</a>@endif
                </td>
            </tr>
        </table>
    @endif

    @include('emails.partials.order-summary')

    <p style="margin:24px 0 0 0;">If anything is missing or damaged, reply to this email and we will help.</p>

    @if ($orderUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 0 0;">
            <tr>
                <td style="background-color:{{ $brandColor }}; border-radius:8px;">
                    <a href="{{ $orderUrl }}" style="display:inline-block; padding:12px 22px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">View your order</a>
                </td>
            </tr>
        </table>
    @endif
@endsection
