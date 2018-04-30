<?php
/*
 * OpenCaps
 * http://opencaps.atrc.utoronto.ca
 * 
 * Copyright 2009 Heidi Hazelton
 * Adaptive Technology Resource Centre, University of Toronto
 * 
 * Licensed under the Educational Community License (ECL), Version 2.0. 
 * You may not use this file except in compliance with this License.
 * http://www.opensource.org/licenses/ecl2.php
 * 
 */

define('INCLUDE_PATH', 'include/');
require(INCLUDE_PATH.'vitals.inc.php');


//remote system request - send captions back
if (isset($_GET['id'])) {
	
	$this_proj->id = $_GET['id'];
	$ref = getenv("HTTP_REFERER");
	$count = 0;
	
	foreach ($remote_systems as $r) {
		if (strcmp($r['url'], $ref)) {
			$rid = $count;
			break;
		}	
		$count++;
	}
	
	$_SESSION['rid'] = $rid;
	$_SESSION['pid'] = $this_proj->id;
 	$this_proj->openRemote($this_proj->id, true);

 	
//basic request - normal export
/*} else if (isset($_GET['url']) && !empty($_GET['url'])) {
	//make sure legit URL
	$this_ext = explode(".", $_GET['url']);
	$this_ext = end($this_ext);
			
	if (!@file_get_contents($_GET['url']) || !in_array($this_ext, $supported_ext) ) {
		$_SESSION['errors'][] = "Invalid URL. Make sure the URL is correct and that the media file is a supported format.";			
	} else {
		//login as guest
		$this_user->login('guest', 'guest');

		
	}	
	$this_proj->openDirect($_GET['url'], $_GET['captions']);	
	*/
} else {
	header("Location:start.php");
	exit;
}

?>