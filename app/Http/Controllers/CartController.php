<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $cart = $user->cart;

        if (! $cart) {
            return response()->json([
                'store_id' => null,
                'store_name' => null,
                'items' => [],
                'subtotal' => 0,
            ]);
        }

        $items = $cart->items()->with('product.store')->get();

        $formattedItems = $items->map(function ($item) {
            $prod = $item->product;

            return [
                'id' => $item->id,
                'product' => [
                    'id' => $prod->id,
                    'store_id' => $prod->store_id,
                    'store_name' => $prod->store?->store_name ?? 'Toko Tidak Dikenal',
                    'name' => $prod->name,
                    'price' => (float) $prod->price,
                    'stock' => (int) $prod->stock,
                    'image' => $prod->image ?? 'https://images.unsplash.com/photo-1546213290-e1b492ab3aea?auto=format&fit=crop&q=80&w=400',
                ],
                'quantity' => (int) $item->quantity,
                'item_subtotal' => (float) ($prod->price * $item->quantity),
            ];
        });

        $subtotal = $formattedItems->sum('item_subtotal');

        return response()->json([
            'store_id' => $cart->store_id,
            'store_name' => $cart->store?->store_name ?? 'Toko Tidak Dikenal',
            'items' => $formattedItems,
            'subtotal' => $subtotal,
        ]);
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'force' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $product = Product::findOrFail($validated['product_id']);
        $quantity = $validated['quantity'];
        $force = $validated['force'] ?? false;

        if ($product->stock < $quantity) {
            return response()->json(['message' => 'Stok tidak mencukupi.'], 400);
        }

        $result = DB::transaction(function () use ($user, $product, $quantity, $force) {
            $cart = $user->cart;

            if ($cart) {
                if ($cart->store_id !== $product->store_id) {
                    if ($force) {
                        $cart->items()->delete();
                        $cart->update(['store_id' => $product->store_id]);
                    } else {
                        return [
                            'conflict' => true,
                            'current_store' => $cart->store?->store_name ?? 'Toko Lain',
                        ];
                    }
                }
            } else {
                $cart = Cart::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'store_id' => $product->store_id,
                ]);
            }

            $item = $cart->items()->where('product_id', $product->id)->first();
            if ($item) {
                $newQty = $item->quantity + $quantity;
                if ($product->stock < $newQty) {
                    throw new \Exception('Stok tidak mencukupi untuk jumlah total yang diminta.');
                }
                $item->update(['quantity' => $newQty]);
            } else {
                $cart->items()->create([
                    'id' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);
            }

            return ['success' => true];
        });

        if (isset($result['conflict']) && $result['conflict']) {
            return response()->json([
                'message' => 'Keranjang Anda berisi produk dari toko '.$result['current_store'].'. Hapus keranjang dan tambahkan produk baru?',
                'conflict' => true,
                'current_store' => $result['current_store'],
            ], 409);
        }

        return response()->json(['message' => 'Produk berhasil ditambahkan ke keranjang.']);
    }

    public function updateItem(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $user = auth()->user();
        $cart = $user->cart;
        if (! $cart) {
            return response()->json(['message' => 'Keranjang tidak ditemukan.'], 404);
        }

        $item = $cart->items()->findOrFail($itemId);
        $quantity = $validated['quantity'];

        if ($quantity === 0) {
            $item->delete();
            if ($cart->items()->count() === 0) {
                $cart->delete();
            }

            return response()->json(['message' => 'Produk berhasil dihapus dari keranjang.']);
        }

        if ($item->product->stock < $quantity) {
            return response()->json(['message' => 'Stok tidak mencukupi.'], 400);
        }

        $item->update(['quantity' => $quantity]);

        return response()->json(['message' => 'Jumlah produk berhasil diperbarui.']);
    }

    public function removeItem($itemId)
    {
        $user = auth()->user();
        $cart = $user->cart;
        if (! $cart) {
            return response()->json(['message' => 'Keranjang tidak ditemukan.'], 404);
        }

        $item = $cart->items()->findOrFail($itemId);
        $item->delete();

        if ($cart->items()->count() === 0) {
            $cart->delete();
        }

        return response()->json(['message' => 'Produk berhasil dihapus dari keranjang.']);
    }

    public function clear()
    {
        $user = auth()->user();
        $cart = $user->cart;
        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        return response()->json(['message' => 'Keranjang berhasil dikosongkan.']);
    }
}
