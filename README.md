# Wikispecies to RDF

Parse Wikispecies pages and convert to RDF using [schema.org](https://schema.org) vocabulary. Goal is to have Wikispecies in a structured form that can be used to do things such as help map Wikispecies to Wikidata, extract author - publication links for use in Wikidata, and as a source of bibliographic metadata to locate articles in BioStor.

## Approach 

Treat each page in Wikispecies as a `schema:WebPage`. Each page corresponds to a particular entity which we can link to via `schema:mainEntity` or its inverse `schema:mainEntityOfPage`. This enables us to model the wiki pages and not conflate identifiers for the page (the URL of the page) with identifiers for the entities (such as DOIs). Note that the Wikispecies XML doesn’t have any link to Wikidata, that is added by Mediawiki and is based on Wikidata having a statement linking a Wikidata page to Wikispecies.

If the wiki page is for an individual reference (i.e., is a template) then we add the reference as the `schema:mainEntity` of the wiki page. If reference has a DOI or other recognised persistent identifier we use that as the URI for the reference, otherwise it is treated as a b-node.

For people we typically have no identifiers available on the Wikispecies page, so links between people and publications are made using `schema:mainEntityOfPage` on the RDF for a reference.

## JSON-LD

https://validator.schema.org

## Parsing Wikispecies references

Use local version of (acoustic-bandana)[https://acoustic-bandana.glitch.me]. Download source from Glitch, then:
- cd to app directory
- `npm install`
- `npm start server.js`

Service will then be available on http://localhost:3000 The service takes a Wikispecies reference string and returns CSL-JSON.

## Triple store

I’m using [Oxigraph](https://crates.io/crates/oxigraph_server). Install this using `cargo install oxigraph_server`.

Start the server in the same directory that you want the server’s files stored. If you cd to that directory (e.g., `oxigraph`), then:

```
oxigraph_server -l. serve
```

The endpoint is http://localhost:7878

### Upload data

#### To default graph

```
curl 'http://localhost:7878/store?default' -H 'Content-Type:application/n-triples' --data-binary '@triples.nt'  --progress-bar
```

#### To named graph

```
curl 'http://localhost:7878/store?graph=https://species.wikimedia.org' -H 'Content-Type:application/n-triples' --data-binary '@triples.nt'  --progress-bar
```

### Query

curl http://localhost:7878/query -H 'Content-Type:application/sparql-query' -H 'Accept:application/sparql-results+json' --data 'SELECT * WHERE { ?s ?p ?o } LIMIT 10' 

curl http://localhost:7878/query -H 'Content-Type:application/sparql-query' -H 'application/n-triples' --data 'DESCRIBE <http://scigraph.springernature.com/pub.10.1186/1999-3110-54-38>'

### Delete

curl http://localhost:7878/update -X POST -H 'Content-Type: application/sparql-update' --data 'DELETE WHERE { ?s ?p ?o }' 

## SPARQL

## Lists (RDF collections)

SciGraph uses rdf:lists to ensure authors are ordered correctly in JSON-LD output. This means we need to be clever about how to query for authorship.

See http://www.snee.com/bobdc.blog/2014/04/rdf-lists-and-sparql.html and https://stackoverflow.com/questions/17523804/is-it-possible-to-get-the-position-of-an-element-in-an-rdf-collection-in-sparql/17530689#17530689

### Authors of a specific work

#### Unordered list

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
select * where { 
  ?work schema:mainEntityOfPage <https://species.wikimedia.org/wiki/Template:Nihei_et_al.,_2016>. 
  ?work schema:author/rdf:rest*|rdf:first ?list_element .
  ?list_element rdf:first ?author .
  ?author schema:name ?name .
}
```

#### Ordered list of authors

Get ordered list based on https://stackoverflow.com/questions/17523804/is-it-possible-to-get-the-position-of-an-element-in-an-rdf-collection-in-sparql/17530689#17530689

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT ?name ?author_page ?author_name (COUNT(?mid)-1 as ?position)
WHERE { 
  VALUES ?work { <https://doi.org/10.3897/zookeys.42.190> }
  
  ?work schema:author/rdf:rest* ?mid .
  ?mid rdf:rest* ?node .
  ?node rdf:first ?author .
  ?author schema:name ?name .
  OPTIONAL {
  	?author schema:mainEntityOfPage ?author_page .
	?author_page schema:name ?author_name .
  }
}
GROUP BY ?author ?name ?author_page ?author_name
ORDER BY ?position
```

#### Works by an author as a list (no author order)

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT *
#FROM <https://species.wikimedia.org>
WHERE { 
  ?webpage schema:name "Silvio Shigueo Nihei" .
   ?author schema:mainEntityOfPage ?webpage .
  ?list_element rdf:first ?author .
  ?work schema:author/rdf:rest*|rdf:first ?list_element .
  ?work schema:mainEntityOfPage ?page. 
  ?work schema:name ?title .
}
```
#### Works by an author (with order)

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT ?work ?title (COUNT(?mid)-1 as ?position)
FROM <https://species.wikimedia.org>
WHERE { 
   ?webpage schema:name "Silvio Shigueo Nihei" .
   ?author schema:mainEntityOfPage ?webpage .
   ?node rdf:first ?author .  
   ?mid rdf:rest* ?node .
  ?work schema:author/rdf:rest* ?mid .
   ?work schema:mainEntityOfPage ?page. 
  ?work schema:name ?title .
}
GROUP BY ?work ?title
```


### Works

#### Works with a DOI

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT * WHERE { 
  ?work schema:identifier ?identifier .
  ?identifier schema:propertyID "doi" .
  ?identifier schema:value ?doi .
}
```

#### Works as citation strings

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT * WHERE { 
  ?work rdf:type schema:CreativeWork .
  ?work schema:description ?description .
}
```

#### Works listed by container title

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT * WHERE { 
  ?work rdf:type schema:CreativeWork .
  ?work schema:isPartOf ?container  .
  ?container schema:name ?container_title
}
ORDER BY ?container_title
```

### Taxa

#### Publications about a taxon (name)

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
SELECT * WHERE { 
  
  # citations related to a name 
  
  ?taxon schema:name "Glischropus" .
  ?taxon rdf:type schema:Taxon .
  ?page schema:mainEntity ?taxon .
 
  # taxon pages either include citations directly as semi-structured strings,
  # or transcluded them as Templates
  {
    # Citation in text of page
    ?page schema:citation ?citation .
   
   }
  UNION
  {
    # Citation is a transcluded template
    ?page schema:citation ?transclusion .
    ?transclusion rdf:type schema:WebPage .
		?transclusion schema:mainEntityOfPage ?reference .
    ?citation schema:mainEntityOfPage ?reference .
  	   
  }
   ?citation rdf:type schema:CreativeWork .
  OPTIONAL {
    ?citation schema:name ?citation_name .
  }
  OPTIONAL {
    ?citation schema:description ?citation_description .
  }
}
```

#### What taxa is a work about?

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
select * where { 
  
  # What is a citation "about"?
  
  VALUES ?citation { <https://doi.org/10.1590/s0101-81752007000200032> }
  ?citation rdf:type schema:CreativeWork .
   {
    # Citation in text of page
    ?page schema:citation ?citation .
   }
  UNION
  {
    # Citation is a transcluded template
   	?citation schema:mainEntityOfPage ?transclusion .    
    ?page schema:citation ?transclusion .
    ?transclusion rdf:type schema:WebPage .
  }
 
  OPTIONAL {
    ?citation schema:name ?citation_name .
  }
  OPTIONAL {
    ?citation schema:description ?citation_description .
  }
  
 
  ?page schema:mainEntity ?taxon . 
  ?taxon rdf:type schema:Taxon .
  ?taxon schema:name ?taxon_name .

}
```


#### Try and get taxonomic hierarchy

The hierarchy is implemented using templates, and doesn’t work for species as the genus and the species will all share the same templates.

```
PREFIX schema: <http://schema.org/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
select * 
where { 
 ?page schema:hasPart ?template .
 ?page schema:mainEntity ?child_entity .
 ?child_entity schema:name ?child_name .
 ?template schema:parentItem ?parent .
 ?parent schema:mainEntity ?parent_entity .
 ?parent_entity schema:name ?parent_name .
}
```


#### Old queries

```
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
```





