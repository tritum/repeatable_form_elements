<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "repeatable_form_elements".
 *
 * Copyright (C) 2018 Ralf Zimmermann TRITUM GmbH <ralf.zimmermann@tritum.de>
 * Copyright (C) 2021 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace TRITUM\RepeatableFormElements\Configuration;

use TRITUM\RepeatableFormElements\Hooks\FormHooks;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extension
 *
 * @author Ralf Zimmermann TRITUM GmbH <ralf.zimmermann@tritum.de>
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class Extension
{
    public const KEY = 'repeatable_form_elements';

    public static function addTypoScriptSetup(): void
    {
        ExtensionManagementUtility::addTypoScriptSetup(trim('
            module.tx_form {
                settings {
                    yamlConfigurations {
                        1511193633 = EXT:repeatable_form_elements/Configuration/Yaml/FormSetup.yaml
                        1511193634 = EXT:repeatable_form_elements/Configuration/Yaml/FormSetupBackend.yaml
                    }
                }
            }
        '));
    }

    public static function registerIcons(): void
    {
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $iconRegistry->registerIcon(
            't3-form-icon-repeatable-container',
            SvgIconProvider::class,
            ['source' => 'EXT:repeatable_form_elements/Resources/Public/Icons/t3-form-icon-repeatable-container.svg']
        );
    }

    public static function registerHooks(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1511196413] = FormHooks::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1511196413] = FormHooks::class;
    }
}
