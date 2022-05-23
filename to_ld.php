<?php

// Convert Wikispecies XML to RDF

error_reporting(E_ALL);

require_once('vendor/autoload.php');
require_once (dirname(__FILE__) . '/parse-xml.php');
require_once (dirname(__FILE__) . '/reference_parser.php');
require_once (dirname(__FILE__) . '/taxon_name_parser.php');

use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

$cuid = new EndyJasmi\Cuid;


//----------------------------------------------------------------------------------------
// Create a uniquely labelled node to use instead of a b-node (Skolemisation)
function create_skolemised_node($graph, $type = "")
{
	global $cuid;
	$sknode = null;

	$node_id = $cuid->cuid();
	
	$graph->getUri() . '#' . $node_id;
	
	if ($type != "")
	{
		$sknode = $graph->resource($uri, $type);
	}
	else
	{
		$sknode = $graph->resource($uri);
	}	
	return $sknode;
}

//----------------------------------------------------------------------------------------
// Create a uniquely labelled b node
function create_bnode($graph, $type = "")
{
	global $cuid;
	$bnode = null;

	$node_id = $cuid->cuid();
	
	$uri = '_:' . $node_id; // b-node
	
	// echo $uri . "\n";
	
	if ($type != "")
	{
		$bnode = $graph->resource($uri, $type);
	}
	else
	{
		$bnode = $graph->resource($uri);
	}	
	return $bnode;
}

//----------------------------------------------------------------------------------------
// Make a URI play nice with triple store
function nice_uri($uri)
{
	$uri = str_replace('[', urlencode('['), $uri);
	$uri = str_replace(']', urlencode(']'), $uri);
	$uri = str_replace('<', urlencode('<'), $uri);
	$uri = str_replace('>', urlencode('>'), $uri);

	return $uri;
}



//----------------------------------------------------------------------------------------
// From easyrdf/lib/parser/ntriples
function unescapeString($str)
    {
        if (strpos($str, '\\') === false) {
            return $str;
        }

        $mappings = array(
            't' => chr(0x09),
            'b' => chr(0x08),
            'n' => chr(0x0A),
            'r' => chr(0x0D),
            'f' => chr(0x0C),
            '\"' => chr(0x22),
            '\'' => chr(0x27)
        );
        foreach ($mappings as $in => $out) {
            $str = preg_replace('/\x5c([' . $in . '])/', $out, $str);
        }

        if (stripos($str, '\u') === false) {
            return $str;
        }

        while (preg_match('/\\\(U)([0-9A-F]{8})/', $str, $matches) ||
               preg_match('/\\\(u)([0-9A-F]{4})/', $str, $matches)) {
            $no = hexdec($matches[2]);
            if ($no < 128) {                // 0x80
                $char = chr($no);
            } elseif ($no < 2048) {         // 0x800
                $char = chr(($no >> 6) + 192) .
                        chr(($no & 63) + 128);
            } elseif ($no < 65536) {        // 0x10000
                $char = chr(($no >> 12) + 224) .
                        chr((($no >> 6) & 63) + 128) .
                        chr(($no & 63) + 128);
            } elseif ($no < 2097152) {      // 0x200000
                $char = chr(($no >> 18) + 240) .
                        chr((($no >> 12) & 63) + 128) .
                        chr((($no >> 6) & 63) + 128) .
                        chr(($no & 63) + 128);
            } else {
                # FIXME: throw an exception instead?
                $char = '';
            }
            $str = str_replace('\\' . $matches[1] . $matches[2], $char, $str);
        }
        return $str;
    }


//----------------------------------------------------------------------------------------
function filesafe_name($name)
{
	$name = urldecode($name);
	 
	$name = str_replace(array_merge(
        array_map('chr', range(0, 31)),
        array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
    ), '', $name);
    
    $name = str_replace(' ', '_', $name);
    $name = str_replace('_%26_', '_&_', $name);
    
    return $name;

}

//----------------------------------------------------------------------------------------
function urlsafe_name($name)
{    
    $name = str_replace(' ', '_', $name);
    $name = str_replace('_%26_', '_&_', $name);
    
    return $name;

}

//----------------------------------------------------------------------------------------
// Check whether link is valid and not full of junk
function wikispecies_link_ok($wikispecies)
{
	$ok = true;
	
	if (preg_match('/[\[|\]|\|]/', $wikispecies))
	{
		$ok = false;
	}
	
	return $ok;
}


//----------------------------------------------------------------------------------------
function csl_to_rdf($csl, $graph, $work)
{
	if (isset($csl->unstructured))
	{
		$work->add('schema:description', $csl->unstructured);
	}

	if (isset($csl->title))
	{
		$work->add('schema:name', strip_tags($csl->title));
	}

	// simple literals ---------------------------------------------------
	if (isset($csl->volume))
	{
		$work->add('schema:volumeNumber', $csl->volume);
	}
	if (isset($csl->issue))
	{
		$work->add('schema:issueNumber', $csl->issue);
	}
	if (isset($csl->page))
	{
		$work->add('schema:pagination', $csl->page);
	}

	// date --------------------------------------------------------------
	if (isset($csl->issued))
	{
		$date = '';
		$d = $csl->issued->{'date-parts'}[0];

		// sanity check
		if (is_numeric($d[0]))
		{
			if ( count($d) > 0 ) $year = $d[0] ;
			if ( count($d) > 1 ) $month = preg_replace ( '/^0+(..)$/' , '$1' , '00'.$d[1] ) ;
			if ( count($d) > 2 ) $day = preg_replace ( '/^0+(..)$/' , '$1' , '00'.$d[2] ) ;
			if ( isset($month) and isset($day) ) $date = "$year-$month-$day";
			else if ( isset($month) ) $date = "$year-$month-00";
			else if ( isset($year) ) $date = "$year-00-00";

			if (0)
			{
				// proper RDF
				$work->add('schema:datePublished', new \EasyRdf\Literal\Date($date));
			}
			else
			{
				// simple literal
				$work->add('schema:datePublished', $date);					
			}
		}				
	}		

	// authors (how do we handle order?) ---------------------------------
	if (isset($csl->author))
	{
		if (0)
		{
			foreach ($csl->author as $creator)
			{
				$author = create_bnode($graph, 'schema:Person');

				if (isset($creator->WIKISPECIES) && wikispecies_link_ok($creator->WIKISPECIES))
				{
					$author->addResource('schema:mainEntityOfPage', 'https://species.wikimedia.org/wiki/' . $creator->WIKISPECIES);
				}

				if (isset($creator->literal))
				{
					$author->add('schema:name', $creator->literal);
				}

				$work->add('schema:author', $author);			
			}	
		}

		if (1)
		{
			$authors_in_order = array();
		
			foreach ($csl->author as $creator)
			{
				$author = create_bnode($graph, 'schema:Person');
				
				if (isset($creator->WIKISPECIES) && wikispecies_link_ok($creator->WIKISPECIES))
				{
					$author->addResource('schema:mainEntityOfPage', 'https://species.wikimedia.org/wiki/' . $creator->WIKISPECIES);
				}

				if (isset($creator->literal))
				{
					$author->add('schema:name', $creator->literal);
				}

				$authors_in_order[] = $author;			
			}	
		
			$num_authors = count($authors_in_order);
			
			if ($num_authors > 0)
			{
		
				$list = array();
		
				for ($k = 0; $k < $num_authors; $k++)
				{
					$list[$k] = create_bnode($graph, "");
			
					if ($k == 0)
					{
						$work->add('schema:author', $list[$k]);
					}
					else
					{
						$list[$k - 1]->add('rdf:rest', $list[$k]);
					}	
					$list[$k]->add('rdf:first', $authors_in_order[$k]);							
				}
				$list[$num_authors - 1]->addResource('rdf:rest', 'rdf:nil');
			}
		
		}
	
		
	}

	// container
	if (isset($csl->{'container-title'}))
	{
		$container = create_bnode($graph, "schema:Periodical");

		$container->add('schema:name', $csl->{'container-title'});

		if (isset($csl->ISSN))
		{
			$container->add('schema:issn', $csl->ISSN[0]);
			$container->addResource('schema:mainEntityOfPage', 'https://species.wikimedia.org/wiki/ISSN_' . $csl->ISSN[0]);

		}					
		$work->add('schema:isPartOf', $container);
	}

	// identifiers sameAs/seeAlso

	// BHL is seeAlso
	if (isset($csl->BHL))
	{
		$work->addResource('schema:seeAlso', 'https://www.biodiversitylibrary.org/page/' . $csl->BHL);
	}

	// JSTOR is sameAs
	if (isset($csl->JSTOR))
	{
		$work->addResource('schema:sameAs', 'https://www.jstor.org/stable/' . $csl->JSTOR);
	}

	// Identifiers as property-value pairs so that we can query by identifier value
	if (isset($csl->DOI))
	{
		// ORCID-style
		$identifier = create_bnode($graph, "schema:PropertyValue");		
		$identifier->add('schema:propertyID', 'doi');
		$identifier->add('schema:value', strtolower($csl->DOI));
		$work->add('schema:identifier', $identifier);
	}

	// URL(s)?
	if (isset($csl->URL))
	{
		$urls = array();
		if (!is_array($csl->URL))
		{
			$urls = array($csl->URL);
		}
		else
		{
			$urls = $csl->URL;
		}

		foreach ($urls as $url)
		{
			$work->addResource('schema:url', $url);
		}
	}

	// PDF?
	if (isset($csl->link))
	{
		foreach ($csl->link as $link)
		{
			if (isset($link->{'content-type'}) && ($link->{'content-type'} == 'application/pdf'))
			{
				$encoding = create_bnode($graph, "schema:MediaObject");
				$encoding->add('schema:fileFormat', $link->{'content-type'});
				$encoding->add('schema:contentUrl', $link->URL);
				
				if (isset($link->thumbnailUrl))
				{
					$encoding->add('schema:thumbnailUrl', $link->thumbnailUrl);
				}
				
				$work->add('schema:encoding', $encoding);
											
			}
		}
	}
}

//----------------------------------------------------------------------------------------
// convert to RDF

function convert_to_rdf($obj)
{

	// Construct a graph of the results	
	// Note that we use the URL of the object as the name for the graph. We don't use this 
	// as we are outputting triples, but it enables us to generate fake bnode URIs.	
	$graph = new \EasyRdf\Graph($obj->url);

	$page = $graph->resource($obj->url, 'schema:WebPage');
	$page->addResource('schema:url', $obj->url);
	$page->add('schema:name', $obj->title);

	// stuff about this entity
	
	$taxon = null;
	
	if ($obj->type == "taxon")
	{
		$taxon = create_bnode($graph, "schema:Taxon");

		$taxon->addResource('schema:additionalType', 'http://rs.tdwg.org/ontology/voc/TaxonConcept#TaxonConcept');
		$taxon->addResource('schema:additionalType', 'http://rs.tdwg.org/dwc/terms/Taxon');

		$page->addResource('schema:mainEntity', $taxon);
		$taxon->addResource('schema:mainEntityOfPage', $obj->url);
		
		if (isset($obj->thumbnailUrl))
		{
			$taxon->addResource('schema:thumbnailUrl', $obj->thumbnailUrl);
		}
		
		if (isset($obj->scientificName))
		{
			$scientificName = create_bnode($graph, "schema:TaxonName");
			$taxon->addResource('schema:scientificName', $scientificName);
			
			$scientificName->addResource('schema:additionalType', 'http://rs.tdwg.org/ontology/voc/TaxonName#TaxonName');
					
			if (isset($obj->scientificName->canonical))
			{			
				$taxon->add('schema:name', $obj->scientificName->canonical);
				$scientificName->add('schema:name', $obj->scientificName->canonical);
			}

		}	
		
		// the taxonomic hierarchy is represented by including a taxon navigation templates
		// so link to the template 
		if (isset($obj->parent))
		{
			$page->addResource('schema:hasPart', 'https://species.wikimedia.org/wiki/' . $obj->parent);
		}
	
	}
	
	/*
	// a template page that is included in a wiki page about a taxon and is used to
	// represent the taxonomic hierarchy
	if ($obj->type == "navigation" && isset($obj->navigation))
	{
		// guess the link to page which includes this pages		
		$guess_url = 'https://species.wikimedia.org/wiki/' . $obj->navigation;
		$page->addResource('schema:parentItem', $guess_url );
	
	}
	*/
	
	$person = null;
	
	if ($obj->type == "person")
	{
		$person = create_bnode($graph, "schema:Person");
		$person->add('schema:name', $obj->title);

		$page->addResource('schema:mainEntity', $person);
		$person->addResource('schema:mainEntityOfPage', $obj->url);
	}


	// A Wikispecies page can have 1,n references. If the page is a reference template then
	// the page is SOLELY about that reference.
	if (isset($obj->references) && count($obj->references) > 0)
	{
		foreach ($obj->references as $reference)
		{
			$id = '';
			$work = null;
		
			if (isset($reference->wiki_name))
			{
				// this is a tranclusion of a web page, so add a link to the page...			
			
				$wiki_link = 'https://species.wikimedia.org/wiki/' . $reference->wiki_name;
				$page->addResource('schema:hasPart', $wiki_link);	
			
				/*
				// this is a tranclusion of a web page, so add a link to the page...			
				$transclusion = create_bnode($graph, 'schema:WebPage');			
				$wiki_link = 'https://species.wikimedia.org/wiki/' . $reference->wiki_name;
				
				// link page to its content so we can connect to the reference itself
				$transclusion->addResource('schema:mainEntityOfPage', $wiki_link );
			
				// we are citing this reference
				$page->addResource('schema:citation', $transclusion);	
				*/
				
			}
			else
			{
				// this is an actual reference
		
				// Do we have an external identifier that we can use as the URI?
				if (isset($reference->csl))
				{
					if ($id == '')
					{
						if (isset($reference->csl->DOI))
						{
							$id = 'https://doi.org/' .  strtolower($reference->csl->DOI);
							$id = nice_uri($id);
						}
					}

					if ($id == '')
					{
						if (isset($reference->csl->HANDLE))
						{
							$id = 'https://hdl.handle.net/' .  $reference->csl->HANDLE;
						}
					}

				}	
			
				// If we have a URI then create a node, otherwise it's a bNode
				if ($id == '')
				{
					$work = create_bnode($graph, 'schema:CreativeWork');
				}
				else
				{
					$work = $graph->resource($id, 'schema:CreativeWork');	
				}
			
				
				// is this page JUST about this reference? Yes? Then use mainEntity
				// to link reference to this wiki page
				if ($obj->type == 'reference')
				{
					$page->addResource('schema:mainEntity', $work);
					$work->addResource('schema:mainEntityOfPage', $obj->url);
				}	
				else
				{
					// we are citing this reference on a page that is, say, a taxon page
					//$page->addResource('schema:citation', $work);	
					//$page->addResource('schema:hasPart', $work);	
					
					if ($taxon)
					{
						$work->addResource('schema:about', $taxon);
					}
								
				}
				
				
				
									
			
				// text string which means this is an actual reference
				if (isset($reference->string))
				{
					// can we clean the string...?
					// $work->add('schema:description', $reference->string);
			
					// any extra info we can extract?
			
					if (preg_match('/\{\{\s*access\s*\|\s*open\s*\}\}/i', $reference->string))
					{
						$work->add('schema:isAccessibleForFree', 'true');
					}
					if (preg_match('/\{\s*\{access\s*\|\s*closed\s*\}\}/i', $reference->string))
					{
						$work->add('schema:isAccessibleForFree', 'false');
					}
						
				}	
				
				
	
				// to RDF ----------------------------------------------------------------
				if (isset($reference->csl))
				{
					csl_to_rdf($reference->csl, $graph, $work);
				}
			
			}
	
	

		}
	}

	return $graph;
}

//----------------------------------------------------------------------------------------

/*

Serialise into triples and JSON-LD. Bit of a headache because:

1. EasyRDF outputs triples with unicode characters escaped
2. EasyRDF JSON-LD does not output @list as simple ordered JSON arrays

So we manually decode unicode characters in triples. if we don't this gets parsed on
to the JSON-LD output. We also manyalyl output JSON-LD by directly calling the same library
as EasyRDF, but for some reason this works!?

*/

function output_rdf($graph, $wiki_obj)
{

	// Triples 
	$format = \EasyRdf\Format::getFormat('ntriples');

	$serialiserClass  = $format->getSerialiserClass();
	$serialiser = new $serialiserClass();

	$triples = $serialiser->serialise($graph, 'ntriples');

	// Remove JSON-style encoding
	$told = explode("\n", $triples);
	$tnew = array();

	foreach ($told as $s)
	{
		$tnew[] = unescapeString($s);
	}

	$triples = join("\n", $tnew);
	
	echo $triples . "\n";

	$ld_filename = $wiki_obj->base_filename . '.nt';

	//file_put_contents($ld_filename, $triples);

	
	// JSON-LD
	if (0)
	{
		$context = new stdclass;
		$context->{'@vocab'} = 'http://schema.org/';
		$context->rdf =  "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
		$context->dwc =  "http://rs.tdwg.org/dwc/terms/";
		$context->tc =  "http://rs.tdwg.org/ontology/voc/TaxonConcept#";
		$context->tn =  "http://rs.tdwg.org/ontology/voc/TaxonName#";

		// author is ordered list
		$author = new stdclass;
		$author->{'@id'} = "author";
		$author->{'@container'} = "@list";
		$context->author = $author;


		$additionalType = new stdclass;
		$additionalType->{'@id'} = "additionalType";
		$additionalType->{'@type'} = "@id";
		$additionalType->{'@container'} = "@set";
	
		$context->{'additionalType'} = $additionalType;
	
		// links as text?
	

		// Frame document
		$frame = (object)array(
			'@context' => $context,
			'@type' => 'http://schema.org/WebPage'
		);	
	
		// Use same libary as EasyRDF but access directly to output ordered list of authors
		$nquads = new NQuads();
		// And parse them again to a JSON-LD document
		$quads = $nquads->parse($triples);		
		$doc = JsonLD::fromRdf($quads);
	
		$obj = JsonLD::frame($doc, $frame);

		echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$ld_filename = $wiki_obj->base_filename . '.json';

		file_put_contents($ld_filename, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}	

}

// test
if (1)
{

	$pages=array(
	'Template:Naydenov,_Yakovlev_&_Penco,_2021',
	'TemplateYakovlev,_Naydenov_&_Penco,_2022',
	'Template:Yakovlev,_Penco_&_Naydenov,_2020'
	);
	
$pages=array(
'Rhinophoridae',
'Template:Rhinophoridae',
'Template:Robineau-Desvoidy,_1863',
'Template:Cerretti_et_al.,_2014a',
'Template:Cerretti_et_al.,_2020',
'Template:Kato_&_Tachi,_2016',
'Template:Nihei,_2016',
'Template:Nihei_et_al.,_2016',
'Template:Wood,_Nihei_&_Araujo,_2018',
);	

/*
$pages=array(
'Rhinophoridae',
);
*/

$pages=array(
'Silvio_Shigueo_Nihei',
'Template:Nihei_&_Toma,_2010',
'Template:Calhau,_Lamas_&_Nihei,_2015',
'Template:Campos_et_al.,_2015',
'Template:Nihei,_2015',
'Template:Nihei,_2015b',
'Template:Calhau,_Lamas_&_Nihei,_2016',
'Template:Dios_&_Nihei,_2016',
'Template:Gillung_&_Nihei,_2016',
'Template:Nihei,_2016',
'Template:Nihei,_2016a',
'Template:Nihei_et_al.,_2016',
'Template:Pamplona_et_al.,_2016',
'Template:Pinto_et_al.,_2016',
'Template:Wolff,_Grisales_&_Nihei,_2016',
'Template:Wolff,_Nihei_&_de_Carvalho,_2016',
'Template:Wolff,_Nihei_&_de_Carvalho,_2016a',
'Template:De_Campos,_Souza-Dias_&_Nihei,_2017',
'Template:Wood,_Nihei_&_Araujo,_2018',
);


//$pages=array('Silvio_Shigueo_Nihei');

	$files = array();
	foreach ($pages as $p)
	{
		$files[] = filesafe_name($p) . '.xml';
	}

	$cache_dir = dirname(__FILE__) . '/cache';

	foreach ($files as $filename)
	{
		$xml = file_get_contents($cache_dir . '/' . $filename);

		//echo $xml;
		//exit();

		if ($xml == '')
		{
			echo "*** Error ***\n";
			echo "XML empty\n";
			exit();
		}

		$obj = xml_to_object($xml);

		if ($obj)
		{
			//print_r($obj);

			
			$graph = convert_to_rdf($obj);

			$obj->base_filename = filesafe_name($obj->title);

			output_rdf($graph, $obj);
			
		}
	}
}

?>
