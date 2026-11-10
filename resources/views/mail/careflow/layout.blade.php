<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background: #e9eef6; color: #12203a; font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif; }
        a { color: #2f6fed; }
        .preheader { display: none !important; visibility: hidden; opacity: 0; color: transparent; height: 0; width: 0; overflow: hidden; }
    </style>
</head>
<body style="margin:0;padding:0;background:#e9eef6;">
@if (! empty($preheader))
    <div class="preheader">{{ $preheader }}</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9eef6;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e9f2;border-radius:24px;overflow:hidden;box-shadow:0 18px 50px -36px rgba(15,23,42,0.35);">
                <tr>
                    <td style="background:linear-gradient(135deg,#2f6fed 0%,#4f8cff 100%);padding:28px 32px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div style="display:inline-block;width:36px;height:36px;border-radius:12px;background:rgba(255,255,255,0.18);color:#ffffff;text-align:center;line-height:36px;font-weight:700;font-size:18px;">+</div>
                                    <span style="margin-left:10px;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-0.02em;vertical-align:middle;">{{ config('app.name', 'CareFlow') }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-top:18px;">
                                    <p style="margin:0;color:rgba(255,255,255,0.82);font-size:12px;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;">
                                        {{ $eyebrow ?? 'International patient care' }}
                                    </p>
                                    <h1 style="margin:8px 0 0;color:#ffffff;font-size:24px;line-height:1.3;font-weight:700;letter-spacing:-0.03em;">
                                        {{ $title }}
                                    </h1>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @if (! empty($greeting))
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#12203a;font-weight:600;">
                                {{ $greeting }}
                            </p>
                        @endif

                        @foreach (($introLines ?? []) as $line)
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#3b4b66;">
                                {!! $line !!}
                            </p>
                        @endforeach

                        @if (! empty($meta) && is_array($meta))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;background:#f7f9fc;border:1px solid #e8edf5;border-radius:16px;">
                                @foreach ($meta as $label => $value)
                                    <tr>
                                        <td style="padding:12px 16px;border-bottom:1px solid #e8edf5;width:38%;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#66758f;">
                                            {{ $label }}
                                        </td>
                                        <td style="padding:12px 16px;border-bottom:1px solid #e8edf5;font-size:14px;font-weight:600;color:#12203a;">
                                            {{ $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if (! empty($actionText) && ! empty($actionUrl))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 8px;">
                                <tr>
                                    <td style="border-radius:999px;background:#2f6fed;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.01em;">
                                            {{ $actionText }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:#8a97ad;word-break:break-all;">
                                Or open this link:<br>
                                <a href="{{ $actionUrl }}" style="color:#2f6fed;">{{ $actionUrl }}</a>
                            </p>
                        @endif

                        @foreach (($outroLines ?? []) as $line)
                            <p style="margin:18px 0 0;font-size:14px;line-height:1.7;color:#66758f;">
                                {!! $line !!}
                            </p>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 28px;">
                        <div style="border-top:1px solid #e8edf5;padding-top:18px;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#8a97ad;">
                                {{ $organizationName ?? config('app.name') }}
                                @if (! empty($organizationContact))
                                    · {{ $organizationContact }}
                                @endif
                            </p>
                            <p style="margin:8px 0 0;font-size:11px;line-height:1.6;color:#9aa8c0;">
                                This message is for care coordination. It is not a medical diagnosis, prescription, or emergency alert.
                                If this email reached you by mistake, you can ignore it.
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
