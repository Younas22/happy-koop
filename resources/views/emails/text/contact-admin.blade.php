New contact inquiry
Reference: {{ $inquiry->reference }}
Received: {{ $receivedAt }}

Name: {{ $inquiry->name }}
Email: {{ $inquiry->email }}
@if ($inquiry->subject)Subject: {{ $inquiry->subject }}
@endif

Message:
{{ $inquiry->message }}

Reply directly to this email to answer the sender.
{{ $storeName }}
