<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:generate')->monthlyOn(1, '02:00')->withoutOverlapping();
Schedule::command('invoices:overdue-check')->dailyAt('03:00');
Schedule::command('leases:expiry-notify')->weeklyOn(1, '08:00');
