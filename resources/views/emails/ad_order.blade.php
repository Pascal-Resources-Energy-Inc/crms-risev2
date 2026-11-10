<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Order</title>
</head>
<body style="font-family: Arial; background:#f5f5f5; padding:20px;">

    <div style="max-width:600px;margin:auto;background:#fff;padding:20px;border-radius:8px;">
        
        <h2 style="color:#28a745;">New Order</h2>

        <p>Hello <strong>{{ $ad->user->name ?? 'Supplier' }}</strong>,</p>

        <p>You have received a new order.</p>

        <hr>

        <h4>Dealer Information</h4>
        <p><strong>Dealer Name:</strong> {{ $dealer->name }}</p>
        <p><strong>Email:</strong> {{ $dealer->email }}</p>

        <hr>

        <h4>Order Details</h4>
        <p><strong>Transaction ID:</strong> {{ $transaction->transaction_id }}</p>
        <p><strong>Product:</strong> {{ $item->product_name }}</p>
        <p><strong>Quantity:</strong> {{ $transaction->qty }}</p>
        <p><strong>Price:</strong> ₱{{ number_format($transaction->price, 2) }}</p>
        <p><strong>Total:</strong> ₱{{ number_format($transaction->price * $transaction->qty, 2) }}</p>

        <hr>

        {{-- <p><strong>Payment Method:</strong> {{ ucfirst($transaction->payment_method) }}</p> --}}
        <p><strong>Payment Method:</strong> {{ ucwords(str_replace('_', ' ', $transaction->payment_method)) }} </p>
        
        <p><strong>Delivery Type:</strong> {{ ucfirst($transaction->delivery_type) }}</p>

        <hr>

        <p style="color:#777;">Please login to your dashboard to process this order.</p>
        <br>

        <p>Thank you,<br>Your System</p>

    </div>

</body>
</html>
