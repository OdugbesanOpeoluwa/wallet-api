<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessOutbox;
use App\Jobs\VerifyTransactions;
use App\Jobs\ReconcileBalances;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ProcessOutbox)->everyMinute();
Schedule::job(new VerifyTransactions)->everyFiveMinutes();
Schedule::job(new ReconcileBalances)->daily();