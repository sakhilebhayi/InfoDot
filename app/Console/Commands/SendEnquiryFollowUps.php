<?php

namespace App\Console\Commands;

use App\Services\Enquiries\EnquiryService;
use Illuminate\Console\Command;

/**
 * R-33: on the morning an enquiry's follow-up date arrives, whoever has it gets one notice.
 */
class SendEnquiryFollowUps extends Command
{
    protected $signature = 'business:enquiry-follow-ups';

    protected $description = 'Remind whoever has an enquiry that its follow-up date has come';

    public function handle(EnquiryService $enquiries): int
    {
        $this->info('Follow-up reminders sent: '.$enquiries->sendFollowUps());

        return self::SUCCESS;
    }
}
