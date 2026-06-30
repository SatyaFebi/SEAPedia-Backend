<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiscountController extends Controller
{
    /**
     * Create a Voucher (Admin only).
     */
    public function createVoucher(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->where('role', 'Admin')->exists()) {
            return response()->json(['message' => 'Forbidden: Only admins can manage discounts.'], 403);
        }

        $request->validate([
            'code' => 'required|string|unique:discounts,code|max:50',
            'amount_type' => 'required|in:FIXED,PERCENTAGE',
            'value' => 'required|numeric|min:0',
            'max_usage' => 'required|integer|min:1',
            'expiry_date' => 'required|date|after:now',
        ]);

        $voucher = Discount::create([
            'id' => (string) Str::uuid(),
            'code' => strtoupper($request->input('code')),
            'type' => 'VOUCHER',
            'amount_type' => $request->input('amount_type'),
            'value' => $request->input('value'),
            'max_usage' => $request->input('max_usage'),
            'used_count' => 0,
            'expiry_date' => $request->input('expiry_date'),
        ]);

        return response()->json([
            'message' => 'Voucher created successfully.',
            'discount' => $voucher
        ], 201);
    }

    /**
     * Create a Promo (Admin only).
     */
    public function createPromo(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->where('role', 'Admin')->exists()) {
            return response()->json(['message' => 'Forbidden: Only admins can manage discounts.'], 403);
        }

        $request->validate([
            'code' => 'required|string|unique:discounts,code|max:50',
            'amount_type' => 'required|in:FIXED,PERCENTAGE',
            'value' => 'required|numeric|min:0',
            'expiry_date' => 'required|date|after:now',
        ]);

        $promo = Discount::create([
            'id' => (string) Str::uuid(),
            'code' => strtoupper($request->input('code')),
            'type' => 'PROMO',
            'amount_type' => $request->input('amount_type'),
            'value' => $request->input('value'),
            'max_usage' => null,
            'used_count' => 0,
            'expiry_date' => $request->input('expiry_date'),
        ]);

        return response()->json([
            'message' => 'Promo created successfully.',
            'discount' => $promo
        ], 201);
    }

    /**
     * List all Vouchers.
     */
    public function listVouchers()
    {
        $vouchers = Discount::where('type', 'VOUCHER')->get();
        return response()->json($vouchers);
    }

    /**
     * List all Promos.
     */
    public function listPromos()
    {
        $promos = Discount::where('type', 'PROMO')->get();
        return response()->json($promos);
    }

    /**
     * View details of a specific Voucher.
     */
    public function showVoucher($code)
    {
        $voucher = Discount::where('type', 'VOUCHER')
            ->where('code', strtoupper($code))
            ->first();

        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        return response()->json($voucher);
    }

    /**
     * View details of a specific Promo.
     */
    public function showPromo($code)
    {
        $promo = Discount::where('type', 'PROMO')
            ->where('code', strtoupper($code))
            ->first();

        if (!$promo) {
            return response()->json(['message' => 'Promo not found.'], 404);
        }

        return response()->json($promo);
    }

    /**
     * Validate a discount code (public endpoint used before/during checkout)
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric'
        ]);

        $code = strtoupper($request->input('code'));
        $subtotal = $request->input('subtotal');

        $discount = Discount::where('code', $code)->first();

        if (!$discount) {
            return response()->json(['message' => 'Kode diskon tidak ditemukan.'], 404);
        }

        // Validate expiry
        if ($discount->expiry_date && $discount->expiry_date->isPast()) {
            return response()->json(['message' => 'Kode diskon sudah kedaluwarsa.'], 422);
        }

        // Validate Voucher remaining usage
        if ($discount->type === 'VOUCHER' && $discount->max_usage !== null) {
            if ($discount->used_count >= $discount->max_usage) {
                return response()->json(['message' => 'Voucher sudah habis digunakan.'], 422);
            }
        }

        // Calculate discount value
        $discountAmount = 0;
        if ($discount->amount_type === 'PERCENTAGE') {
            $discountAmount = $subtotal * ($discount->value / 100);
        } else {
            $discountAmount = $discount->value;
        }

        // Discount cannot exceed subtotal
        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        return response()->json([
            'valid' => true,
            'id' => $discount->id,
            'code' => $discount->code,
            'type' => $discount->type,
            'amount_type' => $discount->amount_type,
            'value' => $discount->value,
            'discount_amount' => $discountAmount,
        ]);
    }
}
