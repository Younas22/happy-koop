{{--
    Shared transactional email shell.

    Deliberately plain: a 600px single column, inline styles only, no external
    CSS and no web fonts, because Outlook and Gmail strip or ignore all three.
    The logo is an enhancement - the store name is always present as text, so
    the email reads correctly when images are blocked, which is the default in
    many clients.

    The accent is the store's own `theme.primary_color`, so an admin changing
    the brand colour changes these emails too.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subjectLine ?? $storeName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; -webkit-font-smoothing:antialiased;">
    @isset($preheader)
        {{-- Shown in the inbox preview, never on screen. --}}
        <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preheader }}</div>
    @endisset

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border:1px solid #e4e6eb; border-radius:12px; overflow:hidden; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                    <tr>
                        <td style="height:4px; background-color:{{ $brandColor }}; line-height:4px; font-size:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 8px 28px;">
                            @if (!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="32" style="height:32px; width:auto; max-width:200px; display:block; border:0;">
                            @else
                                <span style="font-size:20px; font-weight:700; color:#15181d; letter-spacing:-0.01em;">{{ $storeName }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 28px 28px; color:#15181d; font-size:15px; line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 24px 28px; border-top:1px solid #e4e6eb; color:#6b7280; font-size:12px; line-height:1.6;">
                            <p style="margin:0 0 6px 0;">{{ $storeName }}</p>
                            @if (!empty($supportEmail) || !empty($supportPhone))
                                <p style="margin:0 0 6px 0;">
                                    Need help?
                                    @if (!empty($supportEmail))
                                        <a href="mailto:{{ $supportEmail }}" style="color:{{ $brandColor }}; text-decoration:underline;">{{ $supportEmail }}</a>
                                    @endif
                                    @if (!empty($supportEmail) && !empty($supportPhone)) &middot; @endif
                                    @if (!empty($supportPhone)) {{ $supportPhone }} @endif
                                </p>
                            @endif
                            @if (!empty($storeUrl))
                                <p style="margin:0;"><a href="{{ $storeUrl }}" style="color:#6b7280; text-decoration:underline;">{{ $storeUrl }}</a></p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
