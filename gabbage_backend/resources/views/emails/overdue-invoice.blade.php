<!DOCTYPE html>
<html>
<head>
    <title>OVERDUE: Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .overdue-header { background: #dc3545; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .invoice-details { margin-bottom: 20px; }
        .amount { font-size: 24px; font-weight: bold; color: #dc3545; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="overdue-header">
        <h2>⚠️ PAYMENT OVERDUE NOTICE</h2>
        <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
        <p><strong>Original Due Date:</strong> {{ $invoice->due_date->format('F d, Y') }}</p>
    </div>

    <div class="warning">
        <h3>🚨 URGENT: Your payment is past due</h3>
        <p>Dear {{ $invoice->client->name }},</p>
        <p>This is a reminder that your invoice payment is now <strong>OVERDUE</strong>. Your grace period has expired and immediate payment is required to avoid service interruption.</p>
    </div>

    <div class="invoice-details">
        <h3>Invoice Details:</h3>
        <p><strong>Service:</strong> {{ $invoice->title }}</p>
        @if($invoice->description)
        <p><strong>Description:</strong> {{ $invoice->description }}</p>
        @endif
        <p class="amount"><strong>Amount Due:</strong> ${{ number_format($invoice->amount - $invoice->paid_amount, 2) }}</p>
        @if($invoice->paid_amount > 0)
        <p><strong>Amount Paid:</strong> ${{ number_format($invoice->paid_amount, 2) }}</p>
        @endif
        <p><strong>Account Number:</strong> {{ $client->accountNumber }}</p>
    </div>

    <div class="invoice-details">
        <h3>Payment Instructions:</h3>
        <p><strong>Pay via M-Pesa Paybill:</strong></p>
        <p>Business Number: <strong>{{ $organization->business_number ?? 'XXXXXX' }}</strong></p>
        <p>Account Number: <strong>{{ $client->accountNumber }}</strong></p>
        <p>Amount: <strong>${{ number_format($invoice->amount - $invoice->paid_amount, 2) }}</strong></p>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">
        <p><strong>⚠️ WARNING:</strong> Failure to pay within the next 7 days may result in service suspension and additional late fees.</p>
        <p>If you have already made payment, please disregard this notice. Contact us immediately if you have any questions.</p>
    </div>

    <p style="margin-top: 20px;">
        For assistance, contact us immediately:<br>
        <strong>{{ $organization->name }}</strong><br>
        Email: {{ $organization->email }}<br>
        Phone: {{ $organization->phone }}
    </p>
</body>
</html>