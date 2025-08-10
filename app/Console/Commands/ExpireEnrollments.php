<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireEnrollments extends Command
{
    protected $signature = 'enrollments:expire';
    protected $description = 'Deactivate expired course enrollments';

    public function handle(): int
    {
        $affected = DB::table('course_user')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'suspended', 'updated_at' => now()]);

        $this->info("Expired enrollments updated: {$affected}");
        return self::SUCCESS;
    }
}
