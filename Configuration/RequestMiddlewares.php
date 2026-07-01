<?php

declare(strict_types=1);

use Wazum\FluidRenderRecorder\Middleware\RecorderMiddleware;

return [
    'frontend' => [
        'wazum/fluid-render-recorder/recorder' => [
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
