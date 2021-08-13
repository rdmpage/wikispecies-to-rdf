<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/lib.php');

//----------------------------------------------------------------------------------------
function process_references ($obj)
{
	global $config;
	
	if (!isset($obj->references))
	{
		return;
	}
	
	// Call external service to parse reference
	$n = count($obj->references);
	for ($i = 0; $i < $n; $i++)
	{
		$string = $obj->references[$i]->string;
		
		$url = $config['parser_url'] . '/parse?string=' . urlencode($string);

		$json = get($url);
		if ($json != '')
		{
			$csl = json_decode($json);
	
			$ignore = true;
	
			if (isset($csl->unstructured))
			{
				$ignore = false;
				if ($csl->unstructured == '')
				{
					$ignore = true;
				}
			}

			if (!$ignore)
			{					
	
				// clean
				if (isset($csl->matched))
				{
					unset($csl->matched);
				}
				if (isset($csl->parts))
				{
					unset($csl->parts);
				}
	
				$obj->references[$i]->csl = $csl;
			}
		}
	}	

	
}	

?>
	