<!DOCTYPE html>
<html>
<head>
    <title>Driver Account Credentials</title>
</head>
<body>
    <h2>Welcome {{ $name }}!</h2>
    <p>Your driver account has been created. Here are your login credentials:</p>
    
    <p><strong>Email:</strong> {{ $email }}</p>
    <p><strong>Password:</strong> {{ $password }}</p>
    
    <p>Please keep these credentials secure and change your password after your first login.</p>
    
    <p>Best regards,<br>Garbage Management System</p>
</body>
</html>