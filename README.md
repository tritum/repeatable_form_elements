<!-- Generated with 🧡 at typo3-badges.dev -->
![TYPO3 extension](https://typo3-badges.dev/badge/repeatable_form_elements/extension/shields.svg)
![Total downloads](https://typo3-badges.dev/badge/repeatable_form_elements/downloads/shields.svg)
![Stability](https://typo3-badges.dev/badge/repeatable_form_elements/stability/shields.svg)
![TYPO3 versions](https://typo3-badges.dev/badge/repeatable_form_elements/typo3/shields.svg)
![Latest version](https://typo3-badges.dev/badge/repeatable_form_elements/version/shields.svg)

# Custom form element "Repeatable container"

This TYPO3 extension adds a custom form element "Repeatable container" to the
TYPO3 form framework. It displays one/ a set of fields which can be duplicated
and removed if desired. Any existing validation is copied as well. All form
finishers will be aware of the copied field(s).

## Installation

Copy the extension folder to `\typo3conf\ext\ `, upload it via the extension
manager or add it to your composer.json (`composer require tritum/repeatable-form-elements`).
Add the static TypoScript configuration to your TypoScript template.

We have tested with TYPO3 v11.5 and v12.4.

## Usage

Open the TYPO3 form editor and create a new form/ open an existing one. Add a
new element to your form. The modal will list the new custom form element
"Repeatable container".

Add the desired fields with the favored validators to the "Repeatable container".

The frontend will render the "Repeatable container" as fieldset. In addition to the
included form elements it will display buttons for copying/ removing new sets of fields.

The newly implemented extended version of SaveToDatabaseFinisher can be used as seen [here](Resources/Private/ExampleFormDefinitions/extended-save-to-database-finisher.form.yaml).

## Configuration

To deactivate the copying of variants, the feature `repeatableFormElements.copyVariants` can be used

## Extendability

The following options can be used to extend the behavior when copying.

| Name | Description                                                      |
| ---- |------------------------------------------------------------------|
| CopyVariantEvent | Extend manipulation of copied variants or disable specific ones. |

## Credits

This TYPO3 extension was created by Ralf Zimmermann (https://dreistrom.land).

## Thank you

Nora Winter - "Faktenkopf" at www.faktenhaus.de - sponsored this great extension.
The fine people at www.b13.de connected all the people involved.

Elias Häußler - haeussler.dev - for helping with TYPO3v11 compatability and providing
the beautiful [TYPO3 badges](https://typo3-badges.dev). Use them. Give him some kudos!

Uwe - Hawkeye1909 - for removing jQuery as dependency.

Alexander Opitz @ extrameile-gehen.de - for his work on saving repeatable elements to database.


especially to all others who have contributed to the improvement of the extension.
