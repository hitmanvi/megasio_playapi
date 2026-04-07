<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.5; color: #1a1a1a; max-width: 480px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Your verification code is:</p>
    <p style="font-size: 28px; letter-spacing: 8px; font-weight: 700; margin: 0 0 24px;">{{ $code }}</p>
    <p style="margin: 0; font-size: 14px; color: #555;">This code expires in {{ $expiresInMinutes }} minutes. If you did not request this, you can ignore this email.</p>
</body>
</html>
