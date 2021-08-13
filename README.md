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

## Triple store

I’m using [Oxigraph](https://crates.io/crates/oxigraph_server). Install this using `cargo install oxigraph_server`.

Start the server in the same directory that you want the server’s files stored. If you cd to that directory, then:

```oxigraph_server -f .```

The endpoint is http://localhost:7878

### Upload data

curl http://localhost:7878/store?default -H 'Content-Type:application/n-triples' --data-binary '@TemplateLücking_et_al.,_2017c.nt'  --progress-bar 

curl http://localhost:7878/store?default -H 'Content-Type:application/n-triples' --data-binary '@1999-3110-54-38.nt' 

### Query

curl http://localhost:7878/query -H 'Content-Type:application/sparql-query' -H 'Accept:application/sparql-results+json' --data 'SELECT * WHERE { ?s ?p ?o } LIMIT 10' 

curl http://localhost:7878/query -H 'Content-Type:application/sparql-query' -H 'application/n-triples' --data 'DESCRIBE <http://scigraph.springernature.com/pub.10.1186/1999-3110-54-38>'

### Delete
curl http://localhost:7878/update -X POST -H 'Content-Type: application/sparql-update' --data 'DELETE WHERE { ?s ?p ?o }' 

## SPARQL


Queries

PREFIX schema: <http://schema.org/>
select * where { 
  ?person schema:mainEntityOfPage <https://species.wikimedia.org/wiki/Robert_Lücking>. 
	?work schema:author ?person .
  ?work schema:name ?name .
  OPTIONAL {
  ?work schema:url ?url .
  }
  OPTIONAL {
  ?work schema:isAccessibleForFree ?free .
  }
  OPTIONAL {
  ?work schema:mainEntityOfPage ?article .
  }
}



