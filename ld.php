<?php

error_reporting(E_ALL);



require_once('vendor/autoload.php');
require_once (dirname(__FILE__) . '/parse.php');
require_once (dirname(__FILE__) . '/reference_parser.php');
require_once (dirname(__FILE__) . '/taxon_name_parser.php');

use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

$use_bnodes = true;
$use_bnodes = false;



//----------------------------------------------------------------------------------------
// Eventually it becomes clear we can't use b-nodes without causing triples tores to replicate
// lots of triples, so create arbitrary URIs using the graph URI as the base.
function create_bnode($graph, $type = "")
{
	global $use_bnodes;
	
	$bnode = null;
	
	if ($use_bnodes)
	{
		if ($type != "")
		{
			$bnode = $graph->newBNode($type);
		}
		else
		{
			$bnode = $graph->newBNode();
		}		
	}
	else
	{
		$bytes = random_bytes(5);
		$node_id = bin2hex($bytes);
		
		// if we use fragment identifiers the rdf:list trick for JSON-LD fails :(
		if (1)
		{
			$uri = '_:' . $node_id;
		}
		else
		{
			$uri = $graph->getUri() . '#' . $node_id;
		}

		if ($type != "")
		{
			$bnode = $graph->resource($uri, $type);
		}
		else
		{
			$bnode = $graph->resource($uri);
		}	
	
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
	
	// a template page that is included in a wiki page about a taxon and is used to
	// represent the taxonomic hierarchy
	if ($obj->type == "navigation" && isset($obj->navigation))
	{
		// guess the link to page which includes this pages		
		$guess_url = 'https://species.wikimedia.org/wiki/' . $obj->navigation;
		$page->addResource('schema:parentItem', $guess_url );
	
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
				$transclusion = create_bnode($graph, 'schema:WebPage');			
				$wiki_link = 'https://species.wikimedia.org/wiki/' . $reference->wiki_name;
				
				// link page to its content so we can connect to the reference itself
				$transclusion->addResource('schema:mainEntityOfPage', $wiki_link );
			
				// we are citing this reference
				$page->addResource('schema:citation', $transclusion);	
				
				
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
					// we are citing this reference
					$page->addResource('schema:citation', $work);			
				}					
			
				// text string which means this is an actual reference
				if (isset($reference->string))
				{
					// can we clean the string...?
					// $work->add('schema:description', $reference->string);
			
					// any extra info we can extract?
			
					if (preg_match('/\{\{\s*access\s*\|\s*open\s*\}\}/i', $reference->string))
					{
						$work->add('schema:isAccessibleForFree', true);
					}
					if (preg_match('/\{\s*\{access\s*\|\s*closed\s*\}\}/i', $reference->string))
					{
						$work->add('schema:isAccessibleForFree', false);
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

	file_put_contents($ld_filename, $triples);


	// JSON-LD

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
	

	// Frame document
	$frame = (object)array(
		'@context' => $context,
		'@type' => 'http://schema.org/WebPage'
	);	


	if (0)
	{
		// EasyRDF JSON-LD serialisation doesn't handle ordered lists :()
		$options = array();
		$options['compact'] = true;
		$options['frame']= $frame;	

		$format = \EasyRdf\Format::getFormat('jsonld');
		$data = $graph->serialise($format, $options);
		$obj = json_decode( $data);
	}


	if (1)
	{
		// Use same libary as EasyRDF but access directly to output ordered list of authors
		$nquads = new NQuads();
		// And parse them again to a JSON-LD document
		$quads = $nquads->parse($triples);		
		$doc = JsonLD::fromRdf($quads);
		
		$obj = JsonLD::frame($doc, $frame);
	}

	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	$ld_filename = $wiki_obj->base_filename . '.json';

	file_put_contents($ld_filename, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

}


$page = 'Glyptosceloides';
$page = 'TemplateAskevold_&_Flowers,_1994';
//$page = 'TemplateTarmann_&_Cock,_2019';
$page = 'Template:Urtubey et al., 2016';
//$page = 'TemplateDe_Vos,_2019a';
//$page = 'Template:O\'Brien, Askevold & Morimoto, 1994';
//$page = 'Julien Achard';
//$page = 'Redonographa chilensis';
//$page = 'Template:Lücking_et_al.,_2013a';
//$page = 'Robert Lücking';
//$page = 'Template:Lücking,_Parnmen_&_Lumbsch,_2012';
//$page = 'Template:Lücking_et_al.,_2017c';

//$page = 'Template:Askevold_%26_Flowers,_1994';

//$page = 'Marcos_A._Raposo';
//$page = 'Template:Stopiglia_et_al.,_2012';
$page = 'Pseudopipra';
//$page = 'Pseudopipra_pipra';
//$page = 'Pelargonium_carnosum';
//$page = 'Template:Germishuizen_%26_Meyer,_2003';
//$page = 'Template:Kirwan_et_al.,_2016';

$page = 'Template%3ATyler_%26_Menzies%2C_2013';
$page = 'Pteropodidae';
//$page = 'Template:Almeida_et_al.,_2011';
//$page = 'Template%3AZhou_et_al.%2C_2009b';
//$page = 'Rhinolophus_xinanzhongguoensis';
//$page = 'Pseudopipra';
//$page = 'Template%3ARuedi%2C_Eger%2C_Lim_%26_Csorba%2C_2018';
//$page = 'Judith_L._Eger';
$page = 'Darevskia';
$page = 'Template:Kosushkin_%26_Grechko,_2013';
$page = 'Ichnotropis';
$page = 'Template:Opinion_1422';
$page = 'Template:Edwards_et_al.,_2013';
//$page = 'Template:Branch_%26_Broadley,_1985';
$page = 'Hipposideros khaokhouayensis';

$page = 'Template%3ACsorba_et_al.%2C_2011';
//$page = 'Murina walstoni';
//$page = 'Template:Murina';
$page = 'Rob_de_Vos';
$page = 'Template:De_Vos_%26_van_Haren,_2014';
//$page = 'Curtis_John_Callaghan';
//$page = 'Template:Murina';

//echo $page . "\n";
//echo filesafe_name($page) . "\n";


$cache_dir = dirname(__FILE__) . '/cache';

$files = array();

// testing a page

$page = 'Template:De_Vos_%26_van_Haren,_2014';
$page = 'Curtis_John_Callaghan';
$page = 'Template:Costa_et_al.,_2017b';
$page = 'Template:Murina';
//$page = 'Murina';

$page = 'Template:Vespertilionidae';
//$page = 'Vespertilionidae';


$files = array(
	filesafe_name($page) . '.xml'
);

$pages = array(

'Glischropus',
'Template:Glischropus',
'Glischropus_aquilus',
'Glischropus_javanus',
'Glischropus_tylopus',
'Pipistrellini',
'Template:Pipistrellini',
'Template:Csorba_et_al.,_2015',
'Pipistrellus papuanus',
'Pipistrellus',
'Pipistrellus raceyi',
'Template:Bates_et_al.,_2006');

$pages=array(
//'Hipposideridae',
/*
'Template:Csorba & Bates, 2005',
'Template:Csorba et al., 2007',
'Template:Kruskop & Eger, 2008',
'Template:Furey et al., 2009',
'Template:Kuo et al., 2009',
'Template:Eger & Lim, 2011',
'Template:Csorba et al., 2011',
'Template:Francis & Eger, 2012',
'Template:Ruedi, Biswas & Csorba, 2012',
'Template:Soisook et al., 2013a',
'Template:Soisook et al., 2013b',
'Template:Son et al., 2015a',
'Template:Son et al., 2015b',
'Template:He, Xiao & Zhou, 2016',
'Template:Soisook et al., 2017',*/
//'Template:Csorba_et_al.,_2015',
//'Glischropus',
'Nyctalus',
'Craseonycteris',
'Hipposideridae',
);

$pages=array(
'Rhinolophoidea',
'Template:Rhinolophoidea',
'Template:Foley_et_al.,_2015',
);

$pages=array(
'Murina',
'Template:Murina',
'Template:Csorba_&_Bates,_2005',
'Template:Csorba_et_al.,_2007',
'Template:Kruskop_&_Eger,_2008',
'Template:Furey_et_al.,_2009',
'Template:Kuo_et_al.,_2009',
'Template:Eger_&_Lim,_2011',
'Template:Csorba_et_al.,_2011',
'Template:Francis_&_Eger,_2012',
'Template:Ruedi,_Biswas_&_Csorba,_2012',
'Template:Soisook_et_al.,_2013a',
'Template:Soisook_et_al.,_2013b',
'Template:Son_et_al.,_2015a',
'Template:Son_et_al.,_2015b',
'Template:He,_Xiao_&_Zhou,_2016',
'Template:Soisook_et_al.,_2017',
);	


$pages=array(
'Agrilus',
'Template:Agrilus',
'Template:Curtis,_1825',
'Template:Curletti,_2015',
'Template:Curletti_&_Dutto,_2017',
'Template:Curletti_&_Migliore,_2014',
'Template:Jendek,_2012a',
'Template:Jendek,_2013',
'Template:Jendek,_2017',
'Template:Jendek,_2018',
'Template:Jendek,_2018a',
'Template:Jendek,_2021',
'Template:Jendek_&_Grebennikov,_2018',
'Template:Jendek_&_Nakládal,_2017',
'Template:Cid-Arcos_&_Pineda,_2019',
'Template:Curletti,_1994',
'Template:Curletti,_2001',
'Template:Curletti,_2010',
'Template:Curletti,_2010a',
'Template:Curletti,_2013a',
'Template:Curletti_&_Sakalian,_2009',
'Template:Curletti_&_van_Harten,_2002',
'Template:Jendek,_2001',
'Template:Jendek,_2007',
'Template:Jendek,_2012',
'Template:Jendek,_2015',
'Template:Jendek_&_Chamorro,_2012',
'Template:Jendek_&_Grebennikov,_2009',
'Template:Jendek_&_Nakládal,_2018',
'Template:Królik_&_Janicki,_2005',
'Template:Curletti_&_Pineda,_2019',
);

// Molossidae
$pages=array(
'Eumops_glaucinus',
'Eumops_maurus',
'Eumops_dabbenei',
'Eumops_auripendulus',
'Eumops_bonariensis',
'Mops',
'Myopterus',
'Eumops_hansae',
'Eumops_perotis',
'Chaerephon_jobensis',
'Molossus_pretiosus',
'Chaerephon_tomensis',
'Chaerephon_pumilus',
'Molossus_sinaloae',
'Cheiromeles_parvidens',
'Chaerephon_bregullae',
'Otomops_madagascariensis',
'Molossus_currentium',
'Molossus_barnesi',
'Mormopterus_acetabulosus',
'Tadarida_teniotis',
'Eumops_underwoodi',
'Eumops',
'Molossus_aztecus',
'Nyctinomops_macrotis',
'Chaerephon_shortridgei',
'Mormopterus_phrudus',
'Otomops_formosus',
'Mormopterus_beccarii',
'Myopterus_daubentonii',
'Tadarida_insignis',
'Eumops_patagonicus',
'Cheiromeles',
'Tadarida_fulminans',
'Otomops_martiensseni',
'Chaerephon_russatus',
'Mormopterus_kalinowskii',
'Mormopterus_doriae',
'Nyctinomops_laticaudatus',
'Mormopterus_jugularis',
'Mops_leucostigma',
'Mops_nanulus',
'Mops_brachypterus',
'Molossops_neglectus',
'Cynomops_greenhalli',
'Neoplatymops_mattogrossensis',
'Mops_congicus',
'Mops_spurrelli',
'Tomopeas_ravus',
'Sauromys_petrophilus',
'Cynomops_paranus',
'Cynomops_mexicanus',
'Chaerephon_nigeriae',
'Otomops_wroughtoni',
'Otomops',
'Promops_nasutus',
'Mormopterus_minutus',
'Chaerephon_plicatus',
'Nyctinomops_femorosaccus',
'Mormopterus_loriae',
'Cheiromeles_torquatus',
'Molossus_molossus',
'Otomops_johnstonei',
'Mormopterus',
'Promops',
'Molossinae',
'Mops_(Xiphonycteris)',
'Sauromys',
'Neoplatymops',
'Cynomops',
'Nyctinomops',
'Eumops_wilsoni',
'Platymops',
'Otomops_harrisoni',
'Mormopterus_eleryi',
'Chaerephon_atsinanana',
'Tomopeatinae',
'Molossops_(Molossops)',
'Molossus_fentoni',
'Mops_(Mops)',
'Tomopeas',
'Molossus_coibensis',
'Molossus_alvarezi',
'Cabreramops',
'Chaerephon_major',
'Nyctinomops_aurispinosus',
'Otomops_papuensis',
'Tadarida_australis',
'Eumops_trumbulli',
'Otomops_secundus',
'Tadarida_lobata',
'Chaerephon_aloysiisabaudiae',
'Chaerephon_ansorgei',
'Chaerephon_leucogaster',
'Myopterus_whitleyi',
'Chaerephon_bivittatus',
'Promops_centralis',
'Tadarida_aegyptiaca',
'Molossops',
'Tadarida',
'Tadarida_brasiliensis',
'Chaerephon',
'Molossus',
'Chaerephon_chapini',
'Chaerephon_solomonis',
'Molossus_rufus',
'Tadarida_latouchei',
'Chaerephon_johorensis',
'Mormopterus_planiceps',
'Tadarida_kuboriensis',
'Chaerephon_bemmeleni',
'Chaerephon_gallagheri',
'Mormopterus_norfolkensis',
'Tadarida_ventralis',
'Mops_niangarae',
'Mops_mops',
'Mops_thersites',
'Cynomops_abrasus_abrasus',
'Mops_(Mops)_condylurus_condylurus',
'Sauromys_petrophilus_fitzsimonsi',
'Sauromys_petrophilus_umbratus',
'Chaerephon_plicatus_plicatus',
'Cynomops_abrasus_brachymeles',
'Cynomops_abrasus_cerastes',
'Molossops_temminckii_temminckii',
'Mops_(Mops)_condylurus_orientis',
'Mops_(Mops)_midas_miarensis',
'Chaerephon_plicatus_luzonus',
'Cynomops_abrasus_mastivus',
'Mops_(Xiphonycteris)_brachypterus_brachypterus',
'Chaerephon_plicatus_insularis',
'Mops_(Mops)_condylurus_osborni',
'Mops_(Mops)_condylurus_wonderi',
'Mops_(Mops)_sarasinorum_lanei',
'Platymops_setiger_macmillani',
'Platymops_setiger_setiger',
'Sauromys_petrophilus_petrophilus',
'Mops_trevori',
'Mops_niveiventer',
'Mops_demonstrator',
'Mops_petersoni',
'Mops_sarasinorum',
'Mops_midas',
'Mops_condylurus',
'Cynomops_abrasus',
'Molossops_temminckii',
'Platymops_setiger',
'Chaerephon_chapini_lancasteri',
'Cheiromeles_torquatus_jacobsoni',
'Nyctinomops_laticaudatus_yucatanicus',
'Sauromys_petrophilus_erongensis',
'Sauromys_petrophilus_haagneri',
'Tadarida_fulminans_fulminans',
'Tadarida_fulminans_mastersoni',
'Chaerephon_bemmeleni_cistura',
'Chaerephon_chapini_chapini',
'Chaerephon_jobensis_colonicus',
'Chaerephon_jobensis_jobensis',
'Chaerephon_plicatus_dilatatus',
'Chaerephon_plicatus_tenuis',
'Eumops_perotis_californicus',
'Molossops_temminckii_sylvia',
'Molossus_molossus_pygmaeus',
'Molossus_sinaloae_sinaloae',
'Mormopterus_loriae_ridei',
'Myopterus_daubentonii_daubentonii',
'Nyctinomops_laticaudatus_ferruginea',
'Otomops_martiensseni_icarus',
'Promops_centralis_centralis',
'Promops_nasutus_nasutus',
'Tadarida_brasiliensis_intermedia',
'Tadarida_teniotis_rueppelli',
'Cheiromeles_torquatus_caudatus',
'Cheiromeles_torquatus_torquatus',
'Eumops_bonariensis_nanus',
'Eumops_glaucinus_floridanus',
'Eumops_perotis_perotis',
'Eumops_underwoodi_underwoodi',
'Molossops_temminckii_griseiventer',
'Molossus_currentium_robustus',
'Molossus_sinaloae_trinitatus',
'Mormopterus_loriae_cobourgiana',
'Mormopterus_loriae_loriae',
'Eumops_auripendulus_major',
'Eumops_patagonicus_beckeri',
'Eumops_patagonicus_patagonicus',
'Molossus_molossus_fortis',
'Mops_(Mops)_midas_midas',
'Mops_(Mops)_sarasinorum_sarasinorum',
'Mops_(Xiphonycteris)_brachypterus_leonis',
'Nyctinomops_laticaudatus_laticaudatus',
'Tadarida_aegyptiaca_thomasi',
'Tadarida_brasiliensis_brasiliensis',
'Tadarida_brasiliensis_cynocephala',
'Tadarida_ventralis_africana',
'Tadarida_ventralis_ventralis',
'Molossus_currentium_currentium',
'Molossus_molossus_tropidorhynchus',
'Mormopterus_beccarii_beccarii',
'Tadarida_brasiliensis_antillularum',
'Tadarida_brasiliensis_constanzae',
'Tadarida_brasiliensis_murina',
'Cabreramops_aequatorianus',
'Chaerephon_bemmeleni_bemmeleni',
'Chaerephon_nigeriae_spillmani',
'Eumops_auripendulus_auripendulus',
'Eumops_bonariensis_bonariensis',
'Eumops_bonariensis_delticus',
'Eumops_perotis_gigas',
'Nyctinomops_laticaudatus_macarenensis',
'Otomops_martiensseni_martiensseni',
'Promops_centralis_davisoni',
'Promops_centralis_occultus',
'Promops_nasutus_downsi',
'Promops_nasutus_fosteri',
'Tadarida_aegyptiaca_aegyptiaca',
'Tadarida_aegyptiaca_bocagei',
'Tadarida_brasiliensis_mexicana',
'Eumops_glaucinus_glaucinus',
'Molossus_currentium_bondae',
'Molossus_molossus_verrilli',
'Promops_nasutus_ancilla',
'Promops_nasutus_pamana',
'Tadarida_aegyptiaca_sindica',
'Tadarida_brasiliensis_bahamensis',
'Tadarida_teniotis_teniotis',
'Cynomops_planirostris',
'Chaerephon_nigeriae_nigeriae',
'Eumops_underwoodi_sonoriensis',
'Molossus_molossus_debilis',
'Molossus_molossus_milleri',
'Molossus_molossus_molossus',
'Mormopterus_beccarii_astrolabiensis',
'Myopterus_daubentonii_albatus',
'Nyctinomops_laticaudatus_europs',
'Tadarida_aegyptiaca_tragatus',
'Tadarida_brasiliensis_muscula',
'Template:Tadarida',
'Template:Nyctinomops',
'Template:Myopterus',
'Template:Mormopterus',
'Template:Molossus',
'Template:Eumops',
'Template:Chaerephon',
'Template:Cynomops',
'Template:Promops',
'Template:Otomops',
'Template:Cabreramops',
'Template:Mops',
'Template:Molossops',
'Template:Cheiromeles',
'Template:Sauromys',
'Template:Platymops',
'Template:Laurie,_1952',
'Template:Gregorin_&_Cirranello,_2016',
'Template:Tomopeas',
'Template:Loureiro,_Lim_&_Engstrom,_2018',
'Template:Tomopeatinae',
'Template:Ralph_et_al.,_2015',
'Template:Neoplatymops',
'Template:Molossinae',
);


$pages=array(
'Mormopterus norfolkensis',
'Template:Mormopterus'
);



$pages=array('Template:Curletti_&_Sakalian,_2009');

$files = array();
foreach ($pages as $p)
{
	$files[] = filesafe_name($p) . '.xml';
}



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
		print_r($obj);

		$graph = convert_to_rdf($obj);

		$obj->base_filename = filesafe_name($obj->title);

		output_rdf($graph, $obj);
	}
}

?>
