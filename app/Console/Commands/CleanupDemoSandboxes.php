<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupDemoSandboxes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-demo-sandboxes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired demo sandboxes (schemas)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expired = \Illuminate\Support\Facades\DB::table('public.demo_sandboxes')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $sandbox) {
            $this->info("Dropping schema {$sandbox->schema_name}...");
            \Illuminate\Support\Facades\DB::statement("DROP SCHEMA IF EXISTS {$sandbox->schema_name} CASCADE");
            
            \Illuminate\Support\Facades\DB::table('public.demo_sandboxes')
                ->where('id', $sandbox->id)
                ->delete();
        }

        $this->info('Cleanup completed.');
        
        $this->info('Triggering sandbox warm-up process...');
        \Illuminate\Support\Facades\Artisan::call('app:warm-demo-sandboxes');
        $this->info('Warm-up completed.');
    }
}
