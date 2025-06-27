<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class ReportController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'reportable_id' => 'required|string',
                'reportable_type' => 'required|string|in:question,answer,comment',
                'report_reason_id' => 'required|uuid|exists:report_reasons,id',
                'additional_notes' => 'nullable|string|max:1000',
            ]);


            $modelName = 'App\\Models\\' . ucfirst($validated['reportable_type']);
            if (!class_exists($modelName)) {
                return response()->json(['success' => false, 'message' => 'Content type is invalid.'], 400);
            }

            $reportable = $modelName::findOrFail($validated['reportable_id']);

            
            if ($reportable->hasBeenReportedByUser(Auth::id())) {
                return response()->json(['success' => false, 'message' => 'You have already reported this content'], 409);
            }

            // Kode BARU dengan perbaikan
            $reportData = [
                'report_reason_id' => $validated['report_reason_id'],
                'additional_notes' => $validated['additional_notes'] ?? null, // <-- INI PERBAIKANNYA
                'preview' => substr($reportable->content ?? $reportable->title, 0, 150) . '...'
            ];

            $reportable->report(Auth::id(), $reportData);


            return response()->json(['success' => true, 'message' => 'Your report has been sent successfully.'], 201);
        } catch (\Throwable $e) {
            Log::error('An unexpected exception occurred in API store report method.', [
                'error_message' => $e->getMessage(),
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'initial_request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An internal API error occurred.'
            ], 500);
        }
    }
}
