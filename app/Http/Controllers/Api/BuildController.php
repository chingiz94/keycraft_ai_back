<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuildItemRequest;
use App\Http\Requests\BuildRequest;
use App\Http\Requests\PageRequest;
use App\Http\Resources\BuildItemResource;
use App\Http\Resources\BuildResource;
use App\Models\Build;
use App\Models\BuildItem;
use App\Services\BuildService;
use App\Services\CompatibilityService;
use Illuminate\Support\Facades\Gate;

class BuildController extends Controller
{
    public function __construct(private BuildService $builds) {}

    public function index(PageRequest $request)
    {
        return BuildResource::collection($this->builds->list($request->user(), $request->integer('per_page', 20), $request->query('source') === 'retail'));
    }

    public function show(Build $build)
    {
        Gate::authorize('view', $build);

        return new BuildResource($build->load('items.component'));
    }

    public function store(BuildRequest $request)
    {
        return (new BuildResource($this->builds->save($request->user(), null, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(BuildRequest $request, Build $build)
    {
        Gate::authorize('update', $build);

        return new BuildResource($this->builds->save($request->user(), $build, $request->validated()));
    }

    public function destroy(Build $build)
    {
        Gate::authorize('delete', $build);
        $this->builds->delete($build);

        return response()->noContent();
    }

    public function addItem(BuildItemRequest $request, Build $build)
    {
        Gate::authorize('update', $build);

        return (new BuildItemResource($this->builds->item($build, null, $request->validated())))->response()->setStatusCode(201);
    }

    public function updateItem(BuildItemRequest $request, Build $build, BuildItem $item)
    {
        Gate::authorize('update', $build);

        return new BuildItemResource($this->builds->item($build, $item, $request->validated()));
    }

    public function deleteItem(Build $build, BuildItem $item)
    {
        Gate::authorize('update', $build);
        $this->builds->removeItem($build, $item);

        return response()->noContent();
    }

    public function validateBuild(Build $build, CompatibilityService $compatibility)
    {
        Gate::authorize('view', $build);

        return response()->json(['data' => $this->builds->validate($build, $compatibility)]);
    }
}
