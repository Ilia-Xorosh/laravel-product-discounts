<?php

declare(strict_types=1);

namespace ProductDiscounts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use ProductDiscounts\Models\Action;

final class ActionResolver
{
    public function resolve(?CarbonInterface $now = null): Collection
    {
        $now ??= now();
        $actions = Action::query()->where('actively', true)->where('start', '<=', $now)->where('end', '>=', $now)
            ->orderByDesc('priority')->orderBy('sort')->orderBy('id')->with('doings')->get();
        $accepted = collect();
        foreach ($actions as $candidate) {
            $blocked = $accepted->contains(function (Action $previous) use ($candidate) {
                return $previous->priority === $candidate->priority
                    ? $previous->stop_sort !== null && $previous->stop_sort >= $candidate->sort
                    : $previous->stop_priority !== null && $previous->stop_priority >= $candidate->priority;
            });
            if (!$blocked) {
                $accepted->push($candidate);
            }
        }
        return $accepted;
    }
}
