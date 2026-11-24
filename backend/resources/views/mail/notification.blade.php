<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans Arabic', 'Segoe UI Emoji', sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 12px; border-collapse:collapse;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:14px; border-collapse:collapse; border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:#0f172a; padding:18px 26px; border-radius:14px 14px 0 0;">
                            <span style="color:#ffffff; font-size:17px; font-weight:bold; letter-spacing:0.2px;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 26px;">
                            <h1 style="margin:0 0 18px; font-size:20px; line-height:1.5; color:#0f172a; text-align:right;">{{ $heading }}</h1>

                            @foreach ($lines as $line)
                                <p style="margin:0 0 12px; font-size:15px; line-height:1.8; color:#334155; text-align:right;">{{ $line }}</p>
                            @endforeach

                            @if (! empty($actionUrl))
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px; border-collapse:collapse;">
                                    <tr>
                                        <td align="center" style="background-color:#0f172a; border-radius:9px;">
                                            <a href="{{ $actionUrl }}" style="display:inline-block; padding:13px 26px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:bold;">{{ $actionText }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 26px; background-color:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 14px 14px;">
                            <p style="margin:0; font-size:13px; line-height:1.7; color:#64748b; text-align:right;">{{ $footer }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
