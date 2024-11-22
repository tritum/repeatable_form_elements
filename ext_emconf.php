<?php
$EM_CONF['repeatable_form_elements'] = [
    'title' => 'Repeatable form elements',
    'description' => 'Adds a new form element which allows the editor to create new container elements with any type fields in them. In the frontend, a user can create any number of new containers. This is an extension for TYPO3 CMS.',
    'category' => 'fe',
    'state' => 'stable',
    'author' => 'Ralf Zimmermann, Elias Häußler, Christian Seyfferth',
    'author_email' => 'r.zimmermann@dreistrom.land, elias@haeussler.dev, c.seyfferth@dreistrom.land',
    'version' => '4.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.23-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
