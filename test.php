<?php

error_reporting(E_ALL);


require_once('vendor/autoload.php');

require_once(dirname(__FILE__) .  '/vendor/digitalbazaar/json-ld/jsonld.php');

use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

$filename = 'json-ld-examples/s00606-016-1316-4.nt';

$triples = file_get_contents($filename);

if (0)
{
	// DIGITAL BAZAAR
	$doc = jsonld_from_rdf($triples, array('format' => 'application/nquads'));
	
	print_r($doc);

	$context = new stdclass;

	$context->{'@vocab'} = 'http://schema.org/';
	//$context->schema = 'http://schema.org/';

	$author = new stdclass;
	$author->{'@id'} = "author";
	$author->{'@container'} = "@list";

	$context->author = $author;

	// Frame document
	$frame = (object)array(
		'@context' => $context,
		'@type' => 'http://schema.org/ScholarlyArticle'
	);	
	
	$obj = jsonld_frame($doc, $frame);
	
	//$obj = jsonld_compact($doc, $context);

	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


}

if (1)
{
	// ML
	
	$nquads = new NQuads();

	// And parse them again to a JSON-LD document
	$quads = $nquads->parse($triples);	
	
	$doc = JsonLD::fromRdf($quads);
	
	print_r($doc);
	
	$context = new stdclass;

	$context->{'@vocab'} = 'http://schema.org/';
	//$context->schema = 'http://schema.org/';

	$author = new stdclass;
	$author->{'@id'} = "author";
	$author->{'@container'} = "@list";

	$context->author = $author;

	// Frame document
	$frame = (object)array(
		'@context' => $context,
		'@type' => 'http://schema.org/ScholarlyArticle'
	);	
	
	
	$framed = JsonLD::frame($doc, $frame);

	print_r($framed);
	

}


if(0)
{
	$graph = new \EasyRdf\Graph();

	$graph->parse($triples);



	$context_json = file_get_contents('json-ld-examples/sgcontext.json');

	$context = json_decode($context_json);

	$context = new stdclass;

	$context->{'@vocab'} = 'http://schema.org/';
	$context->schema = 'http://schema.org/';

	$author = new stdclass;
	$author->{'@id'} = "schema:author";
	$author->{'@container'} = "@set";

	$context->author = $author;



	// Frame document
	$frame = (object)array(
	//	'@context' => $context,
		'@type' => 'http://schema.org/ScholarlyArticle'
	);	

	$options = array();
	$options['context'] = $context;
	$options['compact'] = true;
	$options['frame'] = $frame;


	$format = \EasyRdf\Format::getFormat('jsonld');
	$data = $graph->serialise($format, $options);
	
	$obj = json_decode( $data);
	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	
}


?>


