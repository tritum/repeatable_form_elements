# Custom form element "Repeatable container"

This TYPO3 extension adds a custom form element "Repeatable container" to the TYPO3 form framework. It displays one/ a
set of fields which can be duplicated and removed if desired. Any existing validation is copied as well. All form
finishers will be aware of the copied field(s).

## Installation

Via composer:

```sh
composer require tritum/repeatable-form-elements
```

through the extension manager or by manually extracting the extension into `typo3conf/ext `.

Include the typo script templates

* Form setup: Includes the necessary YAML files with the form configuration (mandatory)
* JavaScript (global): Includes the javascript, which exposes the `RepeatableContainer` to the global namespace
* JavaScript (bundle): Includes the javascript, which bundle the `RepeatableContainer` class with the necessary
  initialization

The JavaScript does not depend on any frameworks. It works in modern browser, which **excludes** Internet Explorer.

## Usage

Open the TYPO3 form editor and create a new form/ open an existing one. Add a new element to your form. The modal will
list the new custom form element
"Repeatable container".

Add the desired fields with the favored validators to the "Repeatable container".

The frontend will render the "Repeatable container" with the configured fields wrapped inside a row, which is the
repeating element. In addition to the included form elements it will display buttons for adding/removing new sets of
fields.

## Frontend development & build
When you want to make changes to the frontend, please go into the `Resources/Private/Frontend` and install the
necessary node modules by issuing `npm install`. The build it tested with NodeJS 16.

For watcher and continouos build, you can start the Webpack dev server by `npm run dev`. When you want to re-/build
the final javascript files use `npm run build`.

## Credits

This TYPO3 extension was created by Ralf Zimmermann (https://www.tritum.de).

## Thank you

Nora Winter - "Faktenkopf" at www.faktenhaus.de - sponsored this great extension. The fine people at www.b13.de
connected all the people involved.
