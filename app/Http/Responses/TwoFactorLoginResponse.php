<?php

namespace App\Http\Responses;

class TwoFactorLoginResponse extends \Laravel\Fortify\Http\Responses\TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? parent::toResponse($request)
            : app(LoginResponse::class)->toResponse($request);
    }
}
