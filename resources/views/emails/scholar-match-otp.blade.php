
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarMatch Verification</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">

    <div style="max-width:480px;margin:auto;background:#ffffff;padding:30px;border-radius:14px;">

        <h2 style="color:#243b8a;">ScholarMatch</h2>

        <h3>Verify Your Email</h3>

        <p>Use the verification code below to continue:</p>

        <div style="text-align:center;background:#eef2ff;padding:20px;border-radius:10px;">
            <strong style="font-size:32px;letter-spacing:8px;color:#243b8a;">
                {{ $code }}
            </strong>
        </div>

        <p>This code expires in <strong>5 minutes</strong>.</p>

        <p>If you did not request this code, please ignore this email.</p>

        <p style="color:#777;font-size:12px;">
            Never share this verification code with anyone.
        </p>

    </div>

</body>
</html>
