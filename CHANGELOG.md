# Version 1.6.1

* Fix deprecation warning for using strstr with `null` as haystack; refactor function while we're at it

# Version 1.6.0

* Keep a shared PropertyAccessor to improve performance of datatables with many columns

# Version 1.5.2

* Fix correct parameterName for LinkColumn empty_value; changed to emptyValue to be consistent with other columns and to let the name be aligned with its getter and setter

# Version 1.5.1

* Add return types to reduce the number of deprecation warnings.

# Version 1.5.0

* Add support for Symfony 7

# Version 1.4.2

* Fix issue where sometimes other value-types (like DateTime objects) can be passed, which throw errors when casting to a string. in that case, the original value is used

# Version 1.4.1

* Fix issue in different php versions; count(null) does not work anymore in higher versions, so adding an extra check to be able to deal with this

# Version 1.4

* Fix XSS issue for columns which are not editable
    * If you want to render plain, unescaped, values; add the `escape` parameter to the column, and set this to false

# Version 1.3

## Fork from https://github.com/stwe/DatatablesBundle

* Dropped support for PHP 7.1
* Dropped support for Symfony 3.4 and <=4.3
* Support for PHP 8
* Support for Symfony 6
