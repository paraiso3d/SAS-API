<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SendSmsQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:send-queue';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send pending SMS from queue';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        set_time_limit(0);

        $messages = DB::table('sms_queue')
            ->where('status', 'pending')
            ->get();

        foreach ($messages as $index => $row) {

            $number = $row->phone_number;
            $message = $row->message_text;

            $response = $this->sendViaGoip($number, $message);

            if (str_contains($response, 'Sending')) {
                DB::table('sms_queue')
                    ->where('id', $row->id)
                    ->update(['status' => 'sent']);

                $this->info("Sent to {$number}");
            } else {
                $this->error("Failed: {$number} | {$response}");
            }

            if ($index < count($messages) - 1) {
                sleep(rand(5, 10));
            }
        }
    }
}
