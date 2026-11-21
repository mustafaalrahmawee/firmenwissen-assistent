<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-demo-user')]
#[Description('Create the demo user or reset it and end all of its sessions')]
class CreateDemoUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var array{name: string, email: string, password: string} $credentials */
        $credentials = config('demo.user');

        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'starts_with:demo'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            $this->error('Der Demo-Zugang ist falsch konfiguriert (DEMO_USER_* in .env):');

            foreach ($validator->errors()->all() as $message) {
                $this->line("  - {$message}");
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($credentials): User {
            $user = User::updateOrCreate(
                ['email' => $credentials['email']],
                ['name' => $credentials['name'], 'password' => $credentials['password']],
            );

            $user->tokens()->delete();

            return $user;
        });

        $this->info($user->wasRecentlyCreated
            ? "Demo-Zugang {$user->email} angelegt."
            : "Demo-Zugang {$user->email} zurückgesetzt, alle Anmeldungen beendet.");

        return self::SUCCESS;
    }
}
