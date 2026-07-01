<?php

declare(strict_types=1);

namespace Wazum\RenderDependencyRecorder\Writer;

use Wazum\RenderDependencyRecorder\Recorder\RecorderContext;

final class RequestFileWriter
{
    public function write(RecorderContext $recorder, string $outputDir, string $projectPath, array $roots = []): string
    {
        $runSegment = $this->sanitiseSegment($recorder->runId());
        $directory = rtrim($outputDir, '/') . '/requests/' . $runSegment;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create recorder directory: ' . $directory, 1751371200);
        }

        $prefix = rtrim($projectPath, '/') . '/';
        $relativeFiles = array_map(
            static function (string $path) use ($prefix): string {
                $resolved = realpath($path);
                $absolute = $resolved !== false ? $resolved : $path;
                return str_starts_with($absolute, $prefix) ? substr($absolute, strlen($prefix)) : $absolute;
            },
            $recorder->files(),
        );
        $body = [
            'key' => $recorder->key(),
            'runId' => $recorder->runId(),
            'depth' => $recorder->depth(),
            'files' => $this->filterToRoots($relativeFiles, $roots),
            'assets' => $recorder->assets(),
        ];

        $json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $file = $directory . '/' . bin2hex(random_bytes(16)) . '.json';
        if (file_put_contents($file, $json) === false) {
            throw new \RuntimeException('Cannot write recorder file: ' . $file, 1751371300);
        }

        return $file;
    }

    private function sanitiseSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?? '';
        return $safe === '' ? 'default' : $safe;
    }

    /**
     * @param array<string> $files
     * @param array<string> $roots
     * @return array<string>
     */
    private function filterToRoots(array $files, array $roots): array
    {
        if ($roots === []) {
            return $files;
        }

        return array_values(array_filter(
            $files,
            static function (string $file) use ($roots): bool {
                foreach ($roots as $root) {
                    if (str_starts_with($file, $root)) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }
}
