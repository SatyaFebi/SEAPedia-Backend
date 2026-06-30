<?php

namespace App\Http\Controllers;

use App\Models\BuyerWallet;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|in:Buyer,Seller,Driver',
        ]);

        // Create user
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => strip_tags($validated['name']),
            'username' => strtolower(strip_tags($validated['username'])),
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'api_token' => Str::random(80),
        ]);

        // Assign selected roles
        foreach ($validated['roles'] as $roleName) {
            UserRole::create([
                'user_id' => $user->id,
                'role' => $roleName,
            ]);
        }

        // Return user profile and token
        $rolesList = $user->roles()->pluck('role')->toArray();
        $store = $user->store;

        $walletBalance = 0;
        $address = null;
        if (in_array('Buyer', $rolesList)) {
            $wallet = $user->wallet ?: BuyerWallet::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'balance' => 0,
            ]);
            $walletBalance = (float) $wallet->balance;
            $mainAddress = $user->addresses()->where('is_main', true)->first();
            $address = $mainAddress ? $mainAddress->address_details : 'Belum ada alamat pengiriman';
        }

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $user->api_token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $rolesList,
                'walletBalance' => $walletBalance,
                'address' => $address,
                'sellerIncome' => 0,  // Placeholder seller income
                'driverEarnings' => 0, // Placeholder driver earnings
                'store' => $store ? [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ] : null,
            ],
        ], 201);
    }

    /**
     * Handle user login.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = strtolower(trim($validated['username']));

        // Check by username or email
        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Kombinasi username/password salah.',
            ], 401);
        }

        // Generate token
        $user->api_token = Str::random(80);
        $user->save();

        $rolesList = $user->roles()->pluck('role')->toArray();
        $store = $user->store;

        $walletBalance = 0;
        $address = null;
        if (in_array('Buyer', $rolesList)) {
            $wallet = $user->wallet ?: BuyerWallet::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'balance' => 0,
            ]);
            $walletBalance = (float) $wallet->balance;
            $mainAddress = $user->addresses()->where('is_main', true)->first();
            $address = $mainAddress ? $mainAddress->address_details : 'Belum ada alamat pengiriman';
        }

        return response()->json([
            'message' => 'Login successful.',
            'token' => $user->api_token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $rolesList,
                'walletBalance' => $walletBalance,
                'address' => $address,
                'sellerIncome' => 0,
                'driverEarnings' => 0,
                'store' => $store ? [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ] : null,
            ],
        ]);
    }

    /**
     * Get logged-in user profile.
     */
    public function profile(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $rolesList = $user->roles()->pluck('role')->toArray();
        $store = $user->store;

        $walletBalance = 0;
        $address = null;
        if (in_array('Buyer', $rolesList)) {
            $wallet = $user->wallet ?: BuyerWallet::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'balance' => 0,
            ]);
            $walletBalance = (float) $wallet->balance;
            $mainAddress = $user->addresses()->where('is_main', true)->first();
            $address = $mainAddress ? $mainAddress->address_details : 'Belum ada alamat pengiriman';
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $rolesList,
                'walletBalance' => $walletBalance,
                'address' => $address,
                'sellerIncome' => 0,
                'driverEarnings' => 0,
                'store' => $store ? [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ] : null,
            ],
        ]);
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            $user->api_token = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
