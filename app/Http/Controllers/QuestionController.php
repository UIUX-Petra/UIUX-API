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

        // 1. Terapkan Filter berdasarkan Tag
        if ($filterTag) {
            $query->whereHas('groupQuestion.subject', function ($q) use ($filterTag) {
                // Sesuaikan 'groupQuestion.subject' dan 'name' dengan struktur relasi & kolom Anda
                $q->where('name', 'like', '%' . $filterTag . '%');
            });
        }

        // 2. Terapkan Sorting
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
        // Opsi: sorting sekunder jika sorting utama menghasilkan nilai yang sama
        if ($sortBy === 'views' || $sortBy === 'votes') {
            $query->orderBy('created_at', 'desc');
        }

        // 3. Tambahkan 'is_saved_by_request_user' jika user terautentikasi/disediakan
        if ($requestUser) {
            $query->withExists(['savedByUsers as is_saved_by_request_user' => function ($subQuery) use ($requestUser) {
                // Sesuaikan 'savedByUsers' dan 'saved_questions.user_id' dengan relasi Anda
                $subQuery->where('saved_questions.user_id', $requestUser->id);
            }]);
        }

        // Eksekusi query dan paginasi
        $data = $query->paginate($perPage); // $data SEHARUSNYA adalah instance LengthAwarePaginator

        // 4. Transformasi koleksi item dalam paginator (JIKA DIPERLUKAN)
        // Ini hanya akan berjalan jika ada item hasil paginasi
        if ($data->isNotEmpty()) {
            $data->getCollection()->transform(function ($item) {
                // a. Pastikan 'is_saved_by_request_user' adalah boolean dan default ke false
                //    Jika $requestUser null, 'withExists' tidak ditambahkan, jadi atribut ini mungkin tidak ada.
                $item->is_saved_by_request_user = (bool) ($item->is_saved_by_request_user ?? false);

                // b. Mapping field *_count ke nama yang lebih sederhana jika frontend membutuhkannya,
                //    dan pastikan tipenya integer. Sesuai gambar Anda: 'view', 'vote', 'comments_count'.
                if (isset($item->view_count)) {
                    $item->view = (int) $item->view_count;
                    // Anda bisa memilih untuk menghapus field asli jika tidak ingin ada di output:
                    // unset($item->view_count);
                }
                if (isset($item->vote_count)) {
                    $item->vote = (int) $item->vote_count;
                    // unset($item->vote_count);
                }
                // 'comments_count' sudah sesuai dengan nama di gambar, pastikan integer
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
            return $this->error('User not found with the provided email.');
        }
        try {
            $question->upvote($userId);
            return $this->success('Question upvoted successfully.', $question->vote);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }


    public function downvoteQuestion(Request $request, $id)
    {
        $question = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found with the provided email.');
        }
        try {
            $question->downvote($userId);
            return $this->success('Question downvoted successfully.', $question->vote);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
