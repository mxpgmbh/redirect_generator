<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Redirect generator',
    'description' => 'Import + Export redirects',
    'category' => 'frontend',
    'author' => 'Rolf Kiefhaber',
    'author_email' => 'r.kiefhaber@mxp.de',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '1.0.0',
    'constraints' =>
        [
            'depends' => [
                'typo3' => '10.4.0-10.4.99',
                'redirects' => '^10.4.0-10.4.99',
            ],
            'conflicts' => [],
            'suggests' => [],
        ]
];
