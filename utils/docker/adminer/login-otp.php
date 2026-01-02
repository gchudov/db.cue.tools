<?php
require_once("plugins/login-otp.php");

return new AdminerLoginOtp(
        $secret = base64_decode(getenv('ADMINER_OTP_SECRET'))
);

