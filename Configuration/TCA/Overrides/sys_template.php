<?php

defined('TYPO3') or die();

call_user_func(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'repeatable_form_elements',
        'Configuration/TypoScript',
        'Repeatable form configuration'
    );
});
