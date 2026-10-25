{{--
    Visitor acknowledgement.

    It confirms receipt and quotes the reference. It promises NO response time:
    the store has not configured one, and inventing "within 24 hours" would be
    a commitment the business never made.
--}}
@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:19px; font-weight:700; color:#15181d;">We received your message</h1>

    <p style="margin:0 0 16px 0;">Hello {{ $inquiry->name }},</p>
    <p style="margin:0 0 16px 0;">Thank you for contacting {{ $storeName }}. Your message has reached our team and we will reply to this email address.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0;">
        <tr>
            <td style="padding:12px 16px; background-color:#f8f9fb; border:1px solid #e4e6eb; border-radius:8px; font-size:14px;">
                Your reference: <strong style="color:#15181d;">{{ $inquiry->reference }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px 0; color:#6b7280; font-size:13px;">For your records, this is what you sent:</p>
    @if ($inquiry->subject)
        <p style="margin:0 0 8px 0; font-size:14px;"><strong>{{ $inquiry->subject }}</strong></p>
    @endif
    <div style="margin:0; padding:16px; background-color:#f8f9fb; border:1px solid #e4e6eb; border-radius:8px; white-space:pre-wrap; color:#15181d; font-size:14px; line-height:1.6;">{{ $inquiry->message }}</div>
@endsection
