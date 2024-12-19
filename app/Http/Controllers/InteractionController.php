<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Models\User;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InteractionController extends Controller
{
    public function trackInteraction(Request $request)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'question_id' => 'required|exists:questions,id',
            'interaction_type' => 'required|in:view,like,comment',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        // Create interaction
        $interaction = Interaction::create([
            'user_id' => $request->user_id,
            'question_id' => $request->question_id,
            'interaction_type' => $request->interaction_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Interaction tracked successfully.',
            'data' => $interaction
        ], 201);
    }

    public function mostInteractedWith(Request $request, $userId)
    {
        $topN = $request->input('top_n', 1);

        // Get the most interacted users with the given user
        $interactions = Interaction::where('user_id', $userId)
            ->selectRaw('user_id, COUNT(*) as interaction_count')
            ->groupBy('user_id')
            ->orderByDesc('interaction_count')
            ->limit($topN)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $interactions
        ]);
    }
}
