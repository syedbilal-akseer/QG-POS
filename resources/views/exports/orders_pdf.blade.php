<!DOCTYPE html>
<html>
<head>
    <title>Orders Export</title>
    <style>
        /* General table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
            font-weight: bold;
            color: #333;
        }

        tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f1f1f1;
        }

        /* Styling for order items */
        .order-items {

        }

        /* Item details styling */
        .item-detail {
            font-size: 12px;
            color: #555;
        }

        .item-description {
            font-weight: bold;
            color: #333;
        }

        .item-quantity,
        .item-uom,
        .item-discount,
        .item-subtotal {
            display: block;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
<h1>Orders Export</h1>

<table>
    <thead>
    <tr>
        <th>Sr No</th>
        <th>Order Number</th>
        <th>Customer Name</th>
        <th>Salesperson Name</th>
        <th>Order Items</th>
        <th>Order Status</th>
        <th>Discount</th>
        <th>Subtotal</th>
        <th>Total Amount</th>
        <th>Placed At</th>
        <th>Oracle At</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($orders as $index => $order)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->customer->customer_name }}</td>
            <td>{{ $order->salesperson->name }}</td>
            <td>
                <div class="order-items">
                    @foreach($order->orderItems as $orderItem)
                        <span class="item-description">{{ $orderItem->item->item_description }} (Qty: {{ $orderItem->quantity }}, UOM: {{ $orderItem->uom }}, Discount: {{ $orderItem->discount }}, Subtotal: {{ $orderItem->sub_total }})</span>
                        @if (!$loop->last)
                            <span>, </span>
                        @endif
                    @endforeach
                </div>
            </td>
            <td>{{ $order->order_status->name() }}</td>
            <td>{{ $order->discount }}</td>
            <td>{{ $order->sub_total }}</td>
            <td>{{ $order->total_amount }}</td>
            <td>{{ $order->created_at }}</td>
            <td>{{ $order->oracle_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
