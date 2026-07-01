<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Render Dependency Recorder',
    'description' => 'Records the templates, executed PHP, and asset entries a request renders, keyed by an opaque header value.',
    'category' => 'misc',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
];
