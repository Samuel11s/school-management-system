<?php

namespace App\Http\Requests\Api\V1;

class LoginRequest extends ApiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            /** A label for the token, e.g. the device name. */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
