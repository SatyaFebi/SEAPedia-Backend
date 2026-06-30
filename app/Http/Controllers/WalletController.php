<?php

namespace App\Http\Controllers;

use App\Models\BuyerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            $wallet = BuyerWallet::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'balance' => 0,
            ]);
        }

        $transactions = $wallet->transactions()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'amount' => (float) $tx->amount,
                    'type' => $tx->type,
                    'description' => $tx->description,
                    'created_at' => $tx->created_at->toDateTimeString(),
                ];
            }),
        ]);
    }

    public function topup(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1|max:10000000',
        ]);

        $user = auth()->user();
        $amount = $validated['amount'];

        $result = DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet;
            if (! $wallet) {
                $wallet = BuyerWallet::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'balance' => 0,
                ]);
            }

            $wallet->balance += $amount;
            $wallet->save();

            WalletTransaction::create([
                'id' => (string) Str::uuid(),
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'TOPUP',
                'description' => 'Top Up Wallet via Dummy Flow',
            ]);

            return $wallet;
        });

        return response()->json([
            'message' => 'Top up berhasil.',
            'balance' => (float) $result->balance,
        ]);
    }
}
