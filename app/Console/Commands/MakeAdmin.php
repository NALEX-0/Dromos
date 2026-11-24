<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    protected $signature = 'dromos:make-admin {email}';

    protected $description = 'Create an administrator or promote an existing user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $validation = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validation->fails()) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Admin password');

        if (mb_strlen($password) < 8) {
            $this->error('The password must contain at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name ??= 'Dromos Admin';
        $user->password = $password;
        $user->role = 'admin';
        $user->save();

        $this->info("Administrator {$email} is ready.");

        return self::SUCCESS;
    }
}
