<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Discount;
use App\Models\DeliveryJob;

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
            'orders' => $formattedOrders,
        ]);
    }

    /**
     * Seller income report / revenue summary.
     */
    public function sellerReport()
    {
        $user = auth()->user();
        $store = $user->store;

        if (! $store) {
            return response()->json([
                'summary' => [
                    'total_income' => 0,
                    'total_orders' => 0,
                    'processed_orders' => 0,
                    'incoming_orders' => 0,
                ],
                'orders' => [],
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
            'orders' => $formattedOrders,
        ]);
    }

    public function adminDashboard()
    {
        $totalUsers = User::count();
        $totalStores = Store::count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();
        $totalVolume = Order::sum('total_price');

        // Check overdue specifically based on rules? We can just send raw counts.
        // Or calculate overdue dynamically.
        $offsetHours = \Illuminate\Support\Facades\Cache::get('simulated_offset_hours', 0);
        $simulatedNow = now()->addHours($offsetHours);

        $activeOrders = Order::whereNotIn('status', ['Pesanan Selesai', 'Dikembalikan'])
            ->whereNull('overdue_processed_at')
            ->get();
        $overdueCount = 0;

        foreach ($activeOrders as $order) {
            $isOverdue = false;
            $createdAt = clone $order->created_at;

            if ($order->delivery_method === 'Instant') {
                $deadline = clone $createdAt;
                $deadline->addHours(3);
                if ($simulatedNow->greaterThanOrEqualTo($deadline)) {
                    $isOverdue = true;
                }
            } elseif ($order->delivery_method === 'Next Day') {
                $cutOffTime = clone $createdAt;
                $cutOffTime->setTime(13, 0, 0);
                if ($createdAt->lessThan($cutOffTime)) {
                    $deadline = clone $createdAt;
                    $deadline->addDays(1)->endOfDay();
                } else {
                    $deadline = clone $createdAt;
                    $deadline->addDays(2)->endOfDay();
                }
                if ($simulatedNow->greaterThan($deadline)) {
                    $isOverdue = true;
                }
            } elseif ($order->delivery_method === 'Regular') {
                $deadline = clone $createdAt;
                $deadline->addDays(5)->endOfDay();
                if ($simulatedNow->greaterThan($deadline)) {
                    $isOverdue = true;
                }
            }
            if ($isOverdue) $overdueCount++;
        }

        return response()->json([
            'total_users' => $totalUsers,
            'total_stores' => $totalStores,
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_volume' => (float) $totalVolume,
            'overdue_count' => $overdueCount,
        ]);
    }

    public function adminOrders()
    {
        $orders = Order::with(['buyer', 'store', 'driver'])->orderBy('created_at', 'desc')->get();
        
        $offsetHours = \Illuminate\Support\Facades\Cache::get('simulated_offset_hours', 0);
        $simulatedNow = now()->addHours($offsetHours);

        $formatted = $orders->map(function ($order) use ($simulatedNow) {
            return [
                'id' => $order->id,
                'buyer_name' => $order->buyer?->name ?? 'Unknown',
                'store_name' => $order->store?->store_name ?? 'Unknown',
                'driver_name' => $order->driver?->name,
                'status' => $order->status,
                'delivery_method' => $order->delivery_method,
                'delivery_fee' => (float) $order->delivery_fee,
                'total' => (float) $order->total_price,
                'created_at' => $order->created_at->toDateTimeString(),
                'days_in_current_status' => (int) $order->updated_at->diffInDays($simulatedNow), // Using days for simplicity in UI
            ];
        });

        return response()->json($formatted);
    }

    public function adminDeliveryJobs()
    {
        $jobs = DeliveryJob::with(['order.store', 'driver'])->orderBy('created_at', 'desc')->get();
        return response()->json($jobs->map(function ($job) {
            return [
                'id' => $job->id,
                'order_id' => $job->order_id,
                'driver_name' => $job->driver?->name ?? 'Unknown',
                'status' => $job->status,
                'earned_amount' => (float) $job->earned_amount,
                'taken_at' => $job->taken_at,
                'completed_at' => $job->completed_at,
            ];
        }));
    }
}
