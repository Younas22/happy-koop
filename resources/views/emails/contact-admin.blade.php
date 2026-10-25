{{--
    Administrator notification for a contact-form submission.

    Everything a visitor typed is escaped by Blade, so a message containing
    HTML or a script tag arrives as text. The reply-to on the mailable is the
    visitor's address, so hitting Reply answers the right person.
--}}
@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 4px 0; font-size:19px; font-weight:700; color:#15181d;">New contact inquiry</h1>
    <p style="margin:0 0 20px 0; color:#6b7280; font-size:13px;">Reference {{ $inquiry->reference }} &middot; {{ $receivedAt }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        <tr>
            <td style="padding:8px 0; width:120px; color:#6b7280; vertical-align:top;">Name</td>
            <td style="padding:8px 0; color:#15181d;">{{ $inquiry->name }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0; color:#6b7280; vertical-align:top;">Email</td>
            <td style="padding:8px 0;"><a href="mailto:{{ $inquiry->email }}" style="color:{{ $brandColor }};">{{ $inquiry->email }}</a></td>
        </tr>
        @if ($inquiry->subject)
            <tr>
                <td style="padding:8px 0; color:#6b7280; vertical-align:top;">Subject</td>
                <td style="padding:8px 0; color:#15181d;">{{ $inquiry->subject }}</td>
            </tr>
        @endif
        @if ($inquiry->user_id)
            <tr>
                <td style="padding:8px 0; color:#6b7280; vertical-align:top;">Account</td>
                <td style="padding:8px 0; color:#15181d;">Signed in as customer #{{ $inquiry->user_id }}</td>
            </tr>
        @endif
    </table>

    <div style="margin:20px 0 0 0; padding:16px; background-color:#f8f9fb; border:1px solid #e4e6eb; border-radius:8px; white-space:pre-wrap; color:#15181d; font-size:14px; line-height:1.6;">{{ $inquiry->message }}</div>

    <p style="margin:20px 0 0 0; color:#6b7280; font-size:13px;">Reply directly to this email to answer the sender.</p>
@endsection
