<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * What updating vendored core to an upstream release would actually change, file by file
 * (Leandro, 2026-09-07: "Fix the updater paymenter function to work as correctly — update
 * only possible and necessary files without changing functions and designs").
 */
class ReleasePlan
{
    /** The vendored core directories a release actually governs. */
    public const CORE_PATHS = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'public'];

    /** Files that only ever differ, and say nothing useful when listed. */
    private const IGNORE = ['.gitignore', '.gitkeep', 'public/build', 'public/storage', 'public/hot'];

    public function __construct(private string $version) {}

    /**
     * Download the release and work out the plan.
     *
     * @return array{version: string, counts: array<string,int>, files: array<int, array{path: string, state: string}>, error: ?string}
     */
    public function build(): array
    {
        $touchpoints = static::touchpointFiles();

        // Refuse rather than guess. With no touchpoint list every modified core file
        // would be classified "safe to take", which is the most dangerous answer this
        // class can give and the one that looks most reassuring. It happened for real:
        // docs/ was not mounted into the container, so the list came back empty and forty
        // changed files — ExtensionHelper and UserResource among them — read as safe.
        if ($touchpoints === []) {
            return [
                'version' => $this->version,
                'counts' => [],
                'files' => [],
                'error' => 'docs/CORE-TOUCHPOINTS.md could not be read, so the files carrying '
                    . 'our own changes cannot be identified. Refusing to show a plan that would '
                    . 'call every one of them safe to take.',
            ];
        }

        try {
            $root = $this->fetchRelease();
        } catch (\Throwable $exception) {
            return [
                'version' => $this->version,
                'counts' => [],
                'files' => [],
                'error' => $exception->getMessage(),
            ];
        }
        $files = [];
        $counts = ['same' => 0, 'changed' => 0, 'touchpoint' => 0, 'new' => 0];

        foreach (self::CORE_PATHS as $dir) {
            $from = $root . '/' . $dir;

            if (!is_dir($from)) {
                continue;
            }

            foreach (File::allFiles($from) as $file) {
                $relative = $dir . '/' . str_replace('\\', '/', $file->getRelativePathname());

                if ($this->ignored($relative)) {
                    continue;
                }

                $mine = base_path($relative);

                if (!is_file($mine)) {
                    $counts['new']++;
                    $files[] = ['path' => $relative, 'state' => 'new'];

                    continue;
                }

                if (md5_file($file->getPathname()) === md5_file($mine)) {
                    $counts['same']++;

                    continue;
                }

                $state = isset($touchpoints[$relative]) ? 'touchpoint' : 'changed';
                $counts[$state]++;
                $files[] = ['path' => $relative, 'state' => $state];
            }
        }

        // Touchpoints first — they are the ones a human has to decide about — then the
        // rest alphabetically, so the same release always reads the same way.
        usort($files, fn ($a, $b) => [$a['state'] === 'touchpoint' ? 0 : 1, $a['path']]
            <=> [$b['state'] === 'touchpoint' ? 0 : 1, $b['path']]);

        File::deleteDirectory(dirname($root));

        return ['version' => $this->version, 'counts' => $counts, 'files' => $files, 'error' => null];
    }

    private function ignored(string $relative): bool
    {
        foreach (self::IGNORE as $skip) {
            if ($relative === $skip || str_starts_with($relative, $skip . '/') || str_ends_with($relative, '/' . $skip)) {
                return true;
            }
        }

        return false;
    }

    /** A directory this process can actually write to. */
    private function workspace(): string
    {
        $unique = $this->version . '-' . bin2hex(random_bytes(4));

        foreach ([storage_path('app/adminops-release'), sys_get_temp_dir() . '/adminops-release'] as $base) {
            $path = $base . '/' . $unique;

            try {
                File::ensureDirectoryExists($path, 0777);

                // ensureDirectoryExists succeeds when the directory already exists, even
                // if it belongs to someone else, so writing is the only real test.
                if (is_writable($path)) {
                    return $path;
                }
            } catch (\Throwable $exception) {
                // Try the next candidate.
            }
        }

        throw new \RuntimeException('No writable directory available to unpack the release into.');
    }

    /**
     * Download and unpack the tagged release, returning the path its files sit under.
     *
     * @throws \RuntimeException
     */
    private function fetchRelease(): string
    {
        $url = 'https://github.com/Paymenter/Paymenter/archive/refs/tags/v' . $this->version . '.tar.gz';
        $work = $this->workspace();
        $archive = $work . '/release.tar.gz';

        $response = Http::timeout(120)->withOptions(['sink' => $archive])->get($url);

        if (!$response->successful() || !is_file($archive) || filesize($archive) < 1024) {
            File::deleteDirectory($work);

            throw new \RuntimeException('Could not download release v' . $this->version . ' from GitHub (HTTP ' . $response->status() . ').');
        }

        // tar rather than PharData: the release is gzipped, and PharData needs the
        // decompressed copy on disk first, which doubles the unpack for no gain.
        exec('tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($work) . ' 2>&1', $output, $status);

        if ($status !== 0) {
            File::deleteDirectory($work);

            throw new \RuntimeException('Could not unpack the release: ' . implode(' ', $output));
        }

        // GitHub tarballs nest everything under Paymenter-<version>/.
        $roots = array_values(array_filter(File::directories($work)));

        if ($roots === []) {
            File::deleteDirectory($work);

            throw new \RuntimeException('The release archive was empty.');
        }

        return $roots[0];
    }

    /**
     * The core files `docs/CORE-TOUCHPOINTS.md` records a modification in, read from the
     * document itself rather than duplicated here — the document is what gets updated when
     * a touchpoint is added, and a second copy would go stale the first time that happened.
     *
     * @return array<string, true>
     */
    public static function touchpointFiles(): array
    {
        $doc = base_path('docs/CORE-TOUCHPOINTS.md');

        if (!is_file($doc)) {
            return [];
        }

        // Every backticked path anywhere in the document, not only the ones on a
        // "**File:**" line. Touchpoint 9 named its file in the *heading* instead, so a
        // parser that trusted that convention missed it — and on 2026-09-07 that let the
        // 1.5.8 upgrade overwrite app/Models/Plan.php as though it were an ordinary file.
        // Over-matching is the safe direction here: a path mentioned in passing is
        // reported as ours and a human looks at it, which costs a glance. Missing one
        // silently deletes a customisation.
        preg_match_all(
            '/`([^`]+\.(?:php|blade\.php|js|css|json))`/',
            (string) file_get_contents($doc),
            $paths,
        );

        $files = [];

        foreach ($paths[1] ?? [] as $path) {
            $files[ltrim(trim($path), '/')] = true;
        }

        return $files;
    }
}
