<!DOCTYPE html>
<html>
<head>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .invoice-header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .invoice-details { margin-bottom: 20px; }
        .amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .due-date { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h2>{{ $invoice->title }}</h2>
        <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
        <p><strong>Date:</strong> {{ $invoice->created_at->format('F d, Y') }}</p>
    </div>

    <div class="invoice-details">
        <h3>Bill To:</h3>
        <p><strong>{{ $client->name }}</strong></p>
        <p>{{ $client->email }}</p>
        <p>{{ $client->phone }}</p>
        <p>{{ $client->adress }}</p>
    </div>

    <div class="invoice-details">
        <h3>From:</h3>
        <p><strong>{{ $organization->name }}</strong></p>
        <p>{{ $organization->email }}</p>
        <p>{{ $organization->phone }}</p>
    </div>

    <div class="invoice-details">
        <h3>Invoice Details:</h3>
        @if($invoice->description)
        <p><strong>Description:</strong> {{ $invoice->description }}</p>
        @endif
        <p class="amount"><strong>Amount Due:</strong> ${{ number_format($invoice->amount, 2) }}</p>
        <p class="due-date"><strong>Due Date:</strong> {{ $invoice->due_date->format('F d, Y') }}</p>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px;">
        <p><strong>Payment Instructions:</strong></p>
        <p>Please make payment by the due date to avoid any late fees. Contact us if you have any questions about this invoice.</p>
    </div>

    <p style="margin-top: 20px;">
        Thank you for your business!<br>
        <strong>{{ $organization->name }}</strong>
    </p>
</body>
</html>