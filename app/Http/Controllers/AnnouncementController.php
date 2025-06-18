<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\BaseController;
use App\Models\Announcement;
use Illuminate\Database\Console\Migrations\BaseCommand;

class AnnouncementController extends BaseController
{
    public function __construct(Announcement $model)
    {
        parent::__construct($model);
    }
    public function getActiveAnnouncements(): JsonResponse
    {
        try {
            $announcements = $this->model::where('display_on_web', true)
                ->where('status', 'published')
                ->orderBy('published_at', 'desc')
                ->select('title', 'detail') 
                ->get();

            return response()->json([
                'success' => true,
                'data' => $announcements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengumuman.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
