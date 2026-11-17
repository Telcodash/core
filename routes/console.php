<?php

use Illuminate\Support\Facades\Schedule;

// Fallback for the queue service: retry undelivered tasks and poll tasks whose webhooks were missed.
Schedule::command('tasks:sync')->everyFiveMinutes()->withoutOverlapping();
