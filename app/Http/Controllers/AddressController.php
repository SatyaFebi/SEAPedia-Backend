<?php

namespace App\Http\Controllers;

use App\Models\BuyerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $addresses = $user->addresses()->orderBy('is_main', 'desc')->orderBy('created_at', 'desc')->get();
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'address_details' => 'required|string|max:1000',
            'is_main' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $isMain = $validated['is_main'] ?? false;

        $address = DB::transaction(function () use ($user, $validated, $isMain) {
            $currentIsMain = $isMain;
            if ($currentIsMain) {
                $user->addresses()->update(['is_main' => false]);
            } else {
                if ($user->addresses()->count() === 0) {
                    $currentIsMain = true;
                }
            }

            return $user->addresses()->create([
                'id' => (string) Str::uuid(),
                'address_details' => $validated['address_details'],
                'is_main' => $currentIsMain,
            ]);
        });

        return response()->json([
            'message' => 'Alamat berhasil ditambahkan.',
            'address' => $address
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);

        $validated = $request->validate([
            'address_details' => 'required|string|max:1000',
            'is_main' => 'nullable|boolean',
        ]);

        $isMain = $validated['is_main'] ?? false;

        DB::transaction(function () use ($user, $address, $validated, $isMain) {
            if ($isMain) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_main' => false]);
            }
            $address->update([
                'address_details' => $validated['address_details'],
                'is_main' => $isMain,
            ]);
        });

        return response()->json([
            'message' => 'Alamat berhasil diperbarui.',
            'address' => $address
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $address->delete();

            if ($address->is_main) {
                $nextMain = $user->addresses()->orderBy('created_at', 'desc')->first();
                if ($nextMain) {
                    $nextMain->update(['is_main' => true]);
                }
            }
        });

        return response()->json([
            'message' => 'Alamat berhasil dihapus.'
        ]);
    }
}
