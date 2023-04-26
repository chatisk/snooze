<?php

namespace Thomasjohnkane\Snooze;

use Illuminate\Console\Scheduling\Schedule;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__.'/../config/snooze.php';
    const MIGRATIONS_PATH = __DIR__.'/../migrations/';

    protected $commands = [
        Console\Commands\SendScheduledNotifications::class,
        Console\Commands\PruneScheduledNotifications::class,
    ];

    public function boot()
    {

        //Check if snooze should schedule the commands automatically
        if (config('snooze.scheduleCommands', true)) {

            // Schedule base command to run every minute
            $this->app->booted(function () {

                //Ensure the schedule is available if snooze is disabled but a prune age is set
                $schedule = $this->app->make(Schedule::class);

                if (! config('snooze.disabled')) {
                    $frequency = config('snooze.sendFrequency', 'everyMinute');
                    if (config('snooze.onOneServer', false)) {
                        if (config('snooze.runsInMultiTenancy')) {
                            $schedule->command('tenants:run snooze:send')->{$frequency}()->onOneServer();
                        } else {
                            $schedule->command('snooze:send')->{$frequency}()->onOneServer();
                        }
                    } else {
                        if (config('snooze.runsInMultiTenancy')) {
                            $schedule->command('tenants:run snooze:send')->{$frequency}();
                        } else {
                            $schedule->command('snooze:send')->{$frequency}();
                        }
                    }
                }

                if (config('snooze.pruneAge') !== null) {
                    if (config('snooze.onOneServer', false)) {
                        if (config('snooze.runsInMultiTenancy')) {
                            $schedule->command('tenants:run snooze:prune')->daily()->onOneServer();

                        } else {
                            $schedule->command('snooze:prune')->daily()->onOneServer();
                        }
                    } else {
                        if (config('snooze.runsInMultiTenancy')) {
                            $schedule->command('tenants:run snooze:prune')->daily();
                        } else {
                            $schedule->command('snooze:prune')->daily();
                        }
                    }
                }
            });
        }

        $this->publishes([
            self::CONFIG_PATH => config_path('snooze.php'),
        ], 'config');

        $this->publishes([
            self::MIGRATIONS_PATH => database_path('tenants'),
        ], 'migrations-multitenancy');

        $this->loadMigrationsFrom(__DIR__.'/../migrations');

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'snooze'
        );

        $this->app->bind('snooze', function () {
            return new Snooze();
        });
    }
}
