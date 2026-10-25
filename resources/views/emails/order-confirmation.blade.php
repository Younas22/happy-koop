{{--
    Customer order confirmation.

    It confirms the ORDER, not the payment: the payment line reflects the state
    the backend verified, and there is no delivery estimate, because nothing in
    this system records one.
--}}
@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:19px; font-weight:700; color:#15181d;">Thank you for your order</h1>

    <p style="margin:0 0 16px 0;">Hello{{ $customerName ? ' '.$customerName : '' }},</p>
    <p style="margin:0 0 20px 0;">We have received your order. Here is what you ordered.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; font-size:14px;">
        <tr>
            <td style="padding:12px 16px; background-color:#f8f9fb; border:1px solid #e4e6eb; border-radius:8px;">
                Order number: <strong style="color:#15181d;">{{ $order->order_number }}</strong><br>
                Payment: {{ $paymentLabel }}
            </td>
        </tr>
    </table>

    @include('emails.partials.order-summary')

    @if ($orderUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 0 0;">
            <tr>
                <td style="background-color:{{ $brandColor }}; border-radius:8px;">
                    <a href="{{ $orderUrl }}" style="display:inline-block; padding:12px 22px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">View your order</a>
                </td>
            </tr>
        </table>
    @endif

    @if ($shippingAddress)
        <p style="margin:24px 0 6px 0; color:#6b7280; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; font-weight:600;">Shipping to</p>
        <p style="margin:0; color:#15181d; font-size:14px; line-height:1.6;">{!! nl2br(e($shippingAddress)) !!}</p>
    @endif

    @if ($shippingMethod)
        <p style="margin:16px 0 0 0; color:#6b7280; font-size:13px;">Shipping method: {{ $shippingMethod }}</p>
    @endif
@endsection
