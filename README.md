# SgDatatablesBundle - SST Fork

## This repository is a fork of https://github.com/stwe/DatatablesBundle.

## Use this repository to replace the original sg/datatablesbundle with this one, to fix a xss-issue.

### Requirements

- PHP 8.1 or higher
- Doctrine ORM 3.2 or higher. ORM 3.0 and 3.1 are not supported: the generated query uses
  the DQL `PARTIAL` keyword, which was removed in ORM 3.0 and only reintroduced in ORM 3.2.
- Symfony 6.4, 7 or 8

### Installation

- Run `composer require sst/datatablesbundle`
- Copy the following into `config/routes/sg_datatables_bundle.yaml`

```yaml
sg_datatables_bundle:
    resource: "@SgDatatablesBundle/Controller/"
    type: attribute
    prefix: /sg
```

The bundle's controller uses native PHP attributes (`#[Route]`), so the loader type is
`attribute`. Earlier versions of this README said `type: annotation`; that value still works on
Symfony 6.4 but triggers a deprecation there, and Symfony 7 dropped it. If you are upgrading
and see

```
Make sure there is a loader supporting the "annotation" type.
```

then this is the line to change.

### Array hydration only

Since ORM 3, `PARTIAL` is only supported for array hydration. The query built by
`DatatableQueryBuilder` therefore combines `partial <alias>.{...}` with
`Query::HYDRATE_ARRAY` and can never be object-hydrated - doing so throws a
`Doctrine\ORM\Internal\Hydration\HydrationException`.

This matters if you take the query builder out of the bundle and run the query yourself:

```php
// fine - this is what the bundle does
$query = $datatableQueryBuilder->getBuiltQb()->getQuery();
$rows = $query->getArrayResult();

// throws HydrationException
$rows = $datatableQueryBuilder->getBuiltQb()->getQuery()->getResult();
```
