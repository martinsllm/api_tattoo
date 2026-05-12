<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

#[Signature('user:make-admin {email : E-mail do usuário a ser promovido a admin}')]
#[Description('Promove um usuário existente ao role "admin", substituindo qualquer role anterior.')]
class MakeUserAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("Usuário com e-mail [{$email}] não encontrado.");

            return Command::FAILURE;
        }

        if ($user->hasRole('admin')) {
            $this->info("Usuário [{$email}] já é admin. Nenhuma alteração necessária.");

            return Command::SUCCESS;
        }

        Role::findOrCreate('admin');

        $user->syncRoles(['admin']);

        $this->info("Usuário [{$email}] promovido a admin com sucesso.");

        return Command::SUCCESS;
    }
}
