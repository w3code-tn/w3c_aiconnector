<?php

$EM_CONF['w3c_aiconnector'] = [
    'title' => 'W3CODE AI Connector',
    'description' => 'Connector to different AI models',
    'category' => 'services',
    'state' => 'beta',
    'author' => 'Mehdi Guermazi',
    'author_email' => 'mehdi.guermazi@w3code.tn',
    'author_company' => 'W3CODE',
    'version' => '14.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.4.0-8.5.99',
            'typo3' => '14.3.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
