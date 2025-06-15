<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubjectController extends Controller
{

    public function index(Request $request) 
    {
        // Log::info('Fetching subjects request received.', [
        //     'ip_address' => $request->ip(),
        //     'user_agent' => $request->userAgent(),
        //     'params' => $request->all()
        // ]);

        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', null);

            $query = Subject::query()->withCount('groupQuestion');

            if ($search) {
                // Log::info('Applying search filter for subjects.', ['search_term' => $search]);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('abbreviation', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('created_at', 'desc');

            $subjects = $query->paginate($perPage);

            // Log::info('Successfully fetched subjects.', [
            //     'total_results' => $subjects->total(),
            //     'results_on_page' => $subjects->count(),
            //     'current_page' => $subjects->currentPage()
            // ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully retrieved subjects.',
                'data'    => $subjects
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subjects.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString() 
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An internal server error occurred.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), Subject::validationRules(), Subject::validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        $subject = Subject::create($request->only(['name', 'abbreviation', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Subject created successfully.',
            'data'    => $subject
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), Subject::validationRules($id), Subject::validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $subject = Subject::findOrFail($id);
            $subject->update($request->only(['name', 'abbreviation', 'description']));

            return response()->json([
                'success' => true,
                'message' => 'Subject updated successfully.',
                'data'    => $subject
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found.'
            ], 404);
        }
    }

    public function show(string $id)
    {
        try {
            $subject = Subject::withCount('groupQuestion')->findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Subject retrieved successfully.',
                'data'    => $subject
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found.'
            ], 404);
        }
    }


    public function destroy(string $id)
    {
        try {
            $subject = Subject::findOrFail($id);

            $subject->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subject deleted successfully.'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found.'
            ], 404);
        }
    }
}
