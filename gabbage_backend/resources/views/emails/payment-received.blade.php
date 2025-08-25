<!DOCTYPE html>
<html>
<head>
    <title>Payment Received</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { background: #28a745; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .content { margin-bottom: 20px; line-height: 1.6; }
        .payment-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .footer { margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>✅ Payment Received</h2>
        <p>Thank you for your payment</p>
    </div>

    <div class="content">
        <p>Dear <strong>{{ $client->name }}</strong>,</p>
        
        <p>We have successfully received your payment. Thank you for your prompt payment.</p>
        
        <div class="payment-details">
            <h3>Payment Details:</h3>
            <p class="amount"><strong>Amount Received:</strong> ${{ number_format($payment->amount, 2) }}</p>
            <p><strong>Payment Method:</strong> {{ $paymentMethod }}</p>
            <p><strong>Transaction ID:</strong> {{ $payment->trans_id }}</p>
            <p><strong>Date & Time:</strong> {{ $payment->trans_time->format('F d, Y \a\t g:i A') }}</p>
            @if($payment->status === 'fully_allocated')
            <p><strong>Status:</strong> <span style="color: #28a745;">Applied to your account</span></p>
            @elseif($payment->status === 'partially_allocated')
            <p><strong>Status:</strong> <span style="color: #ffc107;">Partially applied to your account</span></p>
            <p><strong>Remaining Credit:</strong> ${{ number_format($payment->remaining_amount, 2) }}</p>
            @endif
        </div>

        @if($payment->status === 'fully_allocated')
        <p>Your payment has been successfully applied to your outstanding invoices. Your account is now up to date.</p>
        @elseif($payment->status === 'partially_allocated')
        <p>Your payment has been applied to your outstanding invoices. Any remaining credit will be applied to future invoices.</p>
        @else
        <p>Your payment has been received and will be applied to your account shortly.</p>
        @endif
    </div>

    <div class="footer">
        <p><strong>Need assistance?</strong><br>
        Contact us at: <a href="mailto:support@garbagecollection.com">support@garbagecollection.com</a><br>
        Phone: +254 700 000 000</p>
        
        <p style="margin-top: 15px; text-align: center; color: #666;">
            Thank you for choosing our garbage collection service!<br>
            <strong>Professional Waste Management Solutions</strong>
        </p>
    </div>
</body>
</html>