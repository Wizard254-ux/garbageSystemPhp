<!DOCTYPE html>
<html>
<head>
    <title>Organization Account Created</title>
</head>
<body>
    <h2>Welcome {{ $name }}!</h2>
    
    <p>Your organization account has been created successfully.</p>
    
    <p><strong>Login Credentials:</strong></p>
    <ul>
        <li><strong>Email:</strong> {{ $email }}</li>
        <li><strong>Password:</strong> {{ $password }}</li>
    </ul>
    
    <p>Please login and change your password immediately for security.</p>
    
    <p>Best regards,<br>{{ config('app.name') }}</p>
</body>
</html>