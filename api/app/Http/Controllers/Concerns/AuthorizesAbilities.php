<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait AuthorizesAbilities
{
    /**
     * Stop the request unless the member holds this ability.
     * Reading is open to every active member; this guards writes and admin.
     */
    protected function must(Request $request, string $ability): void
    {
        if (! $request->user()->hasPermission($ability)) {
            throw new AccessDeniedHttpException(
                'Your role does not allow this action. Ask an account owner for access.'
            );
        }
    }
}
