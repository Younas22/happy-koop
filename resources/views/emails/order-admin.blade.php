{{--
    Administrator notification for a newly created order.

    The payment status is printed exactly as the backend recorded it. An order
    created before payment is confirmed says "unpaid" here: this email never
    implies money has arrived.
--}}
@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 4px 0; font-size:19px; font-weight:700; color:#15181d;">New order {{ $order->order_number }}</h1>
    <p style="margin:0 0 20px 0; color:#6b7280; font-size:13px;">Placed {{ $placedAt }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; font-size:14px;">
        <tr>
            <td style="padding:6px 0; width:140px; color:#6b7280;">Customer</td>
            <td style="padding:6px 0; color:#15181d;">{{ $customerName ?? $order->email }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">Email</td>
            <td style="padding:6px 0;"><a href="mailto:{{ $order->email }}" style="color:{{ $brandColor }};">{{ $order->email }}</a></td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">Order status</td>
            <td style="padding:6px 0; color:#15181d;">{{ ucfirst($order->status) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">Payment</td>
            <td style="padding:6px 0; color:#15181d;">
                {{ $paymentLabel }}
                @if ($order->payment_provider) &middot; {{ ucfirst($order->payment_provider) }} @endif
            </td>
        </tr>
    </table>

    @include('emails.partials.order-summary')

    @if ($adminUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 0 0;">
            <tr>
                <td style="background-color:{{ $brandColor }}; border-radius:8px;">
                    <a href="{{ $adminUrl }}" style="display:inline-block; padding:12px 22px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">Open the order in the admin</a>
                </td>
            </tr>
        </table>
    @endif

    @if ($shippingAddress)
        <p style="margin:24px 0 6px 0; color:#6b7280; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; font-weight:600;">Shipping to</p>
        <p style="margin:0; color:#15181d; font-size:14px; line-height:1.6;">{!! nl2br(e($shippingAddress)) !!}</p>
    @endif
@endsection
