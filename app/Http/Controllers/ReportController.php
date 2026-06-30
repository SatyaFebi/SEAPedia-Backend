<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Buyer spending report / expense summary.
     */
    public function buyerReport()
    {
        $user = auth()->user();
        
        $orders = Order::where('buyer_id', $user->id)
            ->with(['store', 'items.product', 'statusHistory', 'discount'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalSpending = 0;
        $totalOrders = $orders->count();
        $completedOrdersCount = 0;
        
        foreach ($orders as $order) {
            $totalSpending += (float) $order->total_price;
            if ($order->status === 'Pesanan Selesai') {
                $completedOrdersCount++;
            }
        }

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'store_name' => $order->store?->store_name ?? 'Toko Tidak Dikenal',
                'status' => $order->status,
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'discount_code' => $order->discount?->code,
                'discount_type' => $order->discount?->type,
                'delivery_fee' => (float) $order->delivery_fee,
                'tax_amount' => (float) $order->tax_amount,
                'total' => (float) $order->total_price,
                'created_at' => $order->created_at->toDateTimeString(),
                'status_history' => $order->statusHistory->map(function ($hist) {
                    return [
                        'status' => $hist->status,
                        'timestamp' => $hist->created_at->toDateTimeString(),
                        'changed_by_role' => $hist->changed_by_role,
                    ];
                })->toArray(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->product?->name ?? 'Produk Terhapus',
                        'price' => (float) $item->price_at_checkout,
                        'quantity' => (int) $item->quantity,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'summary' => [
                'total_spending' => $totalSpending,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrdersCount,
            ],
            'orders' => $formattedOrders
        ]);
    }

    /**
     * Seller income report / revenue summary.
     */
    public function sellerReport()
    {
        $user = auth()->user();
        $store = $user->store;

        if (!$store) {
            return response()->json([
                'summary' => [
                    'total_income' => 0,
                    'total_orders' => 0,
                    'processed_orders' => 0,
                    'incoming_orders' => 0,
                ],
                'orders' => []
            ]);
        }

        $orders = Order::where('store_id', $store->id)
            ->with(['buyer', 'items.product', 'statusHistory', 'discount'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalIncome = 0;
        $processedCount = 0;
        $incomingCount = 0;

        foreach ($orders as $order) {
            // Seller income is items sold minus discounts
            if ($order->status !== 'Dikembalikan') {
                $totalIncome += (float) ($order->subtotal - $order->discount_amount);
            }
            
            if ($order->status === 'Sedang Dikemas') {
                $incomingCount++;
            } else {
                $processedCount++;
            }
        }

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'buyer_name' => $order->buyer?->name ?? 'Buyer Tidak Dikenal',
                'status' => $order->status,
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'discount_code' => $order->discount?->code,
                'discount_type' => $order->discount?->type,
                'delivery_fee' => (float) $order->delivery_fee,
                'tax_amount' => (float) $order->tax_amount,
                'total' => (float) $order->total_price,
                'created_at' => $order->created_at->toDateTimeString(),
                'status_history' => $order->statusHistory->map(function ($hist) {
                    return [
                        'status' => $hist->status,
                        'timestamp' => $hist->created_at->toDateTimeString(),
                        'changed_by_role' => $hist->changed_by_role,
                    ];
                })->toArray(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->product?->name ?? 'Produk Terhapus',
                        'price' => (float) $item->price_at_checkout,
                        'quantity' => (int) $item->quantity,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'summary' => [
                'total_income' => $totalIncome,
                'total_orders' => $orders->count(),
                'processed_orders' => $processedCount,
                'incoming_orders' => $incomingCount,
            ],
            'orders' => $formattedOrders
        ]);
    }
}
