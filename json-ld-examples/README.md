# JSON-LD examples

Real world JSON-LD examples we can use for inspiration.

## Catalogue of Life

Based on TaxonName DRAFT Profile:
https://bioschemas.org/profiles/TaxonName/0.1-DRAFT/
https://bioschemas.org/profiles/Taxon/0.6-RELEASE/

Embedded in HTML using `<script type="application/ld+json"></script>` tags.

## ORCID

```curl -L -H 'Accept: application/ld+json' https://orcid.org/0000-0002-8104-7761```

Note the use of `@reverse` to associate person with publications.

## ResearchGate

At one point ResearchGate embedded schema.org in their web pages, but they seemed to have stopped doing this?

## SciGraph

```curl -L http://scigraph.springernature.com/pub.10.1007/s00606-016-1316-4.json```

## Zenodo

```curl -H 'Accept: application/ld+json' https://zenodo.org/api/records/3538376```