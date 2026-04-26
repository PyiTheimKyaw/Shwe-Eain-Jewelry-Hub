<?php

namespace App\Console\Commands;

use App\Services\FirebaseInquiryService;
use Illuminate\Console\Command;

class BackfillInquiryImageCandidatesCommand extends Command
{
    protected $signature = 'inquiries:backfill-image-candidates {--inquiry_id=}';

    protected $description = 'Backfill Firestore image messages into inquiry image candidate records.';

    public function handle(FirebaseInquiryService $firebase): int
    {
        $inquiryId = $this->option('inquiry_id');
        $resolvedInquiryId = is_numeric($inquiryId) ? (int) $inquiryId : null;

        $stats = $firebase->backfillInquiryImageCandidates($resolvedInquiryId);
        $this->info(sprintf(
            'Backfill done. checked=%d created=%d updated=%d',
            $stats['checked'],
            $stats['created'],
            $stats['updated'],
        ));

        return self::SUCCESS;
    }
}
