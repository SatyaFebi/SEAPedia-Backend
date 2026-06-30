<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\BuyerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'delivery_method' => 'required|string|in:Instant,Next Day,Regular',
            'shipping_address' => 'required|string|max:1000',
            'voucher_code' => 'nullable|string',
        ]);

        $user = auth()->user();
        $cart = $user->cart;

        if (!$cart || $cart->items()->count() === 0) {
            return response()->json(['message' => 'Keranjang belanja kosong.'], 400);
        }

        $deliveryMethod = $validated['delivery_method'];
        $shippingAddress = $validated['shipping_address'];
        $voucherCode = $request->input('voucher_code');

        // Delivery Fee calculation
        $deliveryFees = [
            'Regular' => 9000,
            'Next Day' => 15000,
            'Instant' => 30000
        ];
        $deliveryFee = $deliveryFees[$deliveryMethod];

        // Retrieve items to compute prices
        $cartItems = $cart->items()->with('product')->get();
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $subtotal += $item->product->price * $item->quantity;
        }

        // Apply voucher discount
        $discountAmount = 0;
        $deliveryFeeDiscount = 0;

        if ($voucherCode) {
            $code = strtoupper(trim($voucherCode));
            if ($code === 'SEAPEDIA10') {
                if ($subtotal >= 50000) {
                    $discountAmount = min($subtotal * 0.10, 50000);
                }
            } elseif ($code === 'HEMAT25') {
                if ($subtotal >= 100000) {
                    $discountAmount = min($subtotal * 0.25, 30000);
                }
            } elseif ($code === 'GRATISONGKIR') {
                if ($subtotal >= 75000) {
                    $deliveryFeeDiscount = min(15000, $deliveryFee);
                }
            }
        }

        $finalDeliveryFee = $deliveryFee - $deliveryFeeDiscount;
        $discountedSubtotal = $subtotal - $discountAmount;
        $ppnBase = max(0, $discountedSubtotal) + $finalDeliveryFee;
        $taxAmount = round($ppnBase * 0.12);
        $totalPrice = max(0, $discountedSubtotal) + $finalDeliveryFee + $taxAmount;

        // Perform validations
        $wallet = $user->wallet ?: BuyerWallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'balance' => 0
        ]);

        if ($wallet->balance < $totalPrice) {
            return response()->json(['message' => 'Saldo wallet tidak mencukupi. Silakan lakukan Top Up.'], 400);
        }

        // Check stock availability before updating
        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json(['message' => "Stok untuk produk '{$item->product->name}' tidak mencukupi."], 400);
            }
        }

        // Execute Checkout transaction
        try {
            $order = DB::transaction(function () use (
                $user, $cart, $cartItems, $totalPrice, $wallet,
                $subtotal, $discountAmount, $deliveryFeeDiscount, $deliveryFee, $taxAmount,
                $deliveryMethod, $shippingAddress
            ) {
                // Deduct wallet
                $wallet->balance -= $totalPrice;
                $wallet->save();

                // Create wallet transaction log
                $orderId = (string) Str::uuid();

                WalletTransaction::create([
                    'id' => (string) Str::uuid(),
                    'wallet_id' => $wallet->id,
                    'amount' => -$totalPrice,
                    'type' => 'PAYMENT',
                    'description' => "Pembayaran Pesanan #{$orderId}",
                ]);

                // Reduce stock safely (concurrency-safe atomic check & decrement)
                foreach ($cartItems as $item) {
                    $affected = Product::where('id', $item->product_id)
                        ->where('stock', '>=', $item->quantity)
                        ->decrement('stock', $item->quantity);

                    if ($affected === 0) {
                        throw new \Exception("Stok produk '{$item->product->name}' berubah atau tidak mencukupi.");
                    }
                }

                // Create Order
                $newOrder = Order::create([
                    'id' => $orderId,
                    'buyer_id' => $user->id,
                    'store_id' => $cart->store_id,
                    'delivery_method' => $deliveryMethod,
                    'status' => 'Sedang Dikemas',
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount + $deliveryFeeDiscount,
                    'delivery_fee' => $deliveryFee,
                    'tax_amount' => $taxAmount,
                    'total_price' => $totalPrice,
                    'shipping_address' => $shippingAddress,
                ]);

                // Create Order Items
                foreach ($cartItems as $item) {
                    OrderItem::create([
                        'id' => (string) Str::uuid(),
                        'order_id' => $newOrder->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price_at_checkout' => $item->product->price,
                    ]);
                }

                // Create Order Status History
                OrderStatusHistory::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $newOrder->id,
                    'status' => 'Sedang Dikemas',
                    'changed_by_role' => 'Buyer',
                ]);

                // Clear the Cart
                $cart->items()->delete();
                $cart->delete();

                return $newOrder;
            });

            return response()->json([
                'message' => 'Checkout berhasil.',
                'order' => $this->formatOrder($order),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function buyerOrders()
    {
        $user = auth()->user();
        $orders = Order::where('buyer_id', $user->id)
            ->with(['store', 'items.product', 'statusHistory'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders->map(fn($o) => $this->formatOrder($o)));
    }

    public function sellerOrders()
    {
        $user = auth()->user();
        $store = $user->store;

        if (!$store) {
            return response()->json([]);
        }

        $orders = Order::where('store_id', $store->id)
            ->with(['buyer', 'items.product', 'statusHistory'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders->map(fn($o) => $this->formatOrder($o)));
    }

    public function show($id)
    {
        $user = auth()->user();
        $order = Order::with(['buyer', 'store', 'items.product', 'statusHistory'])->findOrFail($id);

        // Authorization check: User must be buyer, seller/owner of the store, driver (if assigned), or Admin
        $isBuyer = $order->buyer_id === $user->id;
        $isSeller = $order->store?->user_id === $user->id;
        $isDriver = $order->driver_id === $user->id;
        $isAdmin = $user->roles()->where('role', 'Admin')->exists();

        if (!$isBuyer && !$isSeller && !$isDriver && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke pesanan ini.'], 403);
        }

        return response()->json($this->formatOrder($order));
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Sedang Dikemas,Menunggu Pengirim,Sedang Dikirim,Pesanan Selesai,Dikembalikan',
        ]);

        $newStatus = $validated['status'];
        $user = auth()->user();
        $activeRole = $request->header('X-Active-Role') ?: ($user->roles()->first()?->role);

        $order = Order::findOrFail($id);
        $currentStatus = $order->status;
        $isValid = false;

        if ($activeRole === 'Seller') {
            if ($order->store_id !== $user->store?->id) {
                return response()->json(['message' => 'Forbidden: Pesanan ini bukan milik toko Anda.'], 403);
            }

            if ($currentStatus === 'Sedang Dikemas' && $newStatus === 'Menunggu Pengirim') {
                $isValid = true;
            }
        }

        if ($activeRole === 'Driver') {
            if ($currentStatus === 'Menunggu Pengirim' && $newStatus === 'Sedang Dikirim') {
                $isValid = true;
                $order->driver_id = $user->id;
            } elseif ($currentStatus === 'Sedang Dikirim' && ($newStatus === 'Pesanan Selesai' || $newStatus === 'Dikembalikan')) {
                $isValid = true;
            }
        }

        if ($activeRole === 'Admin') {
            $isValid = true;
        }

        if (!$isValid) {
            return response()->json(['message' => 'Transisi status tidak valid untuk peran Anda.'], 400);
        }

        DB::transaction(function () use ($order, $newStatus, $activeRole) {
            $order->status = $newStatus;
            $order->save();

            $order->statusHistory()->create([
                'id' => (string) Str::uuid(),
                'status' => $newStatus,
                'changed_by_role' => $activeRole,
            ]);
        });

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'order' => $this->formatOrder($order),
        ]);
    }

    private function formatOrder($order)
    {
        return [
            'id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'buyer_name' => $order->buyer?->name ?? 'Buyer Tidak Dikenal',
            'store_id' => $order->store_id,
            'store_name' => $order->store?->store_name ?? 'Toko Tidak Dikenal',
            'driver_id' => $order->driver_id,
            'driver_name' => $order->driver?->name ?? null,
            'delivery_method' => $order->delivery_method,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'delivery_fee' => (float) $order->delivery_fee,
            'tax_amount' => (float) $order->tax_amount,
            'total' => (float) $order->total_price,
            'shipping_address' => $order->shipping_address,
            'created_at' => $order->created_at->toDateTimeString(),
            'status_history' => $order->statusHistory->map(function ($hist) {
                return [
                    'status' => $hist->status,
                    'timestamp' => $hist->created_at->toDateTimeString(),
                    'changed_by_role' => $hist->changed_by_role,
                ];
            })->toArray(),
            'items' => $order->items->map(function ($item) {
                $prod = $item->product;
                return [
                    'id' => $item->product_id,
                    'name' => $prod?->name ?? 'Produk Terhapus',
                    'price' => (float) $item->price_at_checkout,
                    'quantity' => (int) $item->quantity,
                    'image' => $prod?->image ?? 'https://images.unsplash.com/photo-1546213290-e1b492ab3aea?auto=format&fit=crop&q=80&w=400',
                ];
            })->toArray(),
        ];
    }
}
