<?php 
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Log Level: 0 = total quiet, 9 = much verbose
$dp_log_level = 6;

// Set some colors
$pastels[] = "#7979FF";
$pastels[] = "#86BCFF";
$pastels[] = "#8ADCFF";
$pastels[] = "#3DE4FC";
$pastels[] = "#5FFEF7";
$pastels[] = "#33FDC0";
$pastels[] = "#ed9581";
$pastels[] = "#81a6a2";
$pastels[] = "#bae1e7";
$pastels[] = "#eb94e2";
$pastels[] = "#f8d580";
$pastels[] = "#979291";
$pastels[] = "#92b8ef";
$pastels[] = "#ad8086";


$neons[] = "#fe0000";
$neons[] = "#fdfe02";
$neons[] = "#0bff01";
$neons[] = "#011efe";
$neons[] = "#fe00f6";

function dp_load_incoming_routes() {
  global $db;

  $sql = "select * from incoming order by extension";
  $results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from incoming");       
  }

  // Store the routes in a hash indexed by the inbound number
  foreach($results as $route) {
    $num = $route['extension'];
    $routes[$num] = $route;
  }
  return $routes;
}

function dp_find_route($routes, $num) {

  $match = array();
  $pattern = '/[^+0-9]/';   # remove all non-digits
  $num =  preg_replace($pattern, '', $num);

  // "extension" is the key for the routes hash
  foreach ($routes as $ext => $route) {
    if ($ext == $num) {
      $match = $routes[$num];
    }
  }
  return $match;
}

#
# This is a recursive function.  It digs through various nodes
# (ring groups, ivrs, time conditions, extensions, etc.) to find
# the path a call takes.  It creates a graph of the path through
# the dial plan, stored in the $route object.
#
#
function dp_follow_destinations (&$route, $destination) {
  global $db;
  global $pastels;
  global $neons;

  if (! isset ($route['dpgraph'])) {
    $route['dpgraph'] = new Alom\Graphviz\Digraph($route['extension']);
  }
  $dpgraph = $route['dpgraph'];
  //dplog(9, "dpgraph: " . print_r($dpgraph, true));
  dplog(9, "destination='$destination' route[extension]: " . print_r($route['extension'], true));

  # This only happens on the first call.  Every recursive call includes
  # a destination to look at.  For the first one, we get the destination from
  # the route object.
  if ($destination == '') {
    $dpgraph->node($route['extension'],
      array('label' => $route['extension'],
				'shape' => 'cds',
        'style' => 'filled',
	'URL'=> htmlentities('/admin/config.php?display=did&view=form&extdisplay='.urlencode($route['extension']).'%2F'),
        'target'=>'_blank',
        'fillcolor' => 'darkseagreen')
			);
    // $graph->node() returns the graph, not the node, so we always
    // have to get() the node after adding to the graph if we want
    // to save it for something.
    // UPDATE: beginNode() creates a node and returns it instead of
    // returning the graph.  Similarly for edge() and beginEdge().
    $route['parent_node'] = $dpgraph->get($route['extension']);
    $route['parent_edge_label'] = ' Always';

    # One of thse should work to set the root node, but neither does.
    # See: https://rt.cpan.org/Public/Bug/Display.html?id=101437
    #$route->{parent_node}->set_attribute('root', 'true');
    #$dpgraph->set_attribute('root' => $route->{extension});

    // If an inbound route has no destination, we want to bail, otherwise recurse.
    if ($route['destination'] != '') {
      dp_follow_destinations($route, $route['destination']);
    }
    return;
  }

  dplog(9, "Inspecting destination $destination");

  // We use get() to see if the node exists before creating it.  get() throws
  // an exception if the node does not exist so we have to catch it.
  try {
    $node = $dpgraph->get($destination);
  } catch (Exception $e) {
    dplog(7, "Adding node: $destination");
    $node = $dpgraph->beginNode($destination);
  }
 
  // Add an edge from our parent to this node, if there is not already one.
  // We do this even if the node already existed because this node might
  // have several paths to reach it.
  $ptxt = $route['parent_node']->getAttribute('label', '');
  $ntxt = $node->getAttribute('label', '');
  dplog(9, "Found it: ntxt = $ntxt");
  if ($ntxt == '' ) { $ntxt = "(new node: $destination)"; }
  if ($dpgraph->hasEdge(array($route['parent_node'], $node))) {
    dplog(9, "NOT making an edge from $ptxt -> $ntxt");
  } else {
    dplog(9, "Making an edge from $ptxt -> $ntxt");
    $edge= $dpgraph->beginEdge(array($route['parent_node'], $node));
    $edge->attribute('label', $route['parent_edge_label']);
	if (preg_match("/^(Match:)./", $route['parent_edge_label'])){
		$edge->attribute('URL', $route['parent_edge_url']);
		$edge->attribute('target', $route['parent_edge_target']);
	}
  }

  // dplog(9, "The Graph: " . print_r($dpgraph, true));

  // Now bail if we have already recursed on this destination before.
  if ($node->getAttribute('label', 'NONE') != 'NONE') {
    return;
  }

  # Now look at the destination and figure out where to dig deeper.

  #
  # Time Conditions
  #
  if (preg_match("/^timeconditions,(\d+),(\d+)/", $destination, $matches)) {
    $tcnum = $matches[1];
    $tcother = $matches[2];

    $tc = $route['timeconditions'][$tcnum];
    $node->attribute('label', "TC: ".htmlspecialchars($tc['displayname'],ENT_QUOTES));
    $node->attribute('URL', htmlentities('/admin/config.php?display=timeconditions&view=form&itemid='.$tcnum));
    $node->attribute('target', '_blank');
    $node->attribute('shape', 'invhouse');
    $node->attribute('fillcolor', 'dodgerblue');
    $node->attribute('style', 'filled');

  
    # Not going to use the time group info for right now.  Maybe put it in the edge text?
    $tgname = $route['timegroups'][$tc['time']]['description'];
    $tgtime = $route['timegroups'][$tc['time']]['time'];
    $tgnum = $route['timegroups'][$tc['time']]['id'];

    # Now set the current node to be the parent and recurse on both the true and false branches
    $route['parent_edge_label'] = 'Match: \\n'.htmlspecialchars($tgname,ENT_QUOTES).':\\n'.$tgtime;
    $route['parent_edge_url'] = htmlentities('/admin/config.php?display=timegroups&view=form&extdisplay='.$tgnum);
    $route['parent_edge_target'] = '_blank';

    $route['parent_node'] = $node;
    dp_follow_destinations($route, $tc['truegoto']);


    $route['parent_edge_label'] = ' NoMatch';
    $route['parent_edge_url'] ='';
    $route['parent_edge_target'] = '';
    $route['parent_node'] = $node;
    dp_follow_destinations($route, $tc['falsegoto']);

  //
  // Queues
  //
  } elseif (preg_match("/^ext-queues,(\d+),(\d+)/", $destination, $matches)) {
    $qnum = $matches[1];
    $qother = $matches[2];

    $q = $route['queues'][$qnum];
    if ($q['maxwait'] == 0 || $q['maxwait'] == '' || !is_numeric($q['maxwait'])) {
 	$maxwait = 'Unlimited';
    } else {
  	$maxwait = secondsToTime($q['maxwait']);
    }
    $node->attribute('label', "Queue $qnum: ".htmlspecialchars($q['descr'],ENT_QUOTES));
    $node->attribute('URL', htmlentities('/admin/config.php?display=queues&view=form&extdisplay='.$qnum));
    $node->attribute('target', '_blank');
    $node->attribute('shape', 'hexagon');
    $node->attribute('fillcolor', 'mediumaquamarine');
    $node->attribute('style', 'filled');

    # The destinations we need to follow are the queue members (extensions)
    # and the no-answer destination.
    if ($q['dest'] != '') {
      $route['parent_edge_label'] = ' No Answer ('.$maxwait.')';
      $route['parent_node'] = $node;
      dp_follow_destinations($route, $q['dest']);
    }

    if (!empty($q['members'])){ksort($q['members']);
			foreach ($q['members'] as $member => $qstatus) {
				dplog(9, "queue member $member / $qstatus ...");
				if ($qstatus == 'static') {
					$route['parent_edge_label'] = ' Static Member';
				} else {
					$route['parent_edge_label'] = ' Dynamic Member';
				}
				$route['parent_node'] = $node;
				
				dp_follow_destinations($route, 'qmember'.$member);
				
			}
		}
		
  #
  # IVRs
  #
  } elseif (preg_match("/^ivr-(\d+),([a-z]+),(\d+)/", $destination, $matches)) {
    $inum = $matches[1];
    $iflag = $matches[2];
    $iother = $matches[3];

    $ivr = $route['ivrs'][$inum];
	  
    //feature code exist?
    if ( isset($route['featurecodes']['*29'.$ivr['announcement']]) ){
      //custom feature code?
      if ($route['featurecodes']['*29'.$ivr['announcement']]['customcode']!=''){$featurenum=$route['featurecodes']['*29'.$ivr['announcement']]['customcode'];}else{$featurenum=$route['featurecodes']['*29'.$ivr['announcement']]['defaultcode'];}
      //is it enabled?
      if ( ($route['recordings'][$ivr['announcement']]['fcode']== '1') && ($route['featurecodes']['*29'.$ivr['announcement']]['enabled']=='1') ){$rec='\\nRecord(yes): '.$featurenum;}else{$rec='\\nRecord(no): '.$featurenum;}
    }else{
      $rec='\\nRecord(no): disabled';
    }
	  
    $node->attribute('label', "IVR: ".htmlspecialchars($ivr['name'], ENT_QUOTES).$rec);
    $node->attribute('URL', htmlentities('/admin/config.php?display=ivr&action=edit&id='.$inum));
    $node->attribute('target', '_blank');
    $node->attribute('shape', 'component');
    $node->attribute('fillcolor', 'gold');
    $node->attribute('style', 'filled');

    # The destinations we need to follow are the invalid_destination,
    # timeout_destination, and the selection targets


  //are the invalid and timeout destinations the same?
  if ($ivr['invalid_destination']==$ivr['timeout_destination']){
     $route['parent_edge_label']= " Invalid Input, Timeout ($ivr[timeout_time] secs)";
     $route['parent_node'] = $node;
     dp_follow_destinations($route, $ivr['invalid_destination']);
  }else{
      if ($ivr['invalid_destination'] != '') {
        $route['parent_edge_label']= ' Invalid Input';
        $route['parent_node'] = $node;
        dp_follow_destinations($route, $ivr['invalid_destination']);
      }
      if ($ivr['timeout_destination'] != '') {
        $route['parent_edge_label']= " Timeout ($ivr[timeout_time] secs)";
        $route['parent_node'] = $node;
        dp_follow_destinations($route, $ivr['timeout_destination']);
      }
  }
  //now go through the selections
    if (!empty($ivr['entries'])){
      ksort($ivr['entries']);
      foreach ($ivr['entries'] as $selid => $ent) {
        dplog(9, "ivr member $selid / $ent ...");
		$route['parent_edge_label']= " Selection $ent[selection]";
        $route['parent_node'] = $node;
        dp_follow_destinations($route, $ent['dest']);
      }
    }

  #
  # Ring Groups
  #
  } elseif (preg_match("/^ext-group,(\d+),(\d+)/", $destination, $matches)) {
    $rgnum = $matches[1];
    $rgother = $matches[2];

    $rg = $route['ringgroups'][$rgnum];
    $node->attribute('label', "Ring Group: $rgnum: " .htmlspecialchars($rg['description'], ENT_QUOTES));
    $node->attribute('URL', htmlentities('/admin/config.php?display=ringgroups&view=form&extdisplay='.$rgnum));
    $node->attribute('target', '_blank');
    $node->attribute('fillcolor', $pastels[4]);
    $node->attribute('style', 'filled');

    # The destinations we need to follow are the no-answer destination
    # (postdest) and the members of the group.
    if ($rg['postdest'] != '') {
      $route['parent_edge_label'] = ' No Answer ('.secondsToTime($rg['grptime']).')';
      $route['parent_node'] = $node;
      dp_follow_destinations($route, $rg['postdest']);
    }

    if (isset($rg['members'])){
    ksort($rg['members']);
    foreach ($rg['members'] as $member => $name) {
      $route['parent_edge_label'] = ' RG Member';
      $route['parent_node'] = $node;
      if (preg_match("/^\d+/", $member)) {
				//$extname= 
				
        dp_follow_destinations($route, "Ext$member\\n$name");
      } elseif (preg_match("/#$/", $member)) {
        preg_replace("/[^0-9]/", '', $member);   // remove non-digits
        if (preg_match("/^(\d\d\d)(\d\d\d\d)$/", $member, $matches)) {
          $member = "$matches[1]-$matches[2]";
        } elseif (preg_match("/^(\d\d\d)(\d\d\d)(\d\d\d\d)$/", $member, $matches))  {
          $member = "$matches[1]-$matches[2]-$matches[3]";
        }
        dp_follow_destinations($route, "Callout $member");
      } else {
        dp_follow_destinations($route, "$member");
      }
    }  # end of ring groups
    }
  #
  # Announcements
  #
  } elseif (preg_match("/^app-announcement-(\d+),s,(\d+)/", $destination, $matches)) {
  $annum = $matches[1];
  $another = $matches[2];

  $an = $route['announcements'][$annum];

  //feature code exist?
  if ( isset($route['featurecodes']['*29'.$an['recording_id']]) ){
    //custom feature code?
    if ($route['featurecodes']['*29'.$an['recording_id']]['customcode']!=''){$featurenum=$route['featurecodes']['*29'.$an['recording_id']]['customcode'];}else{$featurenum=$route['featurecodes']['*29'.$an['recording_id']]['defaultcode'];}
    //is it enabled?
    if ( ($route['recordings'][$an['recording_id']]['fcode']== '1') && ($route['featurecodes']['*29'.$an['recording_id']]['enabled']=='1') ){$rec='\\nRecord(yes): '.$featurenum;}else{$rec='\\nRecord(no): '.$featurenum;}
  }else{
    $rec='\\nRecord(no): disabled';
  }

  $node->attribute('label', "Announcement: " .htmlspecialchars($an['description'], ENT_QUOTES).$rec);
  $node->attribute('URL', htmlentities('/admin/config.php?display=announcement&view=form&extdisplay='.$annum));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'note');
  $node->attribute('fillcolor', 'oldlace');
  $node->attribute('style', 'filled');

  # The destinations we need to follow are the no-answer destination
  # (postdest) and the members of the group.

  if ($an['post_dest'] != '') {
    $route['parent_edge_label'] = ' Continue';
    $route['parent_node'] = $node;
    dp_follow_destinations($route, $an['post_dest']);
  }

  # end of announcements

  #
  # Set CID
  #
  } elseif (preg_match("/^app-setcid,(\d+),(\d+)/", $destination, $matches)) {
  $cidnum = $matches[1];
  $cidother = $matches[2];

  $cid = $route['setcid'][$cidnum];
  $node->attribute('label', 'Set CID\nName= '.preg_replace('/\${CALLERID\(name\)}/i', '$cid_name', $cid['cid_name']).'\nNumber= '.preg_replace('/\${CALLERID\(num\)}/i', '$cid_number', $cid['cid_num']));
  $node->attribute('URL', htmlentities('/admin/config.php?display=setcid&view=form&id='.$cidnum));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'note');
  $node->attribute('fillcolor', $pastels[6]);
  $node->attribute('style', 'filled');

  if ($cid['dest'] != '') {
    $route['parent_edge_label'] = ' Continue';
    $route['parent_node'] = $node;
    dp_follow_destinations($route, $cid['dest']);
  }

  #end of Set CID
  
  #
  # Languages
  #
  } elseif (preg_match("/^app-languages,(\d+),(\d+)/", $destination, $matches)) {
  $langnum = $matches[1];
  $langother = $matches[2];

  $lang = $route['languages'][$langnum];
  $node->attribute('label', 'Languages: '.$lang['description']);
  $node->attribute('URL', htmlentities('/admin/config.php?display=languages&view=form&extdisplay='.$langnum));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'note');
  $node->attribute('fillcolor', $pastels[6]);
  $node->attribute('style', 'filled');

  if ($lang['dest'] != '') {
    $route['parent_edge_label'] = ' Continue';
    $route['parent_node'] = $node;
    dp_follow_destinations($route, $lang['dest']);
  }

  #end of Languages

  #
  # MISC Destinations
  #
  } elseif (preg_match("/^ext-miscdests,(\d+),(\d+)/", $destination, $matches)) {
  $miscdestnum = $matches[1];
  $miscdestother = $matches[2];

  $miscdest = $route['miscdest'][$miscdestnum];
  $node->attribute('label', "Misc Dest: " .htmlspecialchars($miscdest['description'],ENT_QUOTES)." ($miscdest[destdial])");
  $node->attribute('URL', htmlentities('/admin/config.php?display=miscdests&view=form&extdisplay='.$miscdestnum));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'rpromoter');
  $node->attribute('fillcolor', 'coral');
  $node->attribute('style', 'filled');

  #end of MISC Destinations

  #
  # Conferences (meetme)
  #
  } elseif (preg_match("/^ext-meetme,(\d+),(\d+)/", $destination, $matches)) {
  $meetmenum = $matches[1];
  $meetmeother = $matches[2];
  $meetme = $route['meetme'][$meetmenum];

  $node->attribute('label', 'Conference: '.$meetme['exten']);
  $node->attribute('URL', htmlentities('/admin/config.php?display=conferences&view=form&extdisplay='.$meetmenum));
  $node->attribute('target', '_blank');
  $node->attribute('fillcolor', 'burlywood');
  $node->attribute('style', 'filled');

  #end of Conferences (meetme)

  #
  # Directory
  #
  } elseif (preg_match("/^directory,(\d+),(\d+)/", $destination, $matches)) {
  $directorynum = $matches[1];
  $directoryother = $matches[2];
  $directory = $route['directory'][$directorynum];

  $node->attribute('label', htmlspecialchars($directory['dirname'],ENT_QUOTES));
  $node->attribute('URL', htmlentities('/admin/config.php?display=directory&view=form&id='.$directorynum));
  $node->attribute('target', '_blank');
  $node->attribute('fillcolor', $pastels[9]);
  $node->attribute('style', 'filled');

  #end of Directory

  #
  # DISA
  #
  } elseif (preg_match("/^disa,(\d+),(\d+)/", $destination, $matches)) {
  $disanum = $matches[1];
  $disaother = $matches[2];
  $disa = $route['disa'][$disanum];

  $node->attribute('label', 'DISA: '.htmlspecialchars($disa['displayname'],ENT_QUOTES));
  $node->attribute('URL', htmlentities('/admin/config.php?display=disa&view=form&itemid='.$disanum));
  $node->attribute('target', '_blank');
  $node->attribute('fillcolor', $pastels[10]);
  $node->attribute('style', 'filled');

  #end of DISA

  #
  # Voicemail
  #
  } elseif (preg_match("/^ext-local,vm([b,i,s,u])(\d+),(\d+)/", $destination, $matches)) {
  $vmtype= $matches[1];
  $vmnum = $matches[2];
  $vmother = $matches[3];
  
  $vm_array=array('b'=>'(Busy Message)','i'=>'(Instructions Only)','s'=>'(No Message)','u'=>'(Unavailable Message)' );
  $emailadd= $route['extensions'][$vmnum]['name'].'\\n'.$route['extensions'][$vmnum]['email'];
 
  $node->attribute('label', 'Voicemail: '.$vmnum.': '.$emailadd.'\\n'.$vm_array[$vmtype]);
  $node->attribute('label', 'Voicemail: '.$vmnum.' '.$vm_array[$vmtype]);
  $node->attribute('URL', htmlentities('/admin/config.php?display=extensions&extdisplay='.$vmnum));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'house');
  $node->attribute('fillcolor', $pastels[11]);
  $node->attribute('style', 'filled');

  #end of Voicemail

  #
  # Extension (from-did-direct)
  #
  } elseif (preg_match("/^from-did-direct,(\d+),(\d+)/", $destination, $matches)) {
  $extnum = $matches[1];
	$extother = $matches[2];
  $ext = $route['vm'][$vmnum];
  
  $node->attribute('label', 'Extension: '.$extnum);
  $node->attribute('URL', htmlentities('/admin/config.php?display=extensions&extdisplay='.$extnum));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'house');
  $node->attribute('fillcolor', $pastels[14]);
  $node->attribute('style', 'filled');

  #end of Extension (from-did-direct)

  #
  # Call Flow Control (daynight)
  #
  } elseif (preg_match("/^app-daynight,(\d+),(\d+)/", $destination, $matches)) {
    $daynightnum = $matches[1];
    $daynightother = $matches[2];
    $daynight = $route['daynight'][$daynightnum];
    
    //feature code exist?
    if ( isset($route['featurecodes']['*28'.$daynightnum]) ){
      //custom feature code?
      if ($route['featurecodes']['*28'.$daynightnum]['customcode']!=''){$featurenum=$route['featurecodes']['*28'.$daynightnum]['customcode'];}else{$featurenum=$route['featurecodes']['*28'.$daynightnum]['defaultcode'];}
      //is it enabled?
      if ($route['featurecodes']['*28'.$daynightnum]['enabled']=='1'){$code='\\nToggle (enabled): '.$featurenum;}else{$code='\\nToggle (disabled): '.$featurenum;}
    }else{
      $code='';
    }
	  
    //check current status and set path to active
    $C = '/usr/sbin/asterisk -rx "database show DAYNIGHT/C'.$daynightnum.'" | cut -d \':\' -f2 | tr -d \' \' | head -1';
    exec($C, $current_daynight);
    $dactive = $nactive = "";
    if ($current_daynight[0]=='DAY'){$dactive="(Active)";}else{$nactive="(Active)";}

    foreach ($daynight as $d){
      if ($d['dmode']=='day'){
	 $route['parent_edge_label'] = ' Day Mode '.$dactive;
         $route['parent_node'] = $node;
         dp_follow_destinations($route, $d['dest']);
      }elseif ($d['dmode']=='night'){
          $route['parent_edge_label'] = ' Night Mode '.$nactive;
          $route['parent_node'] = $node;
          dp_follow_destinations($route, $d['dest']);
      }elseif ($d['dmode']=="fc_description"){
           $node->attribute('label', "Call Flow: ".htmlspecialchars($d['dest'],ENT_QUOTES) .$code);
      }
    }
    $daynight = $route['daynight'][$daynightnum];
    $node->attribute('URL', htmlentities('/admin/config.php?display=daynight&view=form&itemid='.$daynightnum.'&extdisplay='.$daynightnum));
    $node->attribute('target', '_blank');
    $node->attribute('fillcolor', $pastels[14]);
    $node->attribute('style', 'filled');

  #end of Call Flow Control (daynight)
  
  #
  # Feature Codes
  #
  } elseif (preg_match("/^ext-featurecodes,(\*?\d+),(\d+)/", $destination, $matches)) {
  $featurenum = $matches[1];
  $featureother = $matches[2];
  $feature = $route['featurecodes'][$featurenum];
  
  if ($feature['customcode']!=''){$featurenum=$feature['customcode'];}
  $node->attribute('label', 'Feature Code: '.htmlspecialchars($feature['description'],ENT_QUOTES).' <'.$featurenum.'>');
  $node->attribute('URL', htmlentities('/admin/config.php?display=featurecodeadmin'));
  $node->attribute('target', '_blank');
  $node->attribute('shape', 'folder');
  $node->attribute('fillcolor', 'gainsboro');
  $node->attribute('style', 'filled');

  #end of Feature Codes
  
  #
  # Blackhole
  #
  } elseif (preg_match("/^app-blackhole,(hangup|congestion|busy|zapateller|musiconhold|ring|no-service),(\d+)/", $destination, $matches)) {
  $blackholetype = str_replace('musiconhold','Music On Hold',$matches[1]);
  $blackholeother = $matches[2];
  
  $node->attribute('label', 'Terminate Call: '.ucwords($blackholetype,'-'));
  $node->attribute('shape', 'invhouse');
  $node->attribute('fillcolor', 'orangered');
  $node->attribute('style', 'filled');

  #end of Blackhole

  //preg_match not found
  } elseif (preg_match("/^qmember(Ext(\d+).+)/", $destination, $matches)) {
		$qlabel=$matches[1];
		if (isset($u[$dest]['name']) && $u[$dest]['name']!=''){echo $u[$dest]['name'];}
		$qextension=$matches[2];
		$node->attribute('label', $qlabel);
		$node->attribute('URL', htmlentities('/admin/config.php?display=extensions&extdisplay='.$qextension));
		$node->attribute('target', '_blank');
		$node->attribute('style', 'filled');
		
		
	}	else {
    dplog(1, "Unknown destination type: $destination");
    if ($route['parent_edge_label'] == ' Dynamic Member') {
      $node->attribute('fillcolor', $neons[1]);
    } else {
      $node->attribute('fillcolor', $pastels[12]);
    }
    $node->attribute('label', htmlspecialchars($destination,ENT_QUOTES));
    $node->attribute('style', 'filled');
  }

}


# load gobs of data.  Save it in hashrefs indexed by ints
function dp_load_tables(&$dproute) {
  global $db;

  # Time Conditions
  $query = "select * from timeconditions";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from timeconditions");       
  }
  foreach($results as $tc) {
    $id = $tc['timeconditions_id'];
    $dproute['timeconditions'][$id] = $tc;
  }

  # Time Groups
  $query = "select * from timegroups_groups";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from timegroups_groups");
  }
  foreach($results as $tg) {
    $id = $tg['id'];
    $dproute['timegroups'][$id] = $tg;
  }

  # Time Groups Details
  $query = "select * from timegroups_details";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from timegroups_details");       
  }
  foreach($results as $tgd) {
    $id = $tgd['timegroupid'];
    if (! isset($dproute['timegroups'][$id])) {
      dplog(1, "timegroups_details id found for unknown timegroup, id=$id");
    } else {
      $exploded=explode("|",$tgd['time']); 
      if ($exploded[0]!=='*'){$time=$exploded[0];}else{$time='';}
      if ($exploded[1]!=='*'){$dow=ucwords($exploded[1],'-').', ';}else{$dow='';}
      if ($exploded[2]!=='*'){$date=$exploded[2].' ';}else{$date='';}
      if ($exploded[3]!=='*'){$month=ucfirst($exploded[3]).' ';}else{$month='';}

      $dproute['timegroups'][$id]['time'] .=$dow . $month . $date . $time.'\l';
      $dproute['timegroups'][$id]['time'] .= "\n";
    }
  }

	# Users
  $query = "select * from users";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from users");
  }
	
  foreach($results as $users) {
    $id = $users['extension'];
    $u[$id]= $users;
    $dproute['extensions'][$id]= $users;
  }

# Userman
  $query = "select * from userman_users";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from userman_users");
  }
	
  foreach($results as $userman) {
	  
	  $id = $userman['username'];
	  if ($userman['email']!=''){
		  $dproute['extensions'][$id]['email'] = $userman['email'];
	  }else{
		  $dproute['extensions'][$id]['email'] = 'unassigned';
	  }
  }
	
  # Queues
  $query = "select * from queues_config";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from timegroups_details");       
  }
  foreach($results as $q) {
    $id = $q['extension'];
    $dproute['queues'][$id] = $q;
  }
	

  # Queue members
  $query = "select * from queues_details";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from queues_details");       
  }
  foreach($results as $qd) {
    $id = $qd['id'];
    if ($qd['keyword'] == 'member') {
      $member = $qd['data'];
      if (preg_match("/Local\/(\d+)/", $member, $matches)) {
        $enum = $matches[1];
				//$name_ext='Ext'.$enum.'\\n'.$u[$enum]['name'];
				$name_ext= htmlspecialchars('Ext'.$enum.'\\n'.$u[$enum]['name'],ENT_QUOTES);
				$dproute['queues'][$id]['members'][$name_ext] = 'static';
      }
    }
  }
	
  # IVRs
  $query = "select * from ivr_details";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from ivr_details");       
  }
  foreach($results as $ivr) {
    $id = $ivr['id'];
    $dproute['ivrs'][$id] = $ivr;
  }

  # IVR entries
  $query = "select * from ivr_entries";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from ivr_entries");       
  }
  foreach($results as $ent) {
    $id = $ent['ivr_id'];
    $selid = $ent['selection'];
    dplog(9, "entry:  ivr=$id   selid=$selid");
    $dproute['ivrs'][$id]['entries'][$selid] = $ent;
  }

  # Ring Groups
  $query = "select * from ringgroups";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from ringgroups");       
  }
  foreach($results as $rg) {
    $id = $rg['grpnum'];
    $dproute['ringgroups'][$id] = $rg;
    $dests = preg_split("/-/", $rg['grplist']);
    foreach ($dests as $dest) {
      dplog(9, "rg dest:  rg=$id   dest=$dest");
      $dproute['ringgroups'][$id]['members'][$dest] = $u[$dest]['name'];
    }
  }

  # Announcements
  $query = "select * from announcement";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from announcement");       
  }
  foreach($results as $an) {
    $id = $an['announcement_id'];
    $dproute['announcements'][$id] = $an;
    $dest = $an['post_dest'];
    dplog(9, "an dest:  an=$id   dest=$dest");
    $dproute['announcements'][$id]['dest'] = $dest;
  }

  # Set Caller ID
  $query = "select * from setcid";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from setcid");
  }
  foreach($results as $cid) {
    $id = $cid['cid_id'];
    $dproute['setcid'][$id] = $cid;
  }

  # Misc Destinations
  $query = "select * from miscdests";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from misc destinations");
  }
  foreach($results as $miscdest) {
    $id = $miscdest['id'];
    $dproute['miscdest'][$id] = $miscdest;
    dplog(9, "miscdest dest: $id");
  }

  # Conferences (meetme)
  $query = "select * from meetme";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from meetme (conferences)");
  }
  foreach($results as $meetme) {
    $id = $meetme['exten'];
    $dproute['meetme'][$id] = $meetme;
    dplog(9, "meetme dest:  conf=$id");
  }

  # Directory
  $query = "select * from directory_details";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from directory");
  }
  foreach($results as $directory) {
    $id = $directory['id'];
    $dproute['directory'][$id] = $directory;
    dplog(9, "directory=$id");
  }

  # DISA
  $query = "select * from disa";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from disa");
  }
  foreach($results as $disa) {
    $id = $disa['disa_id'];
    $dproute['disa'][$id] = $disa;
    dplog(9, "disa=$id");
  }

  # Call Flow Control (day/night)
  $query = "select * from daynight";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from daynight");
  }
  foreach($results as $daynight) {
    $id = $daynight['ext'];
    $dproute['daynight'][$id][] = $daynight;
  }
  
  # Feature Codes
  $query = "select * from featurecodes";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from featurecodes");
  }
  foreach($results as $featurecodes) {
	$id=$featurecodes['defaultcode'];
    $dproute['featurecodes'][$id] = $featurecodes;
  }

  # Recordings
  $query = "select * from recordings";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from featurecodes");
  }
  foreach($results as $recordings) {
	$id=$recordings['id'];
    $dproute['recordings'][$id] = $recordings;
  }
	
  # Languages
  $query = "select * from languages";
  $results = $db->getAll($query, DB_FETCHMODE_ASSOC);
  if (DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from languages");
  }
  foreach($results as $languages) {
	$id=$languages['language_id'];
    $dproute['languages'][$id] = $languages;
  }
}


function dplog($level, $msg) {
  global $dp_log_level;

  if ($dp_log_level < $level) {
    return;
  }

  $ts = date('m-d-Y H:i:s');
  if(! $fd = fopen("/tmp/dpviz.log", "a")) {
    print "Couldn't open log file.";
    exit;
  }
  fwrite($fd, $ts . "  " . $msg . "\n");
  fclose($fd);
  return;
}

function secondsToTime($seconds){
  $seconds = (int) $seconds;
  
  $hours = floor($seconds / 3600);
  $minutes = floor(($seconds / 60) % 60);
  $seconds = $seconds % 60;
  
  return $hours > 0 ? "$hours hrs, $minutes mins" : 
         ($minutes > 0 ? "$minutes mins, $seconds secs" : "$seconds secs");
}

?>
