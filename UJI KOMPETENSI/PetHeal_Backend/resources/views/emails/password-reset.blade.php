<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; background: #f8fafc; padding: 40px;">
    <div style="max-width: 480px; margin: 0 auto; background: white; border-radius: 16px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 24px;">
            <img src="{{ asset('/logo.png') }}" alt="PetHeal" style="height: 40px;">
            <h2 style="color: #0F172A; margin-top: 12px;">Password Reset Code</h2>
        </div>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">You requested a password reset. Use the code below to reset your password:</p>
        <div style="background: #F1F5F9; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0;">
            <span style="font-size: 32px; font-weight: 700; color: #10B981; letter-spacing: 8px;">{{ $code }}</span>
        </div>
        <p style="color: #94A3B8; font-size: 12px;">This code is valid for 60 minutes. If you didn't request this, please ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 20px 0;">
        <p style="color: #94A3B8; font-size: 11px; text-align: center;">PetHeal Veterinary Clinic</p>
    </div>
</body>
</html>
