<?php


// export to RIS

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/csl_utils.php');
require_once (dirname(__FILE__) . '/parse.php');
require_once (dirname(__FILE__) . '/reference_parser.php');
require_once (dirname(__FILE__) . '/taxon_name_parser.php');

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

$cache_dir = dirname(__FILE__) . '/cache';


$pages=array('Template:Curletti_&_Sakalian,_2009');

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
		// print_r($obj);
		
		if (isset($obj->references))
		{
			foreach ($obj->references as $reference)
			{
				if (isset($reference->csl))
				{
					$ris = csl_to_ris($reference->csl);
					
					echo $ris;
					echo "\n";
				}
			}
		
		}

	}
}

?>
