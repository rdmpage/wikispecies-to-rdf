# JSON-LD examples

Real world JSON-LD examples we can use for inspiration.

## ORCID

```curl -L -H 'Accept: application/ld+json' https://orcid.org/0000-0002-8104-7761```

Note the use of `@reverse` to associate person with publications.

## Zenodo

```curl -H 'Accept: application/ld+json' https://zenodo.org/api/records/3538376```