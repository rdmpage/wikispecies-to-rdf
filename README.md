# Wikispecies to RDF

Parse Wikispecies pages and convert to RDF using [schema.org](https://schema.org) vocabulary. Goal is to have Wikispecies in a structured form that can be used to do things such as help map Wikispecies to Wikidata, extract author - publication links for use in Wikidata, and as a source of bibliographic metadata to locate articles in BioStor.

## Approach 

Treat each page in Wikispecies as a `schema:WebPage`. Each page corresponds to a particular entity which we can link to via `schema:mainEntity` or its inverse `schema:mainEntityOfPage`. This enables us to model the wiki pages and not conflate identifiers for the page (the URL of the page) with identifiers for the entities (such as DOIs). Note that the Wikispecies XML doesn’t have any link to Wikidata, that is added by Mediawiki and is based on Wikidata having a statement linking a Wikidata page to Wikispecies.

If the wiki page is for an individual reference (i.e., is a template) then we add the reference as the `schema:mainEntity` of the wiki page. If reference has a DOI or other recognised persistent identifier we use that as the URI for the reference, otherwise it is treated as a b-node.

For people we typically have no identifiers available on the Wikispecies page, so links between people and publications are made using `schema:mainEntityOfPage` on the RDF for a reference.



## Parsing Wikispecies references

Use local version of (acoustic-bandana)[https://acoustic-bandana.glitch.me]. Download source from Glitch, then:
- cd to app directory
- `npm install`
- `npm start server.js`

Service will then be available on http://localhost:3000 The service takes a Wikispecies reference string and returns CSL-JSON.



