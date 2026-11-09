<?php

namespace App\Services\SolarEstimation;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DependencyGraph
{
    public function ordered(Collection $items, array $roots): array
    {
        $visited = [];
        $visiting = [];
        $result = [];
        $visit = function (int $id) use (&$visit, &$visited, &$visiting, &$result, $items): void {
            if (isset($visiting[$id])) {
                throw ValidationException::withMessages(['dependencies' => 'These material dependencies form a loop. Remove the circular link before saving.']);
            }
            if (isset($visited[$id]) || ! $items->has($id)) {
                return;
            }
            $visiting[$id] = true;
            $result[] = $id;
            foreach ($items[$id]->dependencies as $child) {
                $visit($child->id);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach ($roots as $id) {
            $visit((int) $id);
        }

        return $result;
    }
}
