<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogRequest;
use App\Http\Requests\ComponentRequest;
use App\Http\Resources\ComponentResource;
use App\Models\Component;
use App\Services\CatalogService;

class ComponentController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    public function index(CatalogRequest $request)
    {
        return ComponentResource::collection($this->catalog->list($request->validated()));
    }

    public function show(Component $component)
    {
        return new ComponentResource($component->load('offers'));
    }

    public function store(ComponentRequest $request)
    {
        return (new ComponentResource($this->catalog->save(null, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(ComponentRequest $request, Component $component)
    {
        return new ComponentResource($this->catalog->save($component, $request->validated()));
    }

    public function destroy(Component $component)
    {
        $this->catalog->delete($component);

        return response()->noContent();
    }
}
