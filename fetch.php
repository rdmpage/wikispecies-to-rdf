<?php

error_reporting(E_ALL);

// Fetch pages direct from Wikispecies and optionally include transclusions
// Use this to get some exmaple pages to play with


require_once (dirname(__FILE__) . '/lib.php');


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

$page_names = array();


$page_names = array(
//'Katsura_Morimoto',
//'Ingolf_S._Askevold',
//'Vasily_Viktorovich_Grebennikov',
//'Francis_Gard_Howarth',
//'Jan_Bezděk_(entomologist)',
//'Rob_de_Vos'
//'ISSN_1210-5759',
//'Template:Tarmann_%26_Cock,_2019'
//'Julien Achard',
//'Glyptosceloides',
//'Template:Urtubey et al., 2016'
'Julien Achard',
);


// Read list of page names
if (0)
{
	$page_names = array();
	
	$filename = 'pages.txt';

	$file_handle = fopen($filename, "r");
	while (!feof($file_handle)) 
	{
		$page_names[] = trim(fgets($file_handle));
	}

}

$include_transclusions = true;
//$include_transclusions = false;

$force = true;
//$force = false;


while (count($page_names) > 0)
{
	$page_name = array_pop($page_names);

	$filename = filesafe_name($page_name) . '.xml';
	
	$filename = 'cache/' . $filename;
	
	if (!file_exists($filename))
	{
		$url = 'https://species.wikimedia.org/w/index.php?title=Special:Export&pages=' . urlencode($page_name);
	
		echo $url . "\n";

		$xml = get($url);	
		
		file_put_contents($filename, $xml);
	}
	$xml = file_get_contents($filename);
	
	// echo $xml;
	
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace("wiki", "http://www.mediawiki.org/xml/export-0.10/");
		
	$nodeCollection = $xpath->query ("//wiki:text");
	foreach($nodeCollection as $node)
	{
		// get text
		$text = $node->firstChild->nodeValue;		
		$lines = explode("\n", $text);
		
		foreach ($lines as $line)
		{
			
			if ($include_transclusions)
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
						$page_names[] = 'Template:' . $refname;							
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
						$page_names[] = 'Template:' . $refname;							
						$matched = true;	
					}			
				}
			}						
		}	
	}
	$include_transclusions = false; // only do this the first time

}

?>
