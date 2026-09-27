<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $student = $user->student;

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
                // A student's login email is also their contact email on record.
                Rule::unique('students')->ignore($student?->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        DB::transaction(function () use ($user, $student, $input) {
            $user->forceFill([
                'name' => $input['name'],
                'email' => mb_strtolower($input['email']),
            ])->save();

            $student?->forceFill(['email' => mb_strtolower($input['email'])])->save();
        });
    }
}
