<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-demo-user')]
#[Description('Create the demo user with its workspace or reset both and end all of its sessions')]
class CreateDemoUser extends Command
{
    /**
     * Execute the console command.
     *
     * The demo workspace is the workspace of the demo user. A new one is created
     * only together with a new demo user, in the same transaction.
     */
    public function handle(): int
    {
        /** @var array{name: string, email: string, password: string} $credentials */
        $credentials = config('demo.user');
        $tenantName = config('demo.tenant.name');

        $validator = Validator::make([...$credentials, 'tenant_name' => $tenantName], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'starts_with:demo'],
            'password' => ['required', 'string', Password::defaults()],
            'tenant_name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error('Der Demo-Zugang ist falsch konfiguriert (DEMO_USER_* und DEMO_TENANT_NAME in .env):');

            foreach ($validator->errors()->all() as $message) {
                $this->line("  - {$message}");
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($credentials, $tenantName): User {
            $user = User::firstWhere('email', $credentials['email']);

            if ($user === null) {
                $user = new User(['email' => $credentials['email']]);
                $user->tenant()->associate(Tenant::create(['name' => $tenantName]));
            } else {
                $user->tenant->update(['name' => $tenantName]);
            }

            $user->fill(['name' => $credentials['name'], 'password' => $credentials['password']])->save();
            $user->tokens()->delete();

            return $user;
        });

        $this->info($user->wasRecentlyCreated
            ? "Demo-Zugang {$user->email} angelegt, Arbeitsbereich „{$tenantName}“."
            : "Demo-Zugang {$user->email} zurückgesetzt, alle Anmeldungen beendet, Arbeitsbereich „{$tenantName}“.");

        return self::SUCCESS;
    }
}
