<?php

$GLOBALS['TCA']['sys_redirect']['columns']['creation_type']['config']['items'][] = [
    'label' => 'import',
    'value' => \GeorgRinger\RedirectGenerator\Repository\RedirectRepository::CUSTOM_CREATION_TYPE,
];