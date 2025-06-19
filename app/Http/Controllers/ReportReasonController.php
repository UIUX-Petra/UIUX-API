<?php

namespace App\Http\Controllers;

use App\Models\ReportReason;
use Illuminate\Http\Request;

class ReportReasonController extends Controller
{
     public function getReasons()
    {
        try {
            $reasons = ReportReason::select(['id', 'title', 'description'])->get();
            return response()->json($reasons);
        } catch (\Exception $e) {
            // Log::error('Gagal mengambil alasan laporan: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], 500);
        }
    }
}
