<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification code</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 480px; margin: 0 auto; padding: 20px;">
    <p>Hi,</p>
    <p>Your verification code is:</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px; margin: 20px 0;">{{ $otp }}</p>
    <p>This code expires in 15 minutes. If you didn't request it, you can ignore this email.</p>
    <p style="color: #666; font-size: 14px; margin-top: 30px;">— {{ $appName }}</p>
</body>
</html>
