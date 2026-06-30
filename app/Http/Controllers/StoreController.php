<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    /**
     * Create or update user's store.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // Verify the user has the Seller role
        $isSeller = $user->roles()->where('role', 'Seller')->exists();
        if (! $isSeller) {
            return response()->json(['message' => 'Forbidden: Only sellers can manage stores.'], 403);
        }

        $request->validate([
            'store_name' => 'required|string|max:255',
        ]);

        $storeName = trim($request->input('store_name'));

        // Find existing store
        $store = $user->store;

        // Verify uniqueness
        $existingStore = Store::where('store_name', $storeName)->first();
        if ($existingStore && (! $store || $existingStore->id !== $store->id)) {
            return response()->json(['message' => 'Nama toko sudah digunakan.'], 422);
        }

        if ($store) {
            $store->store_name = $storeName;
            $store->save();
        } else {
            $store = Store::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_name' => $storeName,
            ]);
        }

        return response()->json([
            'message' => 'Store saved successfully.',
            'store' => [
                'id' => $store->id,
                'name' => $store->store_name,
            ],
        ]);
    }

    /**
     * Get current authenticated user's store.
     */
    public function myStore(Request $request)
    {
        $user = auth()->user();
        $store = $user->store;

        if (! $store) {
            return response()->json(['message' => 'No store found.'], 404);
        }

        return response()->json([
            'id' => $store->id,
            'name' => $store->store_name,
        ]);
    }

    /**
     * Public store summary.
     */
    public function show(string $id)
    {
        $store = Store::find($id);
        if (! $store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        return response()->json([
            'id' => $store->id,
            'name' => $store->store_name,
        ]);
    }
}
