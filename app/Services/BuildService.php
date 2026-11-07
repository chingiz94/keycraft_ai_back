<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Build;
use App\Models\BuildItem;
use App\Models\Component;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BuildService
{
    public function list(User $user, int $perPage)
    {
        return $user->builds()->with('items.component')->latest('id')->paginate($perPage)->withQueryString();
    }

    public function save(User $user, ?Build $build, array $data): Build
    {
        return DB::transaction(function () use ($user, $build, $data) {
            if (! $build) {
                return $user->builds()->create($data)->load('items.component');
            }
            $build = Build::whereKey($build->id)->lockForUpdate()->firstOrFail();
            $build->update($data);

            return $build->load('items.component');
        });
    }

    public function delete(Build $build): void
    {
        DB::transaction(function () use ($build) {
            Build::whereKey($build->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }

    public function item(Build $build, ?BuildItem $item, array $data): BuildItem
    {
        return DB::transaction(function () use ($build, $item, $data) {
            $build = Build::whereKey($build->id)->lockForUpdate()->firstOrFail();
            if ($item) {
                $item = $build->items()->whereKey($item->id)->firstOrFail();
                $item->update(['quantity' => $data['quantity']]);
            } else {
                // Keep the component alive until its referencing row has been inserted.
                Component::whereKey($data['component_id'])->lockForUpdate()->firstOrFail();
                if ($build->items()->where('component_id', $data['component_id'])->exists()) {
                    throw new BusinessException('Компонент уже есть в сборке');
                }
                $item = $build->items()->create($data);
            }

            return $item->load('component');
        });
    }

    public function removeItem(Build $build, BuildItem $item): void
    {
        DB::transaction(function () use ($build, $item) {
            $build = Build::whereKey($build->id)->lockForUpdate()->firstOrFail();
            $build->items()->whereKey($item->id)->firstOrFail()->delete();
        });
    }

    public function validate(Build $build, CompatibilityService $service): array
    {
        return $service->analyze($build->items()->with('component')->get());
    }
}
