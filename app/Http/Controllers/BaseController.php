<?php

namespace App\Http\Controllers;

use App\Utils\HttpResponse;
use App\Utils\HttpResponseCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller
{
    use HttpResponse;
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return $this->success('Successfully retrieved data', $this->model->with($this->model->relations())->get());
    }




    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $data = $request->only($this->model->getFillable());

        $valid = Validator::make($data, $this->model->validationRules(), $this->model->validationMessages());

        if ($valid->fails()) {
            $validationError = $valid->errors()->first();
            Log::error("Validation failed:", ['error' => $validationError]);
            return $this->error($validationError, HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $model = $this->model->create($data);
        // Log::info("Data successfully saved to model:", ['model_data' => $model]);
        return $this->success('Data successfully saved to model', $model);
    }
    /**
     * Display the specified resource by ID.
     */
    public function show(string $id)
    {
        return $this->success('Successfully retrieved data', $this->model->with($this->model->relations())->findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->only($this->model->getFillable());

        $resp = ControllerUtils::validateRequest($this->model, $data);
        if ($resp) {
            return $this->error($resp);
        }

        $update = $this->model->find($id);
        if (!$update) {
            return $this->error('ID not found !');
        }

        $update->update($data);

        return $this->success('Successfully updated a data', $update);
    }

    /**
     * Update the specified resource in storage partially.
     */
    public function updatePartial(Request $request, $id)
    {
        $fillableKey = [];
        foreach ($this->model->getFillable() as $field) {
            if ($request->has($field)) {
                $fillableKey[] = $field;
            }
        }

        $requestFillable = $request->only($fillableKey);

        $resp = ControllerUtils::validateRequest(
            $this->model,
            $requestFillable,
            isPatch: true,
        );
        if ($resp) {
            return $this->error($resp);
        }

        $update = $this->model->find($id);
        $update->update($requestFillable);

        return $this->success('Successfully updated a data', $update);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = $this->model->find($id);

        if (!$data) {
            return $this->error('ID not found!');
        }
        $data->delete();

        return $this->success('Successfully deleted a data!');
    }
}