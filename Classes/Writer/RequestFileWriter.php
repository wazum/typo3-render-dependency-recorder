<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Writer;

use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

final class RequestFileWriter
{
    public function write(RecorderContext $recorder, string $outputDir, string $projectPath): string
    {
        $runSegment = $this->sanitiseSegment($recorder->runId());
        $directory = rtrim($outputDir, '/') . '/requests/' . $runSegment;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create recorder directory: ' . $directory, 1751371200);
        }

        $prefix = rtrim($projectPath, '/') . '/';
        $body = [
            'key' => $recorder->key(),
            'runId' => $recorder->runId(),
            'renderedTemplates' => array_map(
                static fn (string $path): string => str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path,
                $recorder->templates(),
            ),
            'assets' => $recorder->assets(),
        ];

        $file = $directory . '/' . bin2hex(random_bytes(16)) . '.json';
        file_put_contents($file, json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $file;
    }

    private function sanitiseSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?? '';
        return $safe === '' ? 'default' : $safe;
    }
}
