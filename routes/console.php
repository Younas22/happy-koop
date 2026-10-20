<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Cancels expired pending, unpaid checkouts. Safe by construction: the command
 * only ever touches orders that are still pending AND unpaid AND past expiry.
 */
Schedule::command('happykoop:clean-expired-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
