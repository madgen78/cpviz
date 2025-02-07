<?php /* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//  Copyright (C) 2011 Mikael Carlsson (mickecarlsson at gmail dot com)
//

// load graphviz library
require_once 'graphviz/src/Alom/Graphviz/InstructionInterface.php';
require_once 'graphviz/src/Alom/Graphviz/BaseInstruction.php';
require_once 'graphviz/src/Alom/Graphviz/Node.php';
require_once 'graphviz/src/Alom/Graphviz/Edge.php';
require_once 'graphviz/src/Alom/Graphviz/DirectedEdge.php';
require_once 'graphviz/src/Alom/Graphviz/AttributeBag.php';
require_once 'graphviz/src/Alom/Graphviz/Graph.php';
require_once 'graphviz/src/Alom/Graphviz/Digraph.php';
require_once 'graphviz/src/Alom/Graphviz/AttributeSet.php';
require_once 'graphviz/src/Alom/Graphviz/Subgraph.php';


$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$extdisplay = isset($_REQUEST['extdisplay']) ? $_REQUEST['extdisplay'] : '';
$iroute = isset($_REQUEST['iroute']) ? $_REQUEST['iroute'] : '';
 
$html_txt = '<div class="content">';
$html_txt .= '<br><h2>'._("FreePBX Dial Plan Vizualizer").'</h2>';

$full_list = framework_check_extension_usage(true);
$full_list = is_array($full_list)?$full_list:array();

 // Output a selector for the users to choose an inbound route
$inroutes = dp_load_incoming_routes();
//echo "<pre>";print_r($inroutes);echo "</pre>";

$html_txt .= "<form style=\"display: inline;\" name=\"routePrompt\" action=\"$_SERVER[PHP_SELF]\" method=\"POST\">\n";
$html_txt .= "<input type=\"hidden\" name=\"display\" value=\"cpviz\">\n";
$html_txt .= "Select an inbound route: ";
$html_txt .= "<input list=\"nums\" value=\"$iroute\" name=\"iroute\" onfocus=\"this.value=''\" onchange=\"this.blur();\">\n";
$html_txt .= "<datalist id=\"nums\">\n";

if ($inroutes){
	foreach ($inroutes as $ir) {
	  $html_txt .= "<option value=\"$ir[extension]\">$ir[extension]: $ir[description]</option>\n";
	}
}else{
	$html_txt .= "<option>No Routes Found</option>\n";
}

if (empty($_POST) || $_POST['panzoom'] == 'on'){$panchecked='checked';}else{$panchecked='';}
if (isset($_POST['direction'] && $_POST['direction']=='LR'){
	$direction='LR';
	$checked='checked';
}else{
	$direction='TB';
	$checked='';
}
$html_txt .= "</datalist>\n";
$html_txt .= "<input name=\"Submit\" type=\"submit\" value=\"Visualize Dial Plan\">\n";
$html_txt .= "<input type=\"checkbox\" id=\"pan\" name=\"panzoom\" value=\"on\" $panchecked><label for=\"pan\">&nbsp;Pan/Zoom</label>&nbsp;&nbsp;\n";
$html_txt .= "<input type=\"checkbox\" id=\"LR\" name=\"direction\" value=\"LR\" $checked><label for=\"LR\">&nbsp;Horizontal</label>&nbsp;&nbsp;\n";
$html_txt .= "</form>\n";

// Now, if $iroute is set, we will procede to display the call plan
// graph for it.  If not, we would like to just bail, but I haven't
// figured out how to do that in this framework.  If I exit() or 
// throw an exception, then the page doesn't finish loading, no CSS
// happens, it looks ugly like something went really wrong.

if ($iroute != '') {
  $dproute = dp_find_route($inroutes, $iroute);
  if (empty($dproute)) {
    $html_txt .= "<h2>Error: Could not find inbound route for '$iroute'</h2>\n";
    // ugh: throw new \InvalidArgumentException("Could not find and inbound route for '$iroute'");
  } else {
    //$html_txt .= "<pre>\n" . "$iroute route: " . print_r($dproute, true) . "\n</pre><br>\n";
    dp_load_tables($dproute);   # adds data for time conditions, IVRs, etc.
    //$html_txt .= "<pre>\n" . "FreePBX config data: " . print_r($dproute, true) . "\n</pre><br>\n";

    dplog(5, "Doing follow dest ...");
    dp_follow_destinations($dproute, '');
    dplog(5, "Finished follow dest ...");
    if
    $gtext = $dproute['dpgraph']->attr('graph',array('rankdir'=>$direction));
    $gtext = $dproute['dpgraph']->render();
	
    dplog(5, "Dial Plan Graph for $iroute:\n$gtext");
    $gtext = str_replace("\n", "\\n", $gtext);  // ugh, apparently viz chokes on newlines and +, wtf?
    $gtext = str_replace("+", "", $gtext);  

    $html_txt .= "<script src=\"modules/cpviz/viz.js\"></script>\n";
    $html_txt .= "<script src=\"modules/cpviz/full.render.js\"></script>\n";
    $html_txt .= "<script src=\"modules/cpviz/html2canvas.js\"></script>\n";
		
    $html_txt .= "<input type=\"button\" id=\"download\" value=\"Export as $iroute.png\">\n";
    $html_txt .= "<br><br>\n";
    $html_txt .= "<div id='vizContainer'><h1>Dial Plan For Inbound Route ".formatPhoneNumber($iroute).": ".$dproute['description']."</h1></div>\n";
    $html_txt .= "<script type=\"text/javascript\">\n";
    $html_txt .= "    var viz = new Viz();\n";
    $html_txt .= " viz.renderSVGElement('$gtext')  \n";
    $html_txt .= "   .then(function(element) {                 \n";
    $html_txt .= "     document.getElementById(\"vizContainer\").appendChild(element);   \n";
    $html_txt .= "  });\n";
    $html_txt .= "document.getElementById(\"download\").addEventListener(\"click\", function() {
					html2canvas(document.querySelector('#vizContainer')).then(function(canvas) {
					saveAs(canvas.toDataURL(), '$iroute.png');
						});
					});
					function saveAs(uri, filename) {
						var link = document.createElement('a');
						if (typeof link.download === 'string') {
							link.href = uri;
							link.download = filename;
							//Firefox requires the link to be in the body
							document.body.appendChild(link);
							//simulate click
							link.click();
							//remove the link when done
							document.body.removeChild(link);
						} else {
							window.open(uri);
						}
					}\n";	
	$html_txt .= "</script>\n";
	if ($panchecked=='checked'){
		$html_txt .= "<script src=\"modules/cpviz/panzoom.min.js\"></script>\n";
		$html_txt .= "<script type=\"text/javascript\">\n";
		$html_txt .= "var element = document.querySelector('#graph0')\n";
		$html_txt .= "panzoom(element)\n";
		$html_txt .= "</script>\n";
	}
  }
}

echo $html_txt."</div>";

function formatPhoneNumber($phoneNumber) {
    $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber);

    if(strlen($phoneNumber) > 10) {
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);

        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if(strlen($phoneNumber) == 10) {
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);

        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }

    return $phoneNumber;
}
?>
