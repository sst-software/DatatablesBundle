# SgDatatablesBundle - SST Fork

## This repository is a fork of https://github.com/stwe/DatatablesBundle.

## Use this repository to replace the original sg/datatablesbundle with this one, to fix a xss-issue.

### Installation

- Run `composer require sst/datatablesbundle`
- Copy the following into `config/routes/sg_datatables_bundle.yaml`

```yaml
sg_datatables_bundle:
    resource: "@SgDatatablesBundle/Controller/"
    type: annotation
    prefix: /sg
```
