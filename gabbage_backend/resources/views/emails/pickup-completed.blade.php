<!DOCTYPE html>
<html>
<head>
    <title>Garbage Collection Completed</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { background: #28a745; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .content { margin-bottom: 20px; line-height: 1.6; }
        .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success-icon { font-size: 48px; color: #28a745; text-align: center; margin: 20px 0; }
        .footer { margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>✅ Garbage Collection Completed</h2>
        <p>Your waste has been successfully collected</p>
    </div>

    <div class="success-icon">
        🗑️✅
    </div>

    <div class="content">
        <p>Dear <strong>{{ $client->name }}</strong>,</p>
        
        <p>We are pleased to inform you that your garbage collection service has been completed successfully.</p>
        
        <div class="details">
            <h3>Collection Details:</h3>
            <p><strong>Date:</strong> {{ $pickup->pickup_date->format('l, F d, Y') }}</p>
            <p><strong>Time:</strong> {{ $pickup->picked_at->format('g:i A') }}</p>
            @if($driver)
            <p><strong>Collected by:</strong> {{ $driver->name }}</p>
            @endif
            @if($route)
            <p><strong>Route:</strong> {{ $route->name }}</p>
            @endif
            <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">COMPLETED</span></p>
        </div>

        <p>Your waste has been properly collected and will be disposed of in accordance with environmental regulations.</p>
        
        <p><strong>Next Collection:</strong> Your next scheduled collection will be on your regular pickup day. Please ensure your waste is ready for collection by 6:00 AM on your designated pickup day.</p>
    </div>

    <div class="footer">
        <h4>Important Reminders:</h4>
        <ul>
            <li>Please have your waste ready by 6:00 AM on your pickup day</li>
            <li>Ensure waste is properly bagged and secured</li>
            <li>Separate recyclables when possible</li>
            <li>Contact us if you have any special collection requirements</li>
        </ul>
        
        <p style="margin-top: 20px;">
            <strong>Questions or concerns?</strong><br>
            Contact us at: <a href="mailto:support@garbagecollection.com">support@garbagecollection.com</a><br>
            Phone: +254 700 000 000
        </p>
        
        <p style="margin-top: 15px; text-align: center; color: #666;">
            Thank you for choosing our garbage collection service!<br>
            <strong>Professional Waste Management Solutions</strong>
        </p>
    </div>
</body>
</html>