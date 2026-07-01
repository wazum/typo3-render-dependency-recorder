# Fluid Render Recorder for TYPO3 CMS

[![Supported TYPO3](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://get.typo3.org/)
[![Supported PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Record which **files a request actually used to render** — Fluid templates, executed PHP, and frontend asset entries — keyed by an opaque header, written as one small JSON file per request. It turns a live page render into a precise dependency map: *for this page, these are the exact template/layout/partial/content-element files, the ViewHelpers/DataProcessors/services that executed, and the asset entrypoints it loaded.*

The recording is genuinely dynamic — it captures what *rendered and ran*, including which content elements a database-driven page assembled, which no static analysis can tell you.

Capture has two depths:

- **shallow** (always available, no extra tooling): Fluid template files + asset entries.
- **deep** (adds executed PHP via Xdebug coverage): every project PHP class the render actually executed.

> [!NOTE]
> This is a build/test-time tool. It is **inert unless activated** by a request header **and** running in an allowed application context (`Testing` by default), so it never touches production traffic. Install it as a `--dev` dependency.

## Contents

- [What it captures](#what-it-captures)
- [Requirements](#requirements)
- [Installation](#installation)
- [Recording a request](#recording-a-request)
- [Deep capture (executed PHP)](#deep-capture-executed-php)
- [Frictionless deep recording with DDEV](#frictionless-deep-recording-with-ddev)
- [Output](#output)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [Capturing components (opt-in)](#capturing-components-opt-in)
- [Capturing inline or custom assets (opt-in)](#capturing-inline-or-custom-assets-opt-in)
- [Safety](#safety)
- [A note on consumers](#a-note-on-consumers)

## What it captures

For every recorded request, filtered to your configured project roots (e.g. `source/`, `local/`):

- **`files`** — every project file the render used, matched **directly** against a `git diff`:
  - Fluid source files, whatever their role: page templates, layouts, partials, content-element templates, and (opt-in) `<c:…>` component templates;
  - (deep) executed PHP: ViewHelpers, DataProcessors, EventListeners, services, etc.
  Paths are repo-relative and symlink-resolved (so composer path-repo extensions installed under `vendor/` resolve back to their real `local/…` source).
- **`assets`** — frontend asset **entrypoints** emitted for the page: Vite entries (e.g. `source/typescript/main.ts`) recovered from the `AssetCollector`. Matched **indirectly** by a consumer through the Vite import graph. Build-output hashes and external URLs are deliberately excluded.
- **`depth`** — `shallow` or `deep` (see below).

## Requirements

- TYPO3 **13.4 LTS**
- PHP **8.2+**
- Xdebug with `xdebug.mode=coverage` — **only for deep capture**. Shallow capture needs nothing extra. In DDEV this is handled for you (see [Frictionless deep recording with DDEV](#frictionless-deep-recording-with-ddev)).

## Installation

```bash
composer require --dev wazum/typo3-fluid-render-recorder
```

```bash
vendor/bin/typo3 extension:setup --extension=fluid_render_recorder
```

Installing it as a dev dependency means a production build (`composer install --no-dev`) omits it entirely — a second line of defence on top of the context gate.

## Recording a request

Send two request headers to the page(s) you want to record:

| Header | Required | Meaning |
| --- | --- | --- |
| `X-Render-Record` | yes | An **opaque recording key**. The extension does not interpret it; it becomes the manifest key you group results by. |
| `X-Render-Run` | no | A run id. All requests sharing it are written into one per-run directory. Defaults to `default`. |
| `X-Render-Depth` | no | Set to `deep` to also capture executed PHP (requires Xdebug coverage). Otherwise `shallow`. |

The header is honoured only in an allowed application context (`Testing` by default). Any client works — a test runner, a crawler, or a plain `curl`:

```bash
curl -H 'X-Render-Record: home' -H 'X-Render-Run: run-1' https://example.ddev.site/
```

## Deep capture (executed PHP)

Shallow capture (templates + assets) tells you which *Fluid files* rendered. Deep capture adds the **PHP that actually executed** — the ViewHelpers, DataProcessors, EventListeners and services behind the page — so a change to any of them can be mapped to the pages that used them.

When `X-Render-Depth: deep` is sent **and** Xdebug coverage is available, the middleware wraps the render in `xdebug_start_code_coverage()` … `xdebug_get_code_coverage()`, keeps the executed files under your roots, and tags the entry `depth: deep`. If coverage isn't available it silently degrades to `shallow` — never an error.

Coverage records *executed* files (not merely loaded), so it is immune to autoload/opcache warm-worker state and to DI-container-compilation noise. It costs ~2–5× render time, which is why deep is opt-in per request and meant for an occasional full record run, not everyday requests.

## Frictionless deep recording with DDEV

Deep capture needs the FPM worker started with `xdebug.mode=coverage`, which can't be toggled per request. A small **DDEV add-on ships with this extension** to make that a one-liner. Install it from the local package path (no public repo needed):

```bash
ddev add-on get vendor/wazum/typo3-fluid-render-recorder/Resources/Private/DdevAddon
```

Then record a page deeply with a single, self-restoring command:

```bash
ddev fluid-record https://your-project.ddev.site/ home run-1
```

It enables Xdebug, flips FPM to coverage mode via `supervisorctl restart php-fpm` (~1s, not a full `ddev restart`), sends the record/run/`deep` headers, and restores the environment afterwards — even on failure. Developers never touch Xdebug config.

## Output

Each recorded request writes one randomly-named file under:

```
var/fluid-render-recorder/requests/<runId>/<random>.json
```

```json
{
    "key": "home",
    "runId": "run-1",
    "depth": "deep",
    "files": [
        "local/site/Resources/Private/PageView/Layouts/Default.html",
        "local/site/Resources/Private/PageView/Pages/Home.html",
        "local/site/Classes/DataProcessing/FooterContainerProcessor.php",
        "local/site/Classes/ViewHelpers/InlineSvgViewHelper.php"
    ],
    "assets": [
        "source/typescript/main.ts",
        "source/typescript/critical.ts"
    ]
}
```

Filenames are random (never derived from the key), so concurrent requests never collide — safe under parallel test runners. Aggregating the per-run files into whatever index you need (grouped by key, unioned, committed to your repo) is left to the consumer.

## Configuration

Extension Configuration (Admin Tools → Settings, or `EXTENSIONS/fluid_render_recorder` in `settings.php`):

| Key | Default | Description |
| --- | --- | --- |
| `recordHeader` | `X-Render-Record` | Name of the header that carries the recording key. |
| `runHeader` | `X-Render-Run` | Name of the header that carries the run id. |
| `depthHeader` | `X-Render-Depth` | Header requesting deep capture when its value is `deep`. |
| `activeContexts` | `Testing` | Comma-separated application-context prefixes in which recording is allowed. |
| `roots` | `source/,local/` | Comma-separated repo-relative prefixes; only files under these are recorded. |

## How it works

- A decorator around the core `ViewFactoryInterface` swaps in a recording `TemplatePaths` that logs each resolved template file, and disables the render's compile cache — via a nulled cache **and** a compile-identifier suffix — so a warm compile cache (even a compiled class already loaded into a long-lived FPM worker) can never hide a template from the recorder.
- A frontend PSR-15 middleware activates recording when the record header is present in an allowed context, disables the page cache for that one request (so a cached page can't be served without rendering), optionally starts Xdebug coverage for deep capture, lets the page render, reads the `AssetCollector` and (deep) the coverage set, writes the per-request file, and deactivates.
- Recorded absolute paths are `realpath`-resolved and filtered to the configured `roots`, so vendor path-repo symlinks collapse to real `local/…` source and everything outside your roots (framework, vendor deps) is dropped.
- Everything is **best-effort**: any recording failure is caught and logged at warning level, never surfacing to the request.

## Capturing components (opt-in)

Fluid renders `<c:…>` components through each component collection's *own* `TemplatePaths`, which the component renderer substitutes into the rendering context — so the decorator above cannot see them. To record component files, have your collections return the shipped recording paths when recording is active:

```php
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use Wazum\FluidRenderRecorder\Fluid\RecordingTemplatePaths;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

public function getTemplatePaths(): TemplatePaths
{
    $paths = $this->buildTemplatePaths();

    if (
        class_exists(RecorderContext::class)
        && ($recorder = GeneralUtility::makeInstance(RecorderContext::class))->isActive()
    ) {
        return RecordingTemplatePaths::fromExisting($paths, $recorder);
    }

    return $paths;
}
```

The `class_exists` guard keeps this a no-op when the extension is not installed (e.g. production). If you cache the built `TemplatePaths`, cache the active and inactive variants separately so an inactive lookup cannot poison a later recording render.

## Capturing inline or custom assets (opt-in)

Assets registered with an entry-recoverable identifier (Vite) or a repo-relative source are captured automatically. Assets whose source cannot be recovered from the `AssetCollector` — e.g. inline CSS/JS keyed by a content hash — must announce themselves where they are registered:

```php
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

if (
    class_exists(RecorderContext::class)
    && ($recorder = GeneralUtility::makeInstance(RecorderContext::class))->isActive()
) {
    $recorder->recordAssetEntry('source/critical.ts');
}
```

## Safety

- **Context-gated:** the middleware returns untouched unless the current context matches `activeContexts` (default `Testing`) — even if the header is present.
- **`--dev` install:** omitted from production builds entirely.
- **Non-invasive:** recording never fails a request; errors are logged, not thrown.
- **Complete under parallelism:** per-request files avoid write races; the compile-cache defeats mean a warm cache never causes silent under-recording.

## A note on consumers

The manifest is a general render-dependency map, not tied to any one tool. A typical consumer is a change-based **test or page selector**: record the suite once, then map a `git diff` to exactly the tests whose pages rendered the changed files — so only affected tests run. Other uses (dependency explorers, architectural fitness checks over observed runtime edges) read the same data unchanged.

## License

GPL-2.0-or-later. Author: Wolfgang Klinger &lt;wolfgang@wazum.com&gt;.
