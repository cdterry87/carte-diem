<?php

namespace App\Console\Commands;

use App\Support\DemoAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('demo:reset')]
#[Description('Create the demo account, or restore it and its household to their original state')]
class DemoResetCommand extends Command
{
    public function handle(DemoAccount $demo): int
    {
        try {
            $user = $demo->reset();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Demo account ready: {$user->email}");

        return self::SUCCESS;
    }
}
