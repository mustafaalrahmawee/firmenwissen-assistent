<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public const string EMAIL_TAKEN = 'Zu dieser E-Mail-Adresse gibt es bereits ein Konto.';

    /**
     * Bcrypt only uses the first 72 bytes of a password; everything after would not count.
     */
    private const int PASSWORD_MAX_BYTES = 72;

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
     * Only these fields are read. A workspace named in the request is ignored,
     * the new account always gets a new workspace of its own.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'email' => [
                'bail',
                'required',
                'string',
                'email',
                'max:255',
                $this->notReservedForDemo(...),
                $this->notTaken(...),
            ],
            'password' => [
                'bail',
                'required',
                'string',
                $this->withoutNullCharacter(...),
                Password::defaults(),
                $this->atMostMaxBytes(...),
                'confirmed',
            ],
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
            'name.required' => 'Bitte gib deinen Namen ein.',
            'name.string' => 'Bitte gib deinen Namen ein.',
            'name.max' => 'Der Name darf höchstens 255 Zeichen lang sein.',
            'company.required' => 'Bitte gib den Namen deiner Firma ein.',
            'company.string' => 'Bitte gib den Namen deiner Firma ein.',
            'company.max' => 'Der Name der Firma darf höchstens 255 Zeichen lang sein.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.string' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'email.max' => 'Die E-Mail-Adresse darf höchstens 255 Zeichen lang sein.',
            'password.required' => 'Bitte gib ein Passwort ein.',
            'password.string' => $passwordRule,
            'password.min' => $passwordRule,
            'password.letters' => $passwordRule,
            'password.regex' => $passwordRule,
            'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
        ];
    }

    /**
     * Addresses starting with "demo" belong to the demo account, so the command for the
     * demo account can never take over a registered account. Accents, other letter forms
     * and capitals do not get around it.
     */
    private function notReservedForDemo(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && str_starts_with(Str::lower(Str::ascii($value)), 'demo')) {
            $fail('E-Mail-Adressen, die mit „demo“ beginnen, sind für den Demo-Zugang reserviert.');
        }
    }

    /**
     * An address is taken in any spelling of capitals. Two registrations at the same moment
     * are stopped by the unique index, see AuthController::register().
     */
    private function notTaken(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && User::whereRaw('lower(email) = ?', [Str::lower($value)])->exists()) {
            $fail(self::EMAIL_TAKEN);
        }
    }

    /**
     * Bcrypt cannot hash a password with a null character and would fail with a server error.
     */
    private function withoutNullCharacter(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && str_contains($value, "\0")) {
            $fail('Das Passwort darf kein Nullzeichen enthalten.');
        }
    }

    private function atMostMaxBytes(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > self::PASSWORD_MAX_BYTES) {
            $fail('Das Passwort ist zu lang. Erlaubt sind 72 Byte: 72 Zeichen ohne Umlaute und Sonderzeichen, mit ihnen weniger.');
        }
    }
}
