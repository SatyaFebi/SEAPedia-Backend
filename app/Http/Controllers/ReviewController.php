<?php

namespace App\Http\Controllers;

use App\Models\ApplicationReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of the application reviews.
     */
    public function index()
    {
        $reviews = ApplicationReview::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedReviews = $reviews->map(function ($review) {
            // Find role of the reviewer (if user is linked)
            $role = 'Guest';
            if ($review->user) {
                $userRoles = $review->user->roles()->pluck('role')->toArray();
                if (!empty($userRoles)) {
                    // Pick the first role or active role if we had it, let's use the first one or just default to Buyer/Seller/Driver
                    $role = implode(', ', $userRoles);
                }
            }

            return [
                'id' => $review->id,
                'name' => $review->reviewer_name,
                'role' => $role,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'date' => $review->created_at->toDateString(),
            ];
        });

        return response()->json($formattedReviews);
    }

    /**
     * Store a newly created application review.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reviewer_name' => 'required|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
        ]);

        $userId = null;
        $user = auth()->user();
        if ($user) {
            $userId = $user->id;
        }

        $review = ApplicationReview::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'reviewer_name' => $validated['reviewer_name'],
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        $role = 'Guest';
        if ($user) {
            $userRoles = $user->roles()->pluck('role')->toArray();
            if (!empty($userRoles)) {
                $role = implode(', ', $userRoles);
            }
        }

        return response()->json([
            'id' => $review->id,
            'name' => $review->reviewer_name,
            'role' => $role,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'date' => $review->created_at->toDateString(),
        ], 201);
    }
}
