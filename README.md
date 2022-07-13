# Custom form element "Repeatable container"

This TYPO3 extension adds a custom form element "Repeatable container" to the
TYPO3 form framework. It displays one/ a set of fields which can be duplicated
and removed if desired. Any existing validation is copied as well. All form
finishers will be aware of the copied field(s).

## Installation

Copy the extension folder to `\typo3conf\ext\ `, upload it via the extension
manager or add it to your composer.json. Add the static TypoScript configuration
to your TypoScript template. Make sure, jQuery is available in the frontend.
We have tested with TYPO3 v11 and jQuery v2.2.4.

## Usage

Open the TYPO3 form editor and create a new form/ open an existing one. Add a
new element to your form. The modal will list the new custom form element
"Repeatable container".

Add the desired fields with the favored validators to the "Repeatable container".

The frontend will render the "Repeatable container" as fieldset. In addition to the
included form elements it will display buttons for copying/ removing new sets of fields.

## State of development

This extension is still in beta phase. Right now, a bunch of people are testing the
implementation. Some parts still need some love. Do not use in production right now.

## Credits

This TYPO3 extension was created by Ralf Zimmermann (https://www.tritum.de).

## Thank you

Nora Winter - "Faktenkopf" at www.faktenhaus.de - sponsored this great extension.
The fine people at www.b13.de connected all the people involved.

Elias Häußler - haeussler.dev - for helping with TYPO3v11 compatability.