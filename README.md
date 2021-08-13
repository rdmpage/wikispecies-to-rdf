# Wikispecies to RDF

Parse Wikispecies pages and convert to RDF using [schema.org](https://schema.org) vocabulary. Goal is to have Wikispecies in a structured form that can be used to do things such as help map Wikispecies to Wikidata, extract author - publication links for use in Wikidata, and as a source of bibliographic metadata to locate articles in BioStor.


## Parsing Wikispecies references

Use local version of (acoustic-bandana)[https://acoustic-bandana.glitch.me]. Download source from Glitch, then:
- cd to app directory
- `npm install`
- `npm start server.js`

Service will then be available on http://localhost:3000 The service takes a Wikispecies reference string and returns CSL-JSON.



