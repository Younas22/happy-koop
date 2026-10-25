{{--
    The order summary table.

    Every amount arrives already formatted by the mailable, using the order's
    own currency and the store locale - no money arithmetic and no formatting
    happens in a template.
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 4px 0; font-size:14px; border-collapse:collapse;">
    <tr>
        <th align="left" style="padding:0 0 8px 0; border-bottom:1px solid #e4e6eb; color:#6b7280; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; font-weight:600;">Item</th>
        <th align="right" style="padding:0 0 8px 0; border-bottom:1px solid #e4e6eb; color:#6b7280; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; font-weight:600;">Qty</th>
        <th align="right" style="padding:0 0 8px 0; border-bottom:1px solid #e4e6eb; color:#6b7280; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; font-weight:600;">Total</th>
    </tr>
    @foreach ($lines as $line)
        <tr>
            <td style="padding:12px 8px 12px 0; border-bottom:1px solid #f0f1f4; color:#15181d;">
                {{ $line['name'] }}
                @if (!empty($line['variant']))
                    <span style="display:block; color:#6b7280; font-size:13px;">{{ $line['variant'] }}</span>
                @endif
                @if (!empty($line['sku']))
                    <span style="display:block; color:#9ca3af; font-size:12px;">SKU {{ $line['sku'] }}</span>
                @endif
            </td>
            <td align="right" style="padding:12px 8px; border-bottom:1px solid #f0f1f4; color:#15181d; white-space:nowrap;">{{ $line['quantity'] }}</td>
            <td align="right" style="padding:12px 0 12px 8px; border-bottom:1px solid #f0f1f4; color:#15181d; white-space:nowrap;">{{ $line['lineTotal'] }}</td>
        </tr>
    @endforeach
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
    @foreach ($totals as $total)
        <tr>
            <td style="padding:6px 0; color:{{ $total['strong'] ? '#15181d' : '#6b7280' }}; {{ $total['strong'] ? 'font-weight:700;' : '' }}">{{ $total['label'] }}</td>
            <td align="right" style="padding:6px 0; color:#15181d; white-space:nowrap; {{ $total['strong'] ? 'font-weight:700; font-size:16px;' : '' }}">{{ $total['value'] }}</td>
        </tr>
    @endforeach
</table>
