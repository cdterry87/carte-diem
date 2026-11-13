<?php

use App\Actions\Notifications\SendListActivityDigests;
use App\Actions\Notifications\SendStapleReminders;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('reminders:send', function (SendStapleReminders $reminders) {
    $this->info('Sent '.$reminders->handle().' reminders.');
})->purpose('Send staple reminders that are due');

Artisan::command('notifications:send-digests', function (SendListActivityDigests $digests) {
    $this->info('Sent '.$digests->handle().' digests.');
})->purpose('Send batched list activity notifications');

Schedule::command('reminders:send')->hourly()->withoutOverlapping();
Schedule::command('notifications:send-digests')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('demo:reset')->daily()->when(fn (): bool => (bool) config('demo.mode'));
