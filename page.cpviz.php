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
$scale= isset($options[0]['scale']) ? $options[0]['scale'] : '1';
$dynmembers= isset($options[0]['dynmembers']) ? $options[0]['dynmembers'] : '0';
$direction=($horizontal== 1) ? 'LR' : 'TB';

?>
<div class="container-fluid">
	<div class="display full-border">
		<h1><?php echo _("Dial Plan Vizualizer"); ?></h1>
	</div>
	<?php require('views/options.php');
	
	$inroutes = dp_load_incoming_routes();
	//echo "<pre>" . "FreePBX config data:\n" . print_r($inroutes, true) . "</pre><br>";

	if (isset($_GET['extdisplay'])) {
		$dproute = dp_find_route($inroutes, $iroute);
		
		if (empty($dproute)) {
			echo "<h2>Error: Could not find inbound route for '$iroute'</h2>";
		} else {
			$filename = ($iroute == '') ? 'ANY.png' : $iroute.'.png';
			echo '<p><button class="btn btn-primary" onclick="location.reload();">Reload Page</button><input type="button" id="download" value="Export as ' . $filename . '"><button type="button" id="focus" class="btn btn-default">Highlight Paths</button></p>';
			dp_load_tables($dproute);   # adds data for time conditions, IVRs, etc.
			//echo "<pre>" . "FreePBX config data:\n" . print_r($dproute, true) . "</pre><br>";

			
			dplog(5, "Doing follow dest ...");
			dp_follow_destinations($dproute, '');
			dplog(5, "Finished follow dest ...");
			
			$gtext = $dproute['dpgraph']->render();
		
			dplog(5, "Dial Plan Graph for $extdisplay $cid:\n$gtext");
			
			$gtext = str_replace(["\n","+"], ["\\n","\\+"], $gtext);  // ugh, apparently viz chokes on newlines, wtf?
			
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
				let isFocused = false;
				let svgContainer = null;
				let selectedNodeId = null;
				let originalLinks = new Map();
				let highlightedEdges = new Set(); // Track highlighted edges

				viz.renderSVGElement('<?php echo $gtext; ?>')
				.then(function(element) {
					svgContainer = element;
					document.getElementById("vizContainer").appendChild(element);
					
					// Add click event for nodes - only activates in focus mode
					element.querySelectorAll("g.node").forEach(node => {
						node.addEventListener("click", function(e) {
							// Only handle node clicks for path highlighting if in focus mode
							if (isFocused) {
								selectedNodeId = this.id;
								highlightPathToNode(this.id);
								
								// Prevent default navigation when in focus mode
								e.preventDefault();
								e.stopPropagation();
								return false;
							}
							// Otherwise let default behavior happen (follow links)
						});
					});
					
					// Add click event for edges - only activates in focus mode
					element.querySelectorAll("g.edge").forEach(edge => {
						edge.addEventListener("click", function(e) {
							// Only handle edge clicks for path highlighting if in focus mode
							if (isFocused) {
								// Toggle highlight for this edge
								toggleEdgeHighlight(this.id);
								
								// Prevent default behavior
								e.preventDefault();
								e.stopPropagation();
								return false;
							}
						});
					});
				});
				
				// Use the most reliable way to prevent default for focus button
				document.getElementById("focus").addEventListener("click", function(e) {
					// Stop the event from bubbling up
					e.stopPropagation();
					// Prevent the default action
					e.preventDefault();
					
					// Toggle focus mode
					toggleFocusMode();
					
					// Return false for extra measure
					return false;
				}, false);
				
				function toggleEdgeHighlight(edgeId) {
					if (!svgContainer) return;
					
					const edge = document.getElementById(edgeId);
					if (!edge) return;
					
					// Check if this edge is already highlighted
					if (highlightedEdges.has(edgeId)) {
						// Remove highlight
						highlightedEdges.delete(edgeId);
						
						// Reset edge style
						const edgePath = edge.querySelector("path");
						if (edgePath) {
							edgePath.style.stroke = "";
							edgePath.style.strokeWidth = "";
						}
						
						// Reset arrowhead
						const polygon = edge.querySelector("polygon");
						if (polygon) {
							polygon.style.fill = "";
							polygon.style.stroke = "";
						}
						
						// Reset edge text
						const textElements = edge.querySelectorAll("text");
						textElements.forEach(text => {
							text.style.fill = "";
							text.style.fontWeight = "";
						});
					} else {
						// Add highlight
						highlightedEdges.add(edgeId);
						
						// Highlight edge
						const edgePath = edge.querySelector("path");
						if (edgePath) {
							edgePath.style.stroke = "red";
							edgePath.style.strokeWidth = "3px";
						}
						
						// Highlight arrowhead
						const polygon = edge.querySelector("polygon");
						if (polygon) {
							polygon.style.fill = "red";
							polygon.style.stroke = "red";
						}
						
						// Highlight edge text
						const textElements = edge.querySelectorAll("text");
						textElements.forEach(text => {
							text.style.fill = "red";
							text.style.fontWeight = "bold";
						});
					}
				}
				
				function resetEdges() {
					if (!svgContainer) return;
					
					// Clear highlighted edges set
					highlightedEdges.clear();
					
					// Reset only edge paths
					svgContainer.querySelectorAll("g.edge path").forEach(path => {
						path.style.stroke = "";
						path.style.strokeWidth = "";
					});
					
					// Reset only arrowheads in edges
					svgContainer.querySelectorAll("g.edge polygon").forEach(polygon => {
						polygon.style.fill = "";
						polygon.style.stroke = "";
					});
					
					// Reset edge text (labels)
					svgContainer.querySelectorAll("g.edge text").forEach(text => {
						text.style.fill = "";
						text.style.fontWeight = "";
					});
				}
				
				function toggleFocusMode() {
					if (!svgContainer) return;

					if (isFocused) {
						// Exit focus mode
						resetEdges();
						restoreLinks();
						isFocused = false;
						document.getElementById("focus").textContent = "Highlight Paths";
						document.getElementById("focus").classList.remove("active");
						//document.getElementById("focus").classList.remove("btn-primary");
						document.getElementById("focus").classList.add("btn-default");
					} else {
						// Enter focus mode
						disableLinks();
						isFocused = true;
						document.getElementById("focus").textContent = "Remove Highlights";
						document.getElementById("focus").classList.add("active");
						document.getElementById("focus").classList.remove("btn-default");
						//document.getElementById("focus").classList.add("btn-primary");
					}
				}
				
				function disableLinks() {
					if (!svgContainer) return;
					
					// Block all node clicks to their URL destinations
					svgContainer.querySelectorAll("g.node a").forEach(link => {
						if (link.hasAttribute("xlink:href")) {
							originalLinks.set(link, link.getAttribute("xlink:href"));
							link.setAttribute("xlink:href", "javascript:void(0);");
						}
					});
				}
				
				function restoreLinks() {
					if (!svgContainer) return;
					
					// Restore original hrefs
					svgContainer.querySelectorAll("g.node a").forEach(link => {
						const originalHref = originalLinks.get(link);
						if (originalHref) {
							link.setAttribute("xlink:href", originalHref);
						}
					});
					
					// Clear stored links
					originalLinks.clear();
				}
				
				function highlightPathToNode(nodeId) {
					if (!svgContainer) return;
					
					// First reset all edges
					resetEdges();
					
					// Get the title content of the node to find its name
					const node = document.getElementById(nodeId);
					if (!node) return;
					
					const nodeTitle = node.querySelector("title");
					if (!nodeTitle) return;
					
					const targetNodeName = nodeTitle.textContent;
					
					// Track all nodes that are part of the path
					const visitedNodes = new Set([targetNodeName]);
					// Track all edges we've processed to avoid duplicates
					const processedEdges = new Set();
					
					// Recursively find all nodes that lead to our target
					function findConnectedNodes(nodeName) {
						svgContainer.querySelectorAll("g.edge").forEach(edge => {
							// Skip edges we've already processed
							if (processedEdges.has(edge.id)) return;
							
							const edgeTitle = edge.querySelector("title");
							if (!edgeTitle || !edgeTitle.textContent.includes("->")) return;
							
							const [sourceNode, destNode] = edgeTitle.textContent.split("->");
							
							// If this edge points to our node, highlight it regardless of whether we've visited the source
							if (destNode.trim() === nodeName) {
								// Mark this edge as processed
								processedEdges.add(edge.id);
								
								// Add the source to our visited set
								const sourceNodeName = sourceNode.trim();
								visitedNodes.add(sourceNodeName);
								
								// Highlight this edge
								const edgePath = edge.querySelector("path");
								if (edgePath) {
									edgePath.style.stroke = "red";
									edgePath.style.strokeWidth = "3px";
								}
								
								// Highlight arrowhead
								const polygon = edge.querySelector("polygon");
								if (polygon) {
									polygon.style.fill = "red";
									polygon.style.stroke = "red";
								}
								
								// Highlight edge text (labels)
								const textElements = edge.querySelectorAll("text");
								textElements.forEach(text => {
									text.style.fill = "red";
									text.style.fontWeight = "bold";
								});
								
								// Recursively find nodes that lead to this source
								findConnectedNodes(sourceNodeName);
							}
						});
					}
					
					// Start the recursive search from our target node
					findConnectedNodes(targetNodeName);
				}

				document.getElementById("download").addEventListener("click", function() {
						html2canvas(document.querySelector('#vizContainer'), {
								scale: <?php echo $scale; ?>,
								useCORS: true,
								allowTaint: true
						}).then(function(canvas) {
								let imgData = canvas.toDataURL("image/png");
								saveAs(imgData, "<?php echo $filename; ?>");
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
						document.addEventListener("DOMContentLoaded", function() {
								var element = document.querySelector('#graph0');
								if (element) {
										panzoom(element);
								}
						});
				</script>
			<?php }
		}
	}
	?>
</div>