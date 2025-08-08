<?php

namespace App\Console\Commands;
use App\Notifications\BirthdayWishes;
use Illuminate\Support\Facades\Notification;
use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

// Schedule::command('app:send-birthday-messages')->daily();
Schedule::command('app:send-birthday-messages')->everyMinute();

class SendBirthdayMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-birthday-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send birthday messages to users.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //get and filter users and check if today is their birthday
        $today = Carbon::now();
        $users = User::whereDay('birthdate', $today->day)
            ->whereMonth('birthdate', $today->month)
            ->get();


        foreach ($users as $user) {
            Notification::route('mail', $user['email'])
                ->notify(new BirthdayWishes($user));
            $this->info("Birthday message job dispatched for {$user->name}.");
        }

        $this->info('Birthday messages sent.');

    }
}
