<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class CrudController extends Controller
{
    protected string $model;
    protected array $with = [];

    abstract protected function rules(?int $id = null): array;

    public function index()
    {
        $request = request();
        $query = $this->model::with($this->with);

        if ($request->boolean('all')) {
            return response()->json($query->latest()->get());
        }

        $perPage = $request->integer('per_page', 15);
        return $query->latest()->paginate($perPage);
    }

    public function store(Request $request)
    {
        $item = $this->model::create($request->validate($this->rules()));

        return response()->json($item, 201);
    }

    public function show($id)
    {
        return $this->model::with($this->with)->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $item = $this->model::findOrFail($id);
        $rules = array_map(fn ($r) => array_merge(['sometimes'], $r), $this->rules((int) $id));
        $item->update($request->validate($rules));

        return $item;
    }

    public function destroy($id)
    {
        $this->model::findOrFail($id)->delete();

        return response()->noContent();
    }
}
