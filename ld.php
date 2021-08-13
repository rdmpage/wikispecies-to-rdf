<?php

error_reporting(E_ALL);



require_once('vendor/autoload.php');
require_once (dirname(__FILE__) . '/reference_parser.php');

//----------------------------------------------------------------------------------------
function filesafe_name($name)
{
	$name = str_replace(array_merge(
        array_map('chr', range(0, 31)),
        array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
    ), '', $name);
    
    $name = str_replace(' ', '_', $name);
    $name = str_replace('_%26_', '_&_', $name);
    
    return $name;

}

//----------------------------------------------------------------------------------------


$page = 'Glyptosceloides';
//$page = 'TemplateAskevold_&_Flowers,_1994';
//$page = 'TemplateTarmann_&_Cock,_2019';
//$page = 'Template:Urtubey et al., 2016';
//$page = 'TemplateDe_Vos,_2019a';
//$page = 'Template:O\'Brien, Askevold & Morimoto, 1994';
//$page = 'Julien Achard';
//$page = 'Redonographa chilensis';
//$page = 'Template:Lücking_et_al.,_2013a';
//$page = 'Robert Lücking';
//$page = 'Template:Lücking,_Parnmen_&_Lumbsch,_2012';
//$page = 'Template:Lücking_et_al.,_2017c';

$page = 'Template:Askevold_%26_Flowers,_1994';

$xml = file_get_contents('cache/' . filesafe_name($page) . '.xml');

echo $xml;

if ($xml == '')
{
	echo "*** Error ***\n";
	echo "XML empty\n";
	exit();
}

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace("wiki", "http://www.mediawiki.org/xml/export-0.10/");


$obj = new stdclass;

$obj->type = 'unknown';
$obj->url = '';

$nodeCollection = $xpath->query ("//wiki:page/wiki:id");
foreach($nodeCollection as $node)
{
	$obj->id = $node->firstChild->nodeValue;
}		

$nodeCollection = $xpath->query ("//wiki:title");
foreach($nodeCollection as $node)
{
	$obj->title = $node->firstChild->nodeValue;
	$obj->url = 'https://species.wikimedia.org/wiki/' . filesafe_name($obj->title);
}	

$nodeCollection = $xpath->query ("//wiki:timestamp");
foreach($nodeCollection as $node)
{
	$obj->timestamp = $node->firstChild->nodeValue;
}	

	
$nodeCollection = $xpath->query ("//wiki:text");
foreach($nodeCollection as $node)
{
	$obj->references = array();
		
	// get text
	$obj->text = $node->firstChild->nodeValue;		
	$lines = explode("\n", $obj->text);
	
	// $reference_counter = 0;
	
	foreach ($lines as $line)
	{
		// what is this page about?
		if (preg_match('/\[\[Category:Reference templates\]\]/', $line))
		{
			$obj->type = 'reference';
		}
	
		if (preg_match('/^\*\s+\{\{a/', $line))
		{
			// possible reference
			
			$r = trim($line);
			$r = str_replace('</text>', '', $r);
			
			$citation = new stdclass;
			$citation->string = $r;
			$obj->references[] = $citation;
		}	
		
		{
		
			// transcluded references
			$matched = false;
			if (!$matched)
			{
				if (preg_match('/^(\*\s+)?\{\{(?<refname>[A-Z][\']?[\p{L}]+([,\s&;[a-zA-Z]+)[0-9]{4}[a-z]?)\}\}$/u', trim($line), $m))
				{
					$refname = $m['refname'];
					$refname = str_replace(' ', '_', $refname);
					$refname = str_replace('&', '%26', $refname);	
					
					$citation = new stdclass;
					$citation->wiki_name = 'Template:' . $refname;
					$obj->references[] = $citation;
					
					$matched = true;	
				}			
			}

			if (!$matched)
			{
				if (preg_match('/^\{\{(?<refname>[A-Z][\']?[\p{L}]+(.*)\s+[0-9]{4}[a-z]?)\}\}$/u', trim($line), $m))
				{
					$refname = $m['refname'];
					$refname = str_replace(' ', '_', $refname);
					$refname = str_replace('&', '%26', $refname);	
				
					$citation = new stdclass;
					$citation->wiki_name = 'Template:' . $refname;
					$obj->references[] = $citation;
					
					$matched = true;	
				}			
			}


		}			
		
	}	
		
	// taxonomy
	if (preg_match('/== Taxonavigation ==\s+\{\{(?<parent>.*)\}\}/Uu', $obj->text, $m))
	{
		$obj->taxonavigation = $m['parent'];
	}
	
	// categories		
	if (preg_match_all('/\[\[Category:\s*(?<category>.*)\]\]/Uu', $obj->text, $m))
	{
		$obj->categories = $m['category'];
	}

	
}

process_references($obj);

print_r($obj);

//----------------------------------------------------------------------------------------

// convert to RDF

// Construct a graph of the results		
$graph = new \EasyRdf\Graph();


$page = $graph->resource($obj->url, 'schema:WebPage');
$page->addResource('schema:url', $obj->url);

// stuff about this entity

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
			// this is a tranclusion, so add a link...
			
			$work = $graph->newBNode('schema:CreativeWork');
			
			$wiki_link = 'https://species.wikimedia.org/wiki/' . $reference->wiki_name;
			$work->addResource('schema:mainEntityOfPage', $wiki_link );
			
			// we are citing this reference
			$page->addResource('schema:citation', $work);		
			
		
		}
		else
		{
			// this is an actual reference
		
			// Do we have an external identifier?
			if (isset($reference->csl))
			{
				if ($id == '')
				{
					if (isset($reference->csl->DOI))
					{
						$id = 'https://doi.org/' .  $reference->csl->DOI;
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
			
			if ($id == '')
			{
				$work = $graph->newBNode('schema:CreativeWork');
			}
			else
			{
				$work = $graph->resource($id, 'schema:CreativeWork');	
			}
			
			// is this page JUST about this reference? Yes? Then use mainEntity
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
				$work->add('schema:description', $reference->string);
			
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
	
			// to RDF
			if (isset($reference->csl))
			{
				if (isset($reference->csl->title))
				{
					$work->add('schema:name', strip_tags($reference->csl->title));
				}

				// simple literals
				if (isset($reference->csl->volume))
				{
					$work->add('schema:volumeNumber', $reference->csl->volume);
				}
				if (isset($reference->csl->issue))
				{
					$work->add('schema:issueNumber', $reference->csl->issue);
				}
				if (isset($reference->csl->page))
				{
					$work->add('schema:pagination', $reference->csl->page);
				}
		
				// date
				if (isset($reference->csl->issued))
				{
					$date = '';
					$d = $reference->csl->issued->{'date-parts'}[0];
		
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
		
				// authors (how do we handle order?)
				if (isset($reference->csl->author))
				{
					foreach ($reference->csl->author as $creator)
					{
						$author = $graph->newBNode('schema:Person');
				
						if (isset($creator->WIKISPECIES))
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
				
				// container
				if (isset($reference->csl->{'container-title'}))
				{
					$container = $graph->newBNode('schema:Periodical');
					$container->add('schema:name', $reference->csl->{'container-title'});
				
					if (isset($reference->csl->ISSN))
					{
						$container->add('schema:issn', $reference->csl->ISSN[0]);
						$container->addResource('schema:mainEntityOfPage', 'https://species.wikimedia.org/wiki/ISSN_' . $reference->csl->ISSN[0]);
		
					}					
					$work->add('schema:isPartOf', $container);
				}
			
				// identifiers sameAs/seeAlso
				if (isset($reference->csl->BHL))
				{
					$work->addResource('schema:seeAlso', 'https://www.biodiversitylibrary.org/page/' . $reference->csl->BHL);
				}
				if (isset($reference->csl->JSTOR))
				{
					$work->addResource('schema:sameAs', 'https://www.jstor.org/stable/' . $reference->csl->JSTOR);
				}
			
			
				// identifiers as property-value pairs
				if (isset($reference->csl->DOI))
				{
					$identifier = $graph->newBNode('schema:PropertyValue');
					$identifier->add('schema:name', 'doi');
					$identifier->add('schema:value', $reference->csl->DOI);
			
					$work->add('schema:identifier', $identifier);
				}

				// URL(s)?
				if (isset($reference->csl->URL))
				{
					$urls = array();
					if (!is_array($reference->csl->URL))
					{
						$urls = array($reference->csl->URL);
					}
					else
					{
						$urls = $reference->csl->URL;
					}
				
					foreach ($urls as $url)
					{
						$work->addResource('schema:url', $url);
					}
				}
		
	
			}
			
		}
	
	

	}
}

//----------------------------------------------------------------------------------------
// serialise

$format = \EasyRdf\Format::getFormat('ntriples');
$triples = $graph->serialise($format);

$base_filename = filesafe_name($obj->title);
$ld_filename = $base_filename . '.nt';

file_put_contents($ld_filename, $triples);

$context = new stdclass;
$context->{'@vocab'} = 'http://schema.org/';

// Frame document
$frame = (object)array(
	'@context' => $context,
	'@type' => 'http://schema.org/WebPage'
);	
	
$options = array();
$options['context'] = $context;
$options['compact'] = true;
$options['frame']= $frame;	

$format = \EasyRdf\Format::getFormat('jsonld');
$data = $graph->serialise($format, $options);

$obj = json_decode( $data);
echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$ld_filename = $base_filename . '.json';

file_put_contents($ld_filename, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));




?>


