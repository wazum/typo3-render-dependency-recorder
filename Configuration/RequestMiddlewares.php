<?php

declare(strict_types=1);

use Wazum\RenderDependencyRecorder\Middleware\RecorderMiddleware;

return [
    'frontend' => [
        'wazum/render-dependency-recorder/recorder' => [
            'target' => RecorderMiddleware::class,
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
            'before' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
        ],
    ],
];
