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
function fetch_pages($page_names, $force = false, $include_transclusions = false)
{
	$cache_dir = dirname(__FILE__) . '/cache';

	// store any transclusions we might want to resolve
	$to_resolve = array();

	while (count($page_names) > 0)
	{
		$page_name = array_pop($page_names);

		$filename = filesafe_name($page_name) . '.xml';
	
		$filename = $cache_dir . '/' . $filename;
	
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
		
			$previous_line = '';
		
			foreach ($lines as $line)
			{
			
				if ($include_transclusions)
				{
					$matched = false;
				
					// parent taxon
					if (!$matched)
					{

						if (preg_match('/\{\{int:Taxonavigation\}\}/', $previous_line, $m))
						{
							if (preg_match('/^\{\{(?<refname>[^\}]+)\}\}$/u', trim($line), $m))
							{
								$refname = $m['refname'];
								$refname = str_replace(' ', '_', $refname);
								$to_resolve [] = 'Template:' . $refname;							
								$matched = true;	
							}			
						}
					}
			
			
					// transcluded references
					if (!$matched)
					{
						if (preg_match('/^(\*\s+)?\{\{(?<refname>[A-Z][\']?[\p{L}]+([,\s&;[a-zA-Z]+)[0-9]{4}[a-z]?)\}\}$/u', trim($line), $m))
						{
							$refname = $m['refname'];
							$refname = str_replace(' ', '_', $refname);
							$to_resolve[] = 'Template:' . $refname;							
							$matched = true;	
						}			
					}

					if (!$matched)
					{
						if (preg_match('/^\{\{(?<refname>[A-Z][\']?[\p{L}]+(.*)\s+[0-9]{4}[a-z]?)\}\}$/u', trim($line), $m))
						{
							$refname = $m['refname'];
							$refname = str_replace(' ', '_', $refname);
							$to_resolve[] = 'Template:' . $refname;							
							$matched = true;	
						}			
					}
				}
			
				if (trim($line) != "")						
				{
					$previous_line = $line;
				}
			}	
		}
	}
	
	return $to_resolve;
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
'Glyptosceloides',
'Template:Urtubey et al., 2016',
//'Julien Achard',
//'Redonographa chilensis',
//'Robert Lücking',
'Template:Askevold_%26_Flowers,_1994',
'Marcos_A._Raposo',
'Pseudopipra',
'Pseudopipra_pipra',
'Pelargonium_carnosum',
'Pteropodidae',
//'James_I._Menzies',
'Rhinolophus_xinanzhongguoensis',
'Judith_L._Eger',
'Darevskia',
'Ichnotropis',
'Hipposideros khaokhouayensis',
'Murina walstoni',
'Rob_de_Vos',
'Curtis_John_Callaghan',
'Template:Murina',
'Murina',
'Template:Vespertilionidae',
'Vespertilionidae',

'Glischropus',
'Template:Glischropus',
'Glischropus_aquilus',
'Glischropus_javanus',
'Glischropus_tylopus',
'Pipistrellini',
'Template:Pipistrellini',

'Template:Csorba_et_al.,_2015',
);


$page_names = array(
//'Pipistrellus papuanus',
//'Pipistrellus',

//'Hipposideridae',
//'Template:Murina',
//'Murina',
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
'Template:Soisook et al., 2017',
'Nyctalus',
'Craseonycteris',
);

$page_names=array(
//'Rhinolophoidea',
//'Pipistrellus papuanus',
//'Pipistrellus',
//'Murina',
//'Agrilus',
);

/*
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wd: <http://www.wikidata.org/entity/>
SELECT distinct ?species WHERE
{
 VALUES ?root_name {"Molossidae"}
 ?root wdt:P225 ?root_name .
 ?child wdt:P171+ ?root .
 ?child wdt:P171 ?parent .
 ?child wdt:P225 ?child_name .
 ?parent wdt:P225 ?parent_name .
 ?species  schema:about ?child .
 ?species  schema:isPartOf <https://species.wikimedia.org/> .
}
*/

$page_names = array(
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
);

$page_names = array('Fernando_Cesar_Penco');

$page_names=array('Rhinophoridae');

$page_names=array('Silvio_Shigueo_Nihei');

/*
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
*/

$p = $page_names;


$force = true;
//$force = false;

// get pages
$include_transclusions = false;
$include_transclusions = true;

$to_resolve = fetch_pages($page_names, $force, $include_transclusions);

$p = array_unique(array_merge($p, $to_resolve));

// get any transclusions from the set of pages
$include_transclusions = false;
$include_transclusions = true;
fetch_pages($to_resolve, $force, $include_transclusions);

echo "\n\n";
echo '$' . "pages=array(\n'";
echo join("',\n'", $p);
echo "',\n);\n";


?>
