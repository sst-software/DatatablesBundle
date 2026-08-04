# Version 2.0.0

## Breaking changes

* **Root and join aliases can change for some entity names.** `DatatableQueryBuilder::getSafeName()`
  decided whether to prefix a DQL alias with `_` by asking the *database platform* for its SQL keyword list. That is the wrong list - DQL aliases never reach the generated SQL, Doctrine emits its own `t0_`/`c1_` aliases - and the API it used is deprecated in DBAL 4 with no replacement. The check now uses the DQL parser's own reserved words
  (`Doctrine\ORM\Query\TokenType`).

  The old behaviour cannot be preserved. `AbstractPlatform::getReservedKeywordsList()` is deprecated
  in DBAL 4 ([doctrine/dbal#6607](https://github.com/doctrine/dbal/pull/6607)) and goes away in
  DBAL 5, and as of DBAL 4.4 the `Doctrine\DBAL\Platforms\Keywords\*` classes are deprecated too -
  `KeywordList::__construct()` raises the same deprecation - so resolving the platform's keyword list
  by hand is not a way out. The deprecation was also raised with `triggerIfCalledFromOutside()` on
  every `DatatableQueryBuilder` construction, i.e. once per datatable request.

  If you reference the root alias in your own DQL (`->andWhere('team.project = :project')`), check your entity short names against the two lists:

    * **Lose their `_` prefix:** `user` (PostgreSQL, SQL Server, Oracle, DB2) and, on MySQL/MariaDB,
      `table`, `key`, `option`, `match`, `right`, `schema`, `usage`, `row`, `system`, `function`, `log`.
    * **Gain a `_` prefix:** `count`, `max`, `min`, `sum`, `avg`, `new`, `end`, `member`, `hidden`,
      `partial`, `any`, `index`, `named`, and the other DQL keywords. These could never have worked before - the DQL parser rejected the alias - so no working code depends on them.

* **`doctrine/orm` now requires `^3.2`** (was `^3.0`). The generated query uses the DQL `PARTIAL`
  keyword, which was removed in ORM 3.0 and only reintroduced in ORM 3.2. On ORM 3.0/3.1 every datatable threw, so the old constraint advertised versions the bundle cannot run on.

* **`AbstractColumn::addTypeOfAssociation()`/`setTypeOfAssociation()` reject unknown values.** Use the new `AbstractColumn::TO_ONE_ASSOCIATION` / `TO_MANY_ASSOCIATION` constants. Passing the ORM 2
  `ClassMetadataInfo::ONE_TO_MANY`/`MANY_TO_MANY` integers now throws instead of silently making
  `isToManyAssociation()` return `false` (which rendered a to-many column as a single field).

* **The inline-edit endpoint no longer honours `__get`/`__set`.** 1.8.0 changed
  `DatatableController::editAction()` from `enableMagicCall()` to `enableMagicMethods()`, which was not an equivalent rename - `enableMagicCall()` enables only `__call`, `enableMagicMethods()` enables `__get`, `__set` **and** `__call`. The property path on that endpoint comes straight from the request, so on an entity with a permissive `__set` the endpoint could write properties that were previously unreachable. Restored to `enableMagicCall()`, which is not deprecated in Symfony 6.4/7/8 - so the swap gained nothing in the first place.

## Fixes

* Remove the DBAL deprecation raised on every datatable request (`AbstractPlatform::getReservedKeywordsList()`, deprecated in DBAL 4, removed in DBAL 5).
* Finish the null-array-key fix in `ColumnBuilder::removeColumn()`, which still triggered two PHP 8.5 deprecations when `remove()` was called with an `ActionColumn`, `MultiselectColumn` or dql-less
  `VirtualColumn` present.
* `AbstractFilter::getExpression()` no longer passes a null type of field to `preg_match()`
  (deprecated since PHP 8.1) when searching a column where `isSelectColumn()` is `false`.
* Declare `doctrine/dbal` and `doctrine/persistence` explicitly; both were used directly but only pulled in transitively via `doctrine/orm`. The bundle uses `Doctrine\DBAL\Types\Type` (`AbstractColumn`), `Doctrine\DBAL\Types\Types` (`DatatableController`) and `Doctrine\Persistence\Mapping\{ClassMetadata,MappingException}`. The persistence floor is `^3.3.1|^4.0` to match ORM 3's own requirement - a lower floor such as `^3.1` would advertise versions ORM 3 does not accept anyway.

## Other

* Correct the routing snippet in the README to `type: attribute`. It said `type: annotation`, which Symfony 6.4 deprecated and Symfony 7 removed, so the documented setup could not work on two of the three supported Symfony major versions. The controller has used `#[Route]` attributes since 1.8.0.
* Document in the README that the built query can only be array-hydrated. Because it combines
  `PARTIAL` with `Query::HYDRATE_ARRAY`, consumers taking `getBuiltQb()` and running their own query cannot object-hydrate it on ORM 3.
* `DatatableQueryBuilder::buildQuery()` is a no-op and deprecated; it will be removed in 3.0.
* Raise `phpunit/phpunit` to `^10.5|^11.5` and drop `phpspec/prophecy` (dev only). PHPUnit 10 removed
  `TestCase::prophesize()`, so the one test that used prophecy has been rewritten with PHPUnit's own mocks rather than pulling in the `phpspec/prophecy-phpunit` bridge. `^10.5` is the floor rather than `^11.5` because PHPUnit 10.5 is the last line that still runs on PHP 8.1, which this bundle supports; `phpunit.xml.dist` was migrated off the schema both 9.x and 11.x warned about.
* Add tests for the alias check, `ColumnBuilder::remove()`, the nullable type of field, and
  `isToManyAssociation()` across all six concrete ORM 3 association mappings.

# Version 1.8.1

* Fix deprecation warnings (self-referencing string and null as array key)

# Version 1.8.0

* support orm 3 instead of 2, add support for symfony 8 and remove support for older packages

Note, added retroactively: this release contained breaking changes that were not documented at the time, which is why the next release is 2.0.0 rather than 1.9.0.

* Raised the floors for PHP (7.2 -> 8.1), Doctrine ORM (2.5 -> 3.0), Symfony (4.4/5.4 -> 6.4) and Twig (2 -> 3), and dropped `friendsofsymfony/jsrouting-bundle` 2.
* Removed four public methods from `DatatableQueryBuilder` without a deprecation period:
  `useQueryCache()`, `useCountQueryCache()`, `useResultCache()` and `useCountResultCache()`. ORM 3 removed the underlying query cache API. Callers get a fatal `Call to undefined method`.
* Changed the values stored by `AbstractColumn::addTypeOfAssociation()` from the
  `ClassMetadataInfo::*` integers to the strings `'toMany'`/`'toOne'`, which silently changed the result of `isToManyAssociation()` for anyone calling it directly. See 2.0.0 for the constants.

# Version 1.7.0

* Add option to be able to exclude a column from global search (while available for "normal" search)

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

* Fix issue in different php versions; count (null) does not work anymore in higher versions, so adding an extra check to be able to deal with this

# Version 1.4

* Fix XSS issue for columns which are not editable
    * If you want to render plain, unescaped, values; add the `escape` parameter to the column, and set this to false

# Version 1.3

## Fork from https://github.com/stwe/DatatablesBundle

* Dropped support for PHP 7.1
* Dropped support for Symfony 3.4 and <=4.3
* Support for PHP 8
* Support for Symfony 6
