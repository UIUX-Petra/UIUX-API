<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // <-- Tambahkan fasad Log
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    public function index()
    {

        $questions = Question::withTrashed()->with('user:id,username')->latest()->paginate(15);
        Log::debug('Mengembalikan daftar pertanyaan ke admin.', ['total' => $questions->total()]);
        return response()->json($questions);
    }

    public function show(string $id)
    {
        Log::info('Admin (ID: ' .  Auth::id() . ') mencoba melihat pertanyaan dengan ID: ' . $id);
        $question = Question::withTrashed()->with('user:id,username')->find($id);

        if (!$question) {
            Log::warning('Gagal menemukan pertanyaan dengan ID: ' . $id . ' untuk Admin (ID: ' .  Auth::id() . ')');
            return response()->json(['message' => 'Pertanyaan tidak ditemukan.'], 404);
        }

        return response()->json($question);
    }

    public function update(Request $request, string $id)
    {
        // Log::info('Admin (ID: ' .  Auth::id() . ') memulai proses update untuk pertanyaan ID: ' . $id, $request->except('image'));

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            // Log::error('Validasi gagal saat update pertanyaan ID: ' . $id . ' oleh Admin (ID: ' .  Auth::id() . ')', ['errors' => $validator->errors()]);
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $question = Question::withTrashed()->find($id);
        if (!$question) {
            // Log::warning('Gagal menemukan pertanyaan ID: ' . $id . ' saat akan diupdate oleh Admin (ID: ' .  Auth::id() . ')');
            return response()->json(['message' => 'Pertanyaan tidak ditemukan.'], 404);
        }

        $question->title = $request->title;
        $question->question = $request->question;

        if ($request->hasFile('image')) {
            if ($question->image && Storage::disk('public')->exists($question->image)) {
                Storage::disk('public')->delete($question->image);
            }
            $path = $request->file('image')->store('images/questions', 'public');
            $question->image = $path;
        }

        $question->save();

        return response()->json([
            'message' => 'Pertanyaan berhasil diperbarui.',
            'data' => $question
        ]);
    }

  
    public function destroy(string $id)
    {
        Log::info('Admin (ID: ' .  Auth::id() . ') mencoba melakukan soft delete pada pertanyaan ID: ' . $id);
        $question = Question::find($id);

        if (!$question) {
            Log::warning('Gagal menemukan pertanyaan ID: ' . $id . ' untuk di-soft-delete.');
            return response()->json(['message' => 'Pertanyaan tidak ditemukan atau sudah dihapus.'], 404);
        }

        $question->delete();
        Log::info('Pertanyaan ID: ' . $id . ' berhasil di-soft-delete oleh Admin (ID: ' .  Auth::id() . ')');

        return response()->json(['message' => 'Pertanyaan berhasil dipindahkan ke arsip.']);
    }

  
    public function restore(string $id)
    {
        Log::info('Admin (ID: ' .  Auth::id() . ') mencoba memulihkan pertanyaan ID: ' . $id);
        $question = Question::withTrashed()->find($id);

        if (!$question) {
            Log::warning('Gagal menemukan pertanyaan ID: ' . $id . ' untuk dipulihkan.');
            return response()->json(['message' => 'Pertanyaan tidak ditemukan.'], 404);
        }

        $question->restore();
        Log::info('Pertanyaan ID: ' . $id . ' berhasil dipulihkan oleh Admin (ID: ' .  Auth::id() . ')');

        return response()->json(['message' => 'Pertanyaan berhasil dipulihkan.']);
    }

 
    public function forceDelete(string $id)
    {
        Log::warning('Admin (ID: ' .  Auth::id() . ') mencoba MENGHAPUS PERMANEN pertanyaan ID: ' . $id);
        $question = Question::withTrashed()->find($id);

        if (!$question) {
            Log::warning('Gagal menemukan pertanyaan ID: ' . $id . ' untuk dihapus permanen.');
            return response()->json(['message' => 'Pertanyaan tidak ditemukan.'], 404);
        }

        if ($question->image && Storage::disk('public')->exists($question->image)) {
            Log::info('Menghapus gambar (' . $question->image . ') terkait pertanyaan ID: ' . $id . ' yang akan dihapus permanen.');
            Storage::disk('public')->delete($question->image);
        }

        $question->forceDelete();
        Log::warning('PERMANENT DELETE: Pertanyaan ID: ' . $id . ' telah dihapus permanen oleh Admin (ID: ' .  Auth::id() . ')');

        return response()->json(['message' => 'Pertanyaan berhasil dihapus secara permanen.']);
    }
}
