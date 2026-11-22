<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $passwordRule = 'Das Passwort muss mindestens 8 Zeichen lang sein, Buchstaben und Ziffern enthalten und mit einem Großbuchstaben beginnen.';

        return [
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.string' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'email.max' => 'Die E-Mail-Adresse darf höchstens 255 Zeichen lang sein.',
            'password.required' => 'Bitte gib dein Passwort ein.',
            'password.string' => $passwordRule,
            'password.min' => $passwordRule,
            'password.letters' => $passwordRule,
            'password.regex' => $passwordRule,
        ];
    }
}
