<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Support optional filtering by store_id
        $query = Product::with('store');
        if ($request->has('store_id')) {
            $query->where('store_id', $request->query('store_id'));
        }

        $products = $query->get();

        $formatted = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'store_id' => $product->store_id,
                'store_name' => $product->store?->store_name ?? 'Toko Tidak Dikenal',
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
                'image' => $product->image ?? 'https://images.unsplash.com/photo-1546213290-e1b492ab3aea?auto=format&fit=crop&q=80&w=400',
                'category' => $product->category ?? 'Kuliner',
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $store = $user->store;

        if (! $store) {
            return response()->json(['message' => 'Anda harus membuat toko terlebih dahulu sebelum menambahkan produk.'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:1',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|string|max:2048',
            'category' => 'nullable|string|in:Kuliner,Otomotif',
        ]);

        $cleanName = strip_tags($validated['name']);
        $cleanDesc = isset($validated['description']) ? strip_tags($validated['description']) : null;

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'store_id' => $store->id,
            'name' => $cleanName,
            'description' => $cleanDesc,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image' => $validated['image'] ?? null,
            'category' => $validated['category'] ?? 'Kuliner',
        ]);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'product' => [
                'id' => $product->id,
                'store_id' => $product->store_id,
                'store_name' => $store->store_name,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
                'image' => $product->image,
                'category' => $product->category,
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with('store')->find($id);

        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'id' => $product->id,
            'store_id' => $product->store_id,
            'store_name' => $product->store?->store_name ?? 'Toko Tidak Dikenal',
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
            'image' => $product->image ?? 'https://images.unsplash.com/photo-1546213290-e1b492ab3aea?auto=format&fit=crop&q=80&w=400',
            'category' => $product->category ?? 'Kuliner',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        $store = $user->store;

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 400);
        }

        $product = Product::find($id);
        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        // Verify product ownership
        if ($product->store_id !== $store->id) {
            return response()->json(['message' => 'Forbidden: Produk ini bukan milik toko Anda.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:1',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|string|max:2048',
            'category' => 'nullable|string|in:Kuliner,Otomotif',
        ]);

        $cleanName = strip_tags($validated['name']);
        $cleanDesc = isset($validated['description']) ? strip_tags($validated['description']) : null;

        $product->update([
            'name' => $cleanName,
            'description' => $cleanDesc,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image' => $validated['image'] ?? null,
            'category' => $validated['category'] ?? 'Kuliner',
        ]);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'product' => [
                'id' => $product->id,
                'store_id' => $product->store_id,
                'store_name' => $store->store_name,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
                'image' => $product->image,
                'category' => $product->category,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = auth()->user();
        $store = $user->store;

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 400);
        }

        $product = Product::find($id);
        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        // Verify product ownership
        if ($product->store_id !== $store->id) {
            return response()->json(['message' => 'Forbidden: Produk ini bukan milik toko Anda.'], 403);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
