We received your message

Hello {{ $inquiry->name }},

Thank you for contacting {{ $storeName }}. Your message has reached our team
and we will reply to this email address.

Your reference: {{ $inquiry->reference }}

For your records, this is what you sent:
@if ($inquiry->subject){{ $inquiry->subject }}
@endif
{{ $inquiry->message }}

{{ $storeName }}
