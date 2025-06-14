<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Jobs\SendAnnouncementEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends BaseController
{
    public function __construct(Announcement $model)
    {
        parent::__construct($model);
    }

    public function index()
    {
        Log::info('Fetching all announcements from API.');
        $announcements = $this->model::latest('created_at')->get();
        return $this->success('Announcements fetched successfully', $announcements);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'detail' => 'required|string',
            'status' => 'required|in:draft,published',
            'display_on_web' => 'required|boolean',
            'send_email' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('Announcement creation validation failed.', ['errors' => $validator->errors()]);
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $data = $request->only(['title', 'detail', 'status', 'display_on_web']);
        $data['admin_id'] = Auth::id();

        if ($data['status'] === 'published') {
            $data['published_at'] = now();
        }

        Log::info('Creating new announcement.', ['data' => $data]);
        $announcement = $this->model::create($data);

        if ($request->send_email && $announcement->status === 'published') {
            Log::info('Dispatching SendAnnouncementEmail job.', ['announcement_id' => $announcement->id]);
            SendAnnouncementEmail::dispatch($announcement);
        }

        Log::info('Announcement created successfully.', ['id' => $announcement->id]);
        return $this->success('Announcement created successfully.', $announcement, 201);
    }

    public function showDetail(Announcement $announcement)
    {
        Log::info('Fetching a single announcement.', ['id' => $announcement->id]);
        return $this->success('Announcement fetched successfully.', $announcement);
    }

    public function updateDetail(Request $request, Announcement $announcement)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'detail' => 'required|string',
            'status' => 'required|in:draft,published,archived',
            'display_on_web' => 'required|boolean',
            'send_email' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('Announcement update validation failed.', ['id' => $announcement->id, 'errors' => $validator->errors()]);
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $data = $request->only(['title', 'detail', 'status', 'display_on_web']);

        if ($announcement->status === 'draft' && $data['status'] === 'published' && is_null($announcement->published_at)) {
            $data['published_at'] = now();
        }

        Log::info('Updating announcement.', ['id' => $announcement->id, 'data' => $data]);
        $announcement->update($data);

        if ($request->send_email && $announcement->status === 'published') {
            Log::info('Re-dispatching SendAnnouncementEmail job on update.', ['announcement_id' => $announcement->id]);
            SendAnnouncementEmail::dispatch($announcement);
        }

        Log::info('Announcement updated successfully.', ['id' => $announcement->id]);
        return $this->success('Announcement updated successfully.', $announcement);
    }

    public function destroyAnnouncement(Announcement $announcement)
    {
        $id = $announcement->id;
        Log::info('Attempting to delete announcement.', ['id' => $id]);
        $announcement->delete();
        Log::info('Announcement deleted successfully.', ['id' => $id]);
        return $this->success('Announcement deleted successfully.', null, 204);
    }
}
