<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Your sign-in code</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f1eb;color:#1c2220;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Your secure one-time code for the Don't Stuck Solutions client portal.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f3f1eb;">
        <tr>
            <td align="center" style="padding:34px 14px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e7e3da;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="height:6px;background-color:#70252b;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:24px 30px;border-bottom:1px solid #eeeae2;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td valign="middle" style="padding-right:14px;">
                                        <img src="{{ $message->embed(public_path('images/dss-emblem.png')) }}" width="76" height="38" alt="DSS" style="display:block;width:76px;height:38px;border:0;outline:none;text-decoration:none;object-fit:contain;">
                                    </td>
                                    <td valign="middle" style="font-family:Arial,Helvetica,sans-serif;">
                                        <div style="margin:0 0 4px;color:#a07400;font-size:10px;font-weight:bold;letter-spacing:2px;line-height:1.3;">ENRICHING LIVES</div>
                                        <div style="color:#1c2220;font-size:14px;font-weight:bold;letter-spacing:.3px;line-height:1.3;">DON'T STUCK SOLUTIONS</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 30px 12px;">
                            @if ($previewOnly)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;background-color:#fff5d8;border:1px solid #f2d989;border-radius:7px;">
                                    <tr>
                                        <td style="padding:12px 14px;color:#634b09;font-size:13px;line-height:1.5;">
                                            <strong>Visual test only.</strong> This code is invalid and cannot be used to sign in.
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:0 0 9px;color:#8d6a00;font-size:11px;font-weight:bold;letter-spacing:1.8px;line-height:1.4;text-transform:uppercase;">Client portal security</p>
                            <h1 style="margin:0 0 12px;color:#1c2220;font-size:27px;font-weight:700;letter-spacing:-.5px;line-height:1.2;">Your sign-in code</h1>
                            <p style="margin:0;color:#5f6864;font-size:15px;line-height:1.65;">Hello, use this one-time code to securely access your Don't Stuck Solutions client account.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 30px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f8f7f2;border:1px solid #e8e2d5;border-radius:9px;">
                                <tr>
                                    <td align="center" style="padding:21px 12px 18px;">
                                        <div style="color:#727a75;font-size:10px;font-weight:bold;letter-spacing:2px;line-height:1.4;text-transform:uppercase;">Your secure code</div>
                                        <div style="padding-top:8px;color:#202522;font-family:Arial,Helvetica,sans-serif;font-size:35px;font-weight:bold;letter-spacing:10px;line-height:1.35;">{{ $code }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 30px 28px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#70252b" style="border-radius:6px;">
                                        <a href="{{ $portalUrl }}" style="display:inline-block;padding:13px 22px;border:1px solid #70252b;border-radius:6px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;line-height:1.2;text-decoration:none;">Open the client portal</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 30px 26px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #eeeae2;">
                                <tr>
                                    <td style="padding-top:18px;color:#5f6864;font-size:13px;line-height:1.65;">
                                        This code expires in <strong style="color:#1c2220;">{{ $expiresMinutes }} minutes</strong> and can only be used once. Never share it with anyone.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:11px;color:#737b77;font-size:12px;line-height:1.6;">
                                        If you didn't request a sign-in code, you can safely ignore this email. Need help? <a href="mailto:{{ $supportEmail }}" style="color:#70252b;font-weight:bold;text-decoration:underline;">Contact our team</a>.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:17px 24px;background-color:#171d1b;color:#c4cbc6;font-size:11px;line-height:1.6;">
                            <span style="color:#f1c232;font-weight:bold;letter-spacing:1px;">ENRICHING LIVES</span><br>
                            Don't Stuck Solutions Limited · Nairobi, Kenya
                        </td>
                    </tr>
                </table>
                <p style="margin:15px 0 0;color:#8a918d;font-size:11px;line-height:1.5;">This is an automated security email. Please don't reply directly.</p>
            </td>
        </tr>
    </table>
</body>
</html>
