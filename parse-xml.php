<?php

error_reporting(E_ALL);

// Parse Wikis[ecies XML for one page and create a simple object with the core data.

//----------------------------------------------------------------------------------------
// Extract an object from Wikispecies XML
function xml_to_object($xml)
{
	// XML parsing
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace("wiki", "http://www.mediawiki.org/xml/export-0.10/");
	
	// Taxon name parsing
	$pp = new Parser();

	// object to hold data
	$obj = new stdclass;

	$obj->type = 'unknown';
	$obj->url = '';
	$obj->is_template = false;

	$nodeCollection = $xpath->query ("//wiki:page/wiki:id");
	foreach($nodeCollection as $node)
	{
		$obj->id = $node->firstChild->nodeValue;
	}		

	$nodeCollection = $xpath->query ("//wiki:title");
	foreach($nodeCollection as $node)
	{
		$obj->title = $node->firstChild->nodeValue;
		$obj->url = 'https://species.wikimedia.org/wiki/' . urlsafe_name($obj->title);
	}	

	$nodeCollection = $xpath->query ("//wiki:timestamp");
	foreach($nodeCollection as $node)
	{
		$obj->timestamp = $node->firstChild->nodeValue;
	}	
	
	$nodeCollection = $xpath->query ("//wiki:page/wiki:ns");
	foreach($nodeCollection as $node)
	{
		$obj->is_template = ($node->firstChild->nodeValue == 10);
	}		
	

	//$mode = 'text';
	
	$nodeCollection = $xpath->query ("//wiki:text");
	foreach($nodeCollection as $node)
	{
		$obj->references = array();
		
		// get text
		$obj->text = $node->firstChild->nodeValue;		
		$lines = explode("\n", $obj->text);
	
		// $reference_counter = 0;
	
		$previous_line = "";
			
		foreach ($lines as $line)
		{
		
			// what is this page about?---------------------------------------------------
			
			// reference templates are flagged by category
			if (preg_match('/\[\[Category:Reference templates\]\]/', $line) && $obj->is_template)
			{
				$obj->type = 'reference';
			}

			// taxon navigation pages MAY be in this category
			if (preg_match('/\[\[Category:Taxonavigation templates\]\]/', $line) && $obj->is_template)
			{
				$obj->type = 'navigation';
			}
			
			if (preg_match('/\[\[Category:Taxon authorities\]\]/', $line) && !$obj->is_template)
			{
				$obj->type = 'person';
			}								
			
			// taxa have Taxonavigation
			if (preg_match('/int:Taxonavigation/', $line)  && !$obj->is_template)
			{
				$obj->type = 'taxon';
			}
			
			if ($previous_line == "") // first line
			{
				if (preg_match('/\{\{(Taxonav\|)?(?<taxon>[^\}]+)\}\}/', $line, $m))
				{
					if (in_array($obj->type, array('unknown', 'navigation')))
					{
						$obj->navigation = $m['taxon'];
						
						// fingers crossed we've got this right
						if ($obj->type == 'unknown')
						{
							$obj->type = 'navigation';
						}
					}				
				}			
			}
			
			// image----------------------------------------------------------------------
			if (preg_match('/\{\{image\}\}/', $line))
			{
				// there's a bunch of stuff happening here to get the image, so ignore this for now
				// {{image}}
				//$obj->image = $obj->title; // does this always work?
				//$obj->thumbnailUrl = 'https://commons.wikimedia.org/w/thumb.php?f=' . str_replace(' ', '_', $obj->image) . '&w=200';
			}	

			if (preg_match('/\[\[(File|Image):(?<filename>[^\|]+)\|/', $line, $m))
			{
				$obj->image = $m['filename'];
				$obj->thumbnailUrl = 'https://commons.wikimedia.org/w/thumb.php?f=' . str_replace(' ', '_', $obj->image) . '&w=200';
			}	
	
			// references-----------------------------------------------------------------
			
			$matched_reference = false;
			
			// echo $line . "\n";
			
			if (preg_match('/^\s*\*\s+\{\{a/', $line))
			{
				// possible reference
				
				// echo "*** matched ".  __LINE__ .  " *** $line\n";	
			
				$r = trim($line);
				$r = str_replace('</text>', '', $r);
			
				$citation = new stdclass;
				$citation->string = $r;
				$obj->references[] = $citation;
				
				$matched_reference = true;
			}	
		
			if (!$matched_reference)
			{
		
				// transcluded references
				if (!$matched_reference)
				{
					// reference with optional page number
					// * {{Linnaeus, 1758|190}}
					if (preg_match('/^(\*\s+)?\{\{(?<refname>[A-Z][\']?[\p{L}]+,\s+[0-9]{4}[a-z]?)(\|\d+)\}\}$/u', trim($line), $m))
					{
					
						// echo "*** matched ".  __LINE__ .  " *** $line\n";			
					
						$refname = $m['refname'];
						$refname = str_replace(' ', '_', $refname);
						$refname = str_replace('&', '%26', $refname);	
					
						$citation = new stdclass;
						$citation->wiki_name = 'Template:' . $refname;
						$obj->references[] = $citation;
					
						$matched_reference = true;	
					}			
			
				}
			
				if (!$matched_reference)
				{
					if (preg_match('/^(\*\s+)?\{\{(?<refname>[A-Z][\']?[\p{L}]+([,\s&;[a-zA-Z]+)[0-9]{4}[a-z]?)\}\}$/u', trim($line), $m))
					{
					
						// echo "*** matched ".  __LINE__ .  " *** $line\n";	
					
						$refname = $m['refname'];
						$refname = str_replace(' ', '_', $refname);
						$refname = str_replace('&', '%26', $refname);	
					
						$citation = new stdclass;
						$citation->wiki_name = 'Template:' . $refname;
						$obj->references[] = $citation;
					
						$matched_reference = true;	
					}			
				}

				if (!$matched_reference)
				{
					if (preg_match('/^\{\{(?<refname>[A-Z][\']?[\p{L}]+(.*)\s+[0-9]{4}[a-z]?)\}\}$/u', trim($line), $m))
					{
						$refname = $m['refname'];
						$refname = str_replace(' ', '_', $refname);
						$refname = str_replace('&', '%26', $refname);	
				
						$citation = new stdclass;
						$citation->wiki_name = 'Template:' . $refname;
						$obj->references[] = $citation;
					
						$matched_reference = true;	
					}			
				}


			}
			
			// parent taxon  -------------------------------------------------------------
			if (preg_match('/\{\{int:Taxonavigation\}\}/', $previous_line))
			{			
				if (preg_match('/^\{\{(?<refname>[^\}]+)\}\}$/u', trim($line), $m))
				{
					// this may be the taxon itself, e.g.
					// https://species.wikimedia.org/w/index.php?title=Acanthocephala_terminalis&action=edit
					// or the parent taxon, e.g.
					// https://species.wikimedia.org/w/index.php?title=Murina_walstoni&action=edit&section=1
					// I think in any event the template will always include the parent (?)
					
					$obj->parent = 'Template:' . $m['refname'];				
					$matched = true;	
				}			

			}
			
					
			// taxonomic name ------------------------------------------------------------
			// do we try and parse wiki markup, or strip and parse using name parser?
			if (preg_match('/\{\{int:Name\}\}/', $previous_line))
			{			
				$namestring = $line;
			
				// remove italics
				$namestring = str_replace("''", "", $namestring);
			
				// clean authorship of links			
				$authorship = '';			
				$authors = array();
			
				$matched = false;
			
				if (!$matched)
				{
					if (count($authors) == 0)
					{			
						if (preg_match_all("/\{\{a\|[^[\|\}]+(\|([^\}]+))\}\}/", $namestring, $m))
						{
							//print_r($m);
					
							//$authors = $m[2];
							$n = count($m[0]);
							for ($k = 0; $k < $n; $k++)
							{
								$namestring = str_replace($m[0][$k], $m[2][$k], $namestring);
							}
						
							$matched = true;
						}
					}
				}
			
				if (!$matched)
				{
					if (count($authors) == 0)
					{			
						if (preg_match_all("/\{\{a\|([^[\|\}]+)\}\}/", $namestring, $m))
						{
							//rint_r($m);

							$n = count($m[0]);
							for ($k = 0; $k < $n; $k++)
							{
								$namestring = str_replace($m[0][$k], $m[1][$k], $namestring);
							}
						
							$matched = true;
						}
					}
				}

				// Now we should have something cleaned of formatting and links	
				
				$namestring = preg_replace('/^\s*\*\s*/', '', $namestring);
						
				// echo $namestring . "\n";
				
				
			
				$r = $pp->parse($namestring);
	
				//print_r($r);
				
				//exit();
			
				$obj->scientificName = new stdclass;			
				if (isset($r->scientificName))
				{
					// Canonical name
					if ($r->scientificName->parsed)
					{
						$obj->scientificName->canonical = $r->scientificName->canonical;
					}
				}

			}
		
			// keep previous line
			if ($line != '')
			{
				$previous_line = $line;			
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
	
	if (isset($obj->url) && ($obj->url != ""))
	{
		// post process references
		process_references($obj);
	}
	else
	{
		$obj = null;
	}
	
	// debug
	unset($obj->text);
	
	return $obj;
}


?>
