<?php

namespace Paymenter\Extensions\Others\TermLimits\Console;

use Illuminate\Console\Command;
use Paymenter\Extensions\Others\TermLimits\Support\Sweeper;

/**
 * The sweeper, by hand.
 *
 * The every-minute schedule calls {@see Sweeper::run()} directly; this is the same pass for
 * an operator who wants to see it, and `--dry-run` is the safe way to find out what a fresh
 * install is about to stop before it stops it.
 *
 *   php artisan term-limits:enforce --dry-run
 *   php artisan term-limits:enforce
 *   php artisan term-limits:enforce --backfill   open terms for services already running
 */
class EnforceTerms extends Command
{
    protected $signature = 'term-limits:enforce
        {--dry-run : List what would be stopped and change nothing}
        {--backfill : Open terms for active services that have none, then sweep}';

    protected $description = 'Stop fixed-term services whose contracted time has run out';

    public function handle(): int
    {
        if ($this->option('backfill')) {
            $opened = Sweeper::backfill();
            $this->info('Opened ' . $opened . ' ' . str('term')->plural($opened) . ' for services already running.');
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = Sweeper::run($dryRun);

        foreach ($result['lines'] as $line) {
            $this->line('  ' . $line);
        }

        if ($result['lines'] === []) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d stopped, %d released. Expiry %s.',
            $dryRun ? 'Dry run — nothing changed. ' : '',
            $result['stopped'],
            $result['released'],
            Sweeper::terminates() ? 'terminates' : 'suspends',
        ));

        return self::SUCCESS;
    }
}
