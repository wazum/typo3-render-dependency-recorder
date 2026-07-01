<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Recorder;

use TYPO3\CMS\Core\SingletonInterface;

final class RecorderContext implements SingletonInterface
{
    private bool $active = false;

    private ?string $key = null;

    private string $runId = '';

    private string $depth = '';

    /** @var array<string, true> */
    private array $files = [];

    /** @var array<string, true> */
    private array $assets = [];

    public function activate(string $key, string $runId, string $depth = 'shallow'): void
    {
        $this->active = true;
        $this->key = $key;
        $this->runId = $runId;
        $this->depth = $depth;
        $this->files = [];
        $this->assets = [];
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->key = null;
        $this->runId = '';
        $this->depth = '';
        $this->files = [];
        $this->assets = [];
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function key(): ?string
    {
        return $this->key;
    }

    public function runId(): string
    {
        return $this->runId;
    }

    public function depth(): string
    {
        return $this->depth;
    }

    public function recordFile(string $absolutePath): void
    {
        if (!$this->active || $absolutePath === '') {
            return;
        }
        $this->files[$absolutePath] = true;
    }

    public function recordAssetEntry(string $entry): void
    {
        if (!$this->active || $entry === '') {
            return;
        }
        $this->assets[$entry] = true;
    }

    /** @return array<string> */
    public function files(): array
    {
        $paths = array_keys($this->files);
        sort($paths);
        return $paths;
    }

    /** @return array<string> */
    public function assets(): array
    {
        $entries = array_keys($this->assets);
        sort($entries);
        return $entries;
    }
}
