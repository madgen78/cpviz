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
$cid = isset($_REQUEST['cid']) ? $_REQUEST['cid'] : '';
$iroute=$extdisplay.$cid;

//options
$options=options_get();
$datetime=$options[0]['datetime'];
$datetime = isset($options[0]['datetime']) ? $options[0]['datetime'] : '1';
$horizontal = isset($options[0]['horizontal']) ? $options[0]['horizontal'] : '0';
$panzoom = isset($options[0]['panzoom']) ? $options[0]['panzoom'] : '0';
$destinationColumn= isset($options[0]['destination']) ? $options[0]['destination'] : '0';

$direction=($horizontal== 1) ? 'LR' : 'TB';

?>
<div class="container-fluid">
	<div class="display full-border">
		<h1><?php echo _("Dial Plan Vizualizer"); ?></h1>
	</div>
	<?php require('views/options.php');
	
	$inroutes = dp_load_incoming_routes();
	//echo "<pre>" . "FreePBX config data:\n" . print_r($inroutes, true) . "</pre><br>";

	echo ($iroute!='') ? '<p><button class="btn btn-primary" onclick="location.reload();">Reload Page</button><input type="button" id="download" value="Export as ' . $iroute . '.png"></p>' : '';

	if ($iroute != '') {
		$dproute = dp_find_route($inroutes, $iroute);
		
		if (empty($dproute)) {
			echo "<h2>Error: Could not find inbound route for '$iroute'</h2>";
		} else {
			dp_load_tables($dproute);   # adds data for time conditions, IVRs, etc.
			//echo "<pre>" . "FreePBX config data:\n" . print_r($dproute, true) . "</pre><br>";

			dplog(5, "Doing follow dest ...");
			dp_follow_destinations($dproute, '');
			dplog(5, "Finished follow dest ...");
			
			$gtext = $dproute['dpgraph']->attr('graph',array('rankdir'=>$direction));
			$gtext = $dproute['dpgraph']->render();
		
			dplog(5, "Dial Plan Graph for $extdisplay $cid:\n$gtext");
			
			$gtext = str_replace(["\n", "+"], ["\\n", "\+"], $gtext);  // ugh, apparently viz chokes on newlines and +, wtf?
			
			?>
			<div class="fpbx-container">
				<div id="vizContainer" class="display full-border">
					<h2>Dial Plan For Inbound Route <?php echo formatPhoneNumber($extdisplay); if (!empty($cid)){echo ' / '.formatPhoneNumber($cid);} echo ': '.$dproute['description']; ?></h2>
					<?php if ($datetime==1){echo "<h6>".date('Y-m-d H:i:s')."</h6>";} ?>
				</div>
			</div>
			<script src="modules/cpviz/assets/js/viz.min.js"></script>
			<script src="modules/cpviz/assets/js/full.render.js"></script>
			<script src="modules/cpviz/assets/js/html2canvas.min.js"></script>
			<script type="text/javascript">
				var viz = new Viz();
				viz.renderSVGElement('<?php echo $gtext; ?>')
				.then(function(element) {
					document.getElementById("vizContainer").appendChild(element);
				});
				document.getElementById("download").addEventListener("click", function() {
						html2canvas(document.querySelector('#vizContainer'), {
								scale: 3,
								useCORS: true,
								allowTaint: true
						}).then(function(canvas) {
								let imgData = canvas.toDataURL("image/png");
								saveAs(imgData, "<?php echo $iroute.'.png'; ?>");
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
				}
				
			</script>
			<?php
			if ($panzoom==1){ ?>
				<script src="modules/cpviz/assets/js/panzoom.min.js"></script>
				<script type="text/javascript">
					var element = document.querySelector('#graph0')
					panzoom(element)
				</script>
			<?php }
		}
	}
	?>
</div>
