<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public function active(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    public function impersonatorId(Request $request): ?int
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public function impersonator(Request $request): ?User
    {
        $id = $this->impersonatorId($request);

        if ($id === null) {
            return null;
        }

        return User::query()->find($id);
    }

    /**
     * @return array{active: bool, impersonator: array{id: int, name: string, email: string}|null}|null
     */
    public function sharedState(Request $request): ?array
    {
        if (! $this->active($request)) {
            return null;
        }

        $impersonator = $this->impersonator($request);

        return [
            'active' => true,
            'impersonator' => $impersonator ? [
                'id' => $impersonator->id,
                'name' => $impersonator->name,
                'email' => $impersonator->email,
            ] : null,
        ];
    }
}
