<?php

namespace Database\Seeders;

use App\Support\DemoAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local data is the demo account: the DEMO_* settings in .env, with a household and a few
     * months of lists, shops, and spending. `demo:reset` rebuilds the same thing.
     */
    public function run(DemoAccount $demo): void
    {
        $user = $demo->reset();

        $this->command->info("Demo account ready: {$user->email}");
    }
}
