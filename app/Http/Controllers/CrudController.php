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
        return $this->model::with($this->with)->latest()->paginate(15);
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
