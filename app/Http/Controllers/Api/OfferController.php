<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfferRequest;
use App\Http\Requests\PageRequest;
use App\Http\Resources\OfferResource;
use App\Models\Component;
use App\Models\MarketplaceOffer;
use App\Services\CatalogService;

class OfferController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    public function index(PageRequest $request)
    {
        return OfferResource::collection(MarketplaceOffer::paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function forComponent(PageRequest $request, Component $component)
    {
        return OfferResource::collection($component->offers()->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(MarketplaceOffer $offer)
    {
        return new OfferResource($offer);
    }

    public function store(OfferRequest $request)
    {
        return (new OfferResource($this->catalog->saveOffer(null, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(OfferRequest $request, MarketplaceOffer $offer)
    {
        return new OfferResource($this->catalog->saveOffer($offer, $request->validated()));
    }

    public function destroy(MarketplaceOffer $offer)
    {
        $offer->delete();

        return response()->noContent();
    }
}
