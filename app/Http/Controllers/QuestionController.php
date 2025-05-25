<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Models\GroupQuestion;
use App\Utils\HttpResponseCode;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\GroupQuestionController;
use Illuminate\Support\Facades\Storage;

class QuestionController extends BaseController
{
    protected $userController, $tagsQuestionController;
    public function __construct(Question $model)
    {
        parent::__construct($model);
        $this->userController = new UserController(new User());
        $this->tagsQuestionController = new GroupQuestionController(new GroupQuestion());
    }

    public function getUserQuestionsWithCount($userId)
    {
        $questions = $this->model->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // $count = $questions->count();

        return $this->success('Successfully retrieved data', $questions);
    }


    public function getQuestionPaginated(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $userEmail = $request->input('email');

        $sortBy = $request->input('sort_by', 'latest');
        $filterTag = $request->input('filter_tag', null);

        $requestUser = null;
        if ($userEmail) {
            $requestUser = User::where('email', $userEmail)->first();
        }

        $query = $this->model->with($this->model->getDefaultRelations()) // Asumsi getDefaultRelations() ada
            ->withCount([
                'comment as comments_count',
            ]);

        // filter berdasarkan Tag
        if ($filterTag) {
            $query->whereHas('groupQuestion.subject', function ($q) use ($filterTag) {
                $q->where('name', 'like', '%' . $filterTag . '%');
            });
        }

        switch ($sortBy) {
            case 'views':
                $query->orderBy('view', 'desc');
                break;
            case 'votes':
                $query->orderBy('vote', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
        if ($sortBy === 'views' || $sortBy === 'votes') {
            $query->orderBy('created_at', 'desc');
        }

        if ($requestUser) {
            $query->withExists([
                'savedByUsers as is_saved_by_request_user' => function ($subQuery) use ($requestUser) {
                    $subQuery->where('saved_questions.user_id', $requestUser->id);
                }
            ]);
        }

        $data = $query->paginate($perPage);

        if ($data->isNotEmpty()) {
            $data->getCollection()->transform(function ($item) {
                $item->is_saved_by_request_user = (bool) ($item->is_saved_by_request_user ?? false);

                if (isset($item->view_count)) {
                    $item->view = (int) $item->view_count;
                }
                if (isset($item->vote_count)) {
                    $item->vote = (int) $item->vote_count;
                }
                if (isset($item->comments_count)) {
                    $item->comments_count = (int) $item->comments_count;
                }

                return $item;
            });
        }

        return $this->success('Successfully retrieved data', $data);
    }


    public function store(Request $request)
    {
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');

        $data = $request->only($this->model->getFillable());
        $valid = Validator::make($data, $this->model->validationRules(), $this->model->validationMessages());

        if ($valid->fails()) {
            $validationError = $valid->errors()->first();
            Log::error("Validation failed:", ['error' => $validationError]);
            return $this->error($validationError, HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $model = $this->model->create($data);
        $request->request->add(['question_id' => $model->id]);
        $this->tagsQuestionController->store($request);
        return $this->success('Data successfully saved to model', $model);
    }

    public function getQuestionByAnswerId($answer_id)
    {
        $question = $this->model::with($this->model->relations())->findOrFail($answer_id);
        return $this->success('Successfully retrieved data', $question);
    }

    public function viewQuestion(Request $request, $id) //ini emang ga butuh login sih, tapi pas fetch comment, masih harus login
    {
        $question = $this->model::with(array_merge($this->model->relations(), ['comment.user', 'answer.user', 'answer.comment.user']))->findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (!$question) {
            return $this->error('Question not found with the provided id.');
        }
        if (is_null($userId)) {
            return $this->error('User not found with the provided email.');
        }
        $answers = $question->answer->map(function ($answer) {
            return [
                'id' => $answer->id,
                'username' => $answer->user->username,
                'image' => $answer->image,
                'answer' => $answer->answer,
                'vote' => $answer->vote,
                'timestamp' => $answer->created_at,
                'comments' => $answer->comment->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'username' => $comment->user->username,
                        'user_email' => $comment->user->email,
                        'comment' => $comment->comment,
                        'timestamp' => $comment->created_at
                    ];
                }),
            ];
        });
        $comment = $question->comment->map(function ($comment) {
            return [
                'id' => $comment->id,
                'username' => $comment->user->username,
                'comment' => $comment->comment,
                'timestamp' => $comment->created_at,

            ];
        });

        $question->timestamp = $question->created_at;
        $question->setRelation('answer', $answers);
        $question->setRelation('comment', $comment);
        try {
            $question->view($userId);
            return $this->success('Question viewed successfully.', $question);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function upvoteQuestion(Request $request, $id)
    {
        $question = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found or not authenticated.');
        }
        try {
            $question->upvote($userId);
            return $this->success('Question upvoted successfully.', $question->vote);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \Log::error("Error upvoting question {$id} by user {$userId}: " . $e->getMessage(), ['exception' => $e]);
            return $this->error('An error occurred while processing your vote.');
        }
    }

    public function downvoteQuestion(Request $request, $id)
    {
        $question = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found or not authenticated.');
        }
        try {
            $question->downvote($userId);
            return $this->success('Question downvoted successfully.', $question->vote);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \Log::error("Error downvoting question {$id} by user {$userId}: " . $e->getMessage(), ['exception' => $e]);
            return $this->error('An error occurred while processing your vote.');
        }
    }
    
    public function update(Request $request, $id)
    {
        // Dapatkan user ID dari sesi yang login
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found or not authenticated.', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        try {
            $question = Question::findOrFail($id);

            // Pastikan hanya pemilik pertanyaan yang bisa mengedit
            if ($question->user_id !== $userId) {
                return $this->error('You are not authorized to update this question.', HttpResponseCode::HTTP_FORBIDDEN);
            }

            // Validasi input
            $request->validate([
                'title' => 'required|string|max:255',
                'question' => 'required|string',
                'subject_id' => 'array',
                'subject_id.*' => 'exists:subjects,id', // Pastikan subjects table dan id column ada
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5042', // Sesuaikan ukuran max
            ]);

            $question->title = $request->title;
            $question->question = $request->question;

            // Handle image update/deletion
            // Jika ada file image baru di request
            if ($request->hasFile('image')) {
                // Hapus gambar lama jika ada
                if ($question->image) {
                    Storage::disk('public')->delete($question->image);
                    Log::info("Old image deleted: " . $question->image);
                }
                // Simpan gambar baru
                $timestamp = date('Y-m-d_H-i-s');
                $emailCleaned = str_replace(['@', '.'], '_', $request->email);
                $extension = $request->file('image')->getClientOriginalExtension();
                $customFileName = "q_" . $emailCleaned . "_" . $timestamp . "." . $extension;
                $imagePath = $request->file('image')->storeAs("uploads/questions", $customFileName, 'public');
                $question->image = $imagePath;
                Log::info("New image uploaded to: " . $imagePath);
            } elseif ($request->has('image') && $request->input('image') === '') {
                // Jika input 'image' adalah string kosong, berarti user ingin menghapus gambar yang sudah ada
                if ($question->image) {
                    Storage::disk('public')->delete($question->image);
                    $question->image = null;
                    Log::info("Existing image explicitly deleted for question ID: " . $id);
                }
            }
            // Jika tidak ada file 'image' baru dan 'image' tidak kosong string, berarti gambar lama dipertahankan

            $question->save();

            // Sinkronkan tags (subjects)
            if ($request->has('subject_id')) {
                $question->subjects()->sync($request->subject_id);
            } else {
                $question->subjects()->detach(); // Hapus semua subjects jika tidak ada yang dipilih
            }

            // Muat ulang relasi subjects dan image_url untuk respons yang lengkap
            $question->load('subjects');
            $questionData = $question->toArray();
            $questionData['image_url'] = $question->image ? Storage::url($question->image) : null;
            $questionData['subjects'] = $question->subjects->map(function($subject) {
                return ['id' => $subject->id, 'name' => $subject->name];
            });

            return $this->success('Question updated successfully.', $questionData);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Question not found.', HttpResponseCode::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error("Error updating question {$id}: " . $e->getMessage(), ['exception' => $e]);
            return $this->error('Failed to update question: ' . $e->getMessage(), HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

     public function deleteQuestionAPI(Request $request, $id) // <<< NAMA METODE DIUBAH DI SINI
    {
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found or not authenticated.', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        try {
            $question = Question::findOrFail($id);

            // Pastikan hanya pemilik pertanyaan yang bisa menghapus
            if ($question->user_id !== $userId) {
                return $this->error('You are not authorized to delete this question.', HttpResponseCode::HTTP_FORBIDDEN);
            }

            // Hapus gambar terkait jika ada
            if ($question->image) {
                Storage::disk('public')->delete($question->image);
                Log::info("Image deleted for question ID: " . $id . " Path: " . $question->image);
            }

            // Hapus relasi tags (subjects)
            $question->subjects()->detach();
            // Hapus komentar dan jawaban terkait (jika menggunakan onDelete('cascade') di database, ini otomatis)
            // Jika tidak, Anda perlu menghapusnya secara manual di sini:
            // $question->comment()->delete();
            // $question->answer()->delete();

            $question->delete();

            return $this->success('Question deleted successfully.', null);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Question not found.', HttpResponseCode::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error("Error deleting question {$id}: " . $e->getMessage(), ['exception' => $e]);
            return $this->error('Failed to delete question: ' . $e->getMessage(), HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
