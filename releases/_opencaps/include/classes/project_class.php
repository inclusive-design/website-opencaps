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

class project {
	//public $owner;
	
	public $id;
	public $name;
	//public $prefs;

	public $media_loc; //url to media file
	public $media_height;
	public $media_width;
	public $duration;
	
	public $caption_loc; //url to caption json file
	public $clip_collection;  // holds everything: captions, descriptions, etc.

	public $layout;
	
	/* new project object belonging to member */
	function __construct() {
		$this->clip_collection = new clipCollection();	
	}
	
	/* create a new project */
	function createNew($name, $media, $captions) {	
		global $this_db;
		
		$this->name = $name;
		$this->layout = 0;
				
		//enter into db	
		$this->id = $this_db->addProj($this);
		
		if (isset($_SESSION['errors'])) {
			header("Location:start.php");
			exit;
		}
		
		$_SESSION['pid'] = $this->id; 		
		
		//make project folder
		if (!file_exists('projects/'.$this->id.'/')) {
			@mkdir('projects/'.$this->id.'/');
			@copy('projects/index.html', 'projects/'.$this->id.'/index.html');
		}		
		
		// if uploaded
		if (is_array($media)) {	
			$this->media_loc = 'projects/'.$this->id.'/'.$media['name'];
				
			if ( !move_uploaded_file($media['tmp_name'], 'projects/'.$this->id.'/'.basename($media['name'])) ) {
				$_SESSION['errors'][] = "Problem uploading media file - can't copy file to server. The file may exceed file size limits. Try a smaller file or contact your server administrator.";
				
				$this->delete($this->id);
				return;				
			}
			
		// if URL	
		} else {
			$this->media_loc = $media;	
		}
						
		//convert caption file to quicktime &  save
		if (!empty($captions['tmp_name'])) {					
			$this->importCaptions($captions);	
		}
					
		//save reference file
		$json = json_encode(get_object_vars($this));		
		$this->saveJson($json, $this->id);				
		$this_db->updateProj($this);
		
		return $this->id;
	}
	
	/* open user project */
	function open($pid) {	
		global $this_db;	
		$sql = "UPDATE projects SET last_accessed=NOW() WHERE project_id=$pid AND member_id=$_SESSION[mid]";
		if (!$result = mysql_query($sql, $this_db->db)) {
			echo 'Database error: '.mysql_error();
			exit;	
		}
		
		$_SESSION['pid'] = $pid;	
	}			

	/* open remote media (MH) */
	function openRemote($id, $direct=false) {
		global $remote_systems, $supported_ext;

		$this->id = $id;
		$_SESSION['pid'] = $id;
		
		$rid = $_SESSION['rid'];
		$uri = '/opencaps/rest/'.$this->id;
		$rm = json_decode(matterhornAuth($rid, $uri));
		
		if (!empty($rm)) {
			
			$ext = explode(".", $rm->mediaURL);
			$ext = end($ext);
	
			/* error checking */
			if (!in_array($ext, $supported_ext)) {
				$_SESSION['errors'][] = "Media file format not supported. Your Matterhorn administrator must include a Quicktime-readable format in its encoding profile.";
				
				if ($direct) {
					include(INCLUDE_PATH."basic_header.inc.php");
					include(INCLUDE_PATH."footer.inc.php");
					exit;
				}	
				return;
			}			
			
			$this->name = $rm->title;
			$this->media_loc = $rm->mediaURL;
			$this->clip_collection = new clipCollection();	
						
			//save reference file
			$json = json_encode(get_object_vars($this));
			$this->saveJson($json, $this->id);
			
		} else {
			$_SESSION['errors'][] = "Could not open project - no response from server.";
			if ($direct) {
				include(INCLUDE_PATH."basic_header.inc.php");
				include(INCLUDE_PATH."footer.inc.php");
				exit;
			}	
			return;
		}	
		
		$_SESSION['valid_user'] = true;

		if ($direct) {			
			$_SESSION['mid'] = '99999';					
			header('Location:editor.php');
			exit;
		}
		
		return;
				
	}
	
	
	/* save uploaded media - returns location of file */
	function saveMedia($upload) {
		$dir = 'projects';
		if (!move_uploaded_file($file['tmp_name'], $dir.'/'.basename($file['name'])) ) {
			//$err = "Problem uploading file - can't copy file to server. The file may exceed file size limits. Try a smaller file or contact your server administrator.";
		}
	}
	
	/* list the current user's projects */
	function printUserProjects($pageNum) {
		global $this_db, $stripslashes, $addslashes;
		
		if (!isset($pageNum))
			$pageNum = 1;
				
		$projsPerPage = 20;
			
		//printing page numbers
		$sql  = "SELECT project_id FROM projects WHERE member_id=".intval($_SESSION['mid']);
		$result  = @mysql_query($sql, $this_db->db);
		$numrows = @mysql_num_rows($result);
			
		$maxPage = ceil($numrows/$projsPerPage);
			
			$nav  = 'Page: ';		
			for($page = 1; $page <= $maxPage; $page++) {
			   if ($page == $pageNum) {
			      $nav .= " $page "; 
			   } else {
			      $nav .= ' <a href="start.php?page='.$page.'">'.$page.'</a> ';
			   }
			}		
			
			if ($maxPage > 1)
				echo $nav."<br /><br />";
			
			$offset = ($pageNum - 1) * $projsPerPage;
			$sql = "SELECT * FROM projects WHERE member_id=".intval($_SESSION['mid'])." ORDER BY last_accessed DESC LIMIT $offset, $projsPerPage";
			$result = @mysql_query($sql, $this_db->db);		
			
		
		if ($numrows == 0) {
			echo "No projects yet.";
		} else {
			echo '<ul class="proj-list">';			
			while ($row = @mysql_fetch_assoc($result)) {					
				echo '<li><label><input type="radio" name="proj" value='.$row['project_id'].' /> '.$stripslashes($row['name']).'</label> (<a href="#" onClick="confirmDelete('.$row['project_id'].', \''.$addslashes($row['name']).'\');">Delete</a>)</li>';
			}
			echo '</ul>';	
			echo "<div style='text-align:right;'><input type='submit' class='button' style='width:6em;margin-top:5px;' name='submit_new' value='Submit' /></div>";		
			
		}			
	}
	
	/* list a remote Matterhorn system's captionable/describable projects */
	function printRemoteProjects($rid, $pageNum=1) {
		global $remote_systems;
				
		//check if connected
		if (!@file_get_contents($remote_systems[$rid]['url'])) {
			echo "Can't connect to remote server.";
			return;
		}
		
		$projsPerPage = 20;

		$uri = "/opencaps/rest/list/processed.json";	
		$remote_media = json_decode(matterhornAuth($rid, $uri));	
				
		$numrows = $remote_media->total;		
		$maxPage = ceil($numrows/$projsPerPage);
				
		$nav  = 'Page: ';		
		for($page = 1; $page <= $maxPage; $page++) {
		   if ($page == $pageNum) {
		      $nav .= " $page "; 
		   } else {
		      $nav .= ' <a href="start_remote.php?r='.$rid.'&page='.$page.'">'.$page.'</a> ';
		   }
		}		
		
		if ($maxPage > 1)
			echo $nav."<br /><br />";
		
		$offset = ($pageNum - 1) * $projsPerPage;
		
		$uri = "/opencaps/rest/list/processed.json?count=90&startPage=".($pageNum-1);		
				
		//$remote_json = $remote_systems[$rid]['url']."/opencaps/rest/list/processed.json?count=$projsPerPage&startPage=".($pageNum-1);
				
		$_SESSION['rid'] = $rid;		
		$remote_media = json_decode(matterhornAuth($rid, $uri));		
				
		if (!empty($remote_media->results)) {
			echo '<ul class="proj-list">';
			foreach($remote_media->results as $rm) {			
				echo '<li><label><input type="radio" name="proj" value='.$rm->id.' /> '.$rm->title.'</label></li>';
			}
			echo '</ul>';
			echo "<div style='text-align:right;'><input type='submit' class='button' style='width:6em;margin-top:5px;' name='startopen' value='Submit' /></div>";
		} else {
			echo "No projects yet.";
		}
	}
	
	function saveJson($json, $pid) {
		global $stripslashes;
				
		$json_path = INCLUDE_PATH.'../projects/'.$pid.'/';
			
		if (!file_exists($json_path))
			mkdir($json_path);
			
		if (!file_put_contents($json_path.'opencaps.json', $stripslashes($json))) {
			$_SESSION['errors'][] = 'Could not create project file.';
		}	
	}
	
	function remoteUpdate($rid) {
		global $remote_systems;
		
		//remove 'export.php'
		$base_path = 'http://'.$_SERVER['SERVER_NAME'];
		if (!empty($_SERVER['SERVER_PORT'])) 
			$base_path .= ':'.$_SERVER['SERVER_PORT'];
		$base_path .= substr($_SERVER['PHP_SELF'], 0, -10);
		
		//convert to dfxp
		$convert_url = $base_path.'conversion_service/index.php?cc_url='.urlencode('../projects/'.$this->id.'/opencaps.json').'&cc_result=0&cc_target=DFXP&cc_name=noname.txt';
		$formatted_captions = trim(@file_get_contents($convert_url));
						
		if (!empty($formatted_captions)) {
			//save dfxp file
			$cappath = INCLUDE_PATH.'../projects/'.$this->id.'/'.'captions.dfxp.xml';
			@file_put_contents($cappath, $formatted_captions);
			
			//send dfxp contents to remote server
			
			$uri = '/opencaps/rest/'.$this->id.'/TimedText/';			
			$content = $formatted_captions;
			$response = matterhornAuth($rid, $uri, $content);	
									
			if (empty($response)) {
				$_SESSION['feedback'][] = "Successfully updated server.";
				header('Location:start_remote.php?rid='.$rid);
				exit;
			} else {
				$_SESSION['errors'][] = "Could not update remote server - http error ".$response;
				header('Location:export.php');
				exit;
			}
			
		} else {
			$_SESSION['errors'][] = "Could not convert captions to required format.";
			header('Location:export.php');
			exit;
		}
		
	}
	
	function importCaptions($capfile) {		
		global $page;
		
		$page = explode('/',$_SERVER['PHP_SELF']); 
		$page = end($page);
		
		//save
		if ( !move_uploaded_file( $capfile['tmp_name'], INCLUDE_PATH.'../projects/'.$this->id.'/'.basename($capfile['name'])) ) {
			$_SESSION['errors'][] = "Problem uploading caption file - can't copy file to server.";			
			$this->delete($this->id);
			return;				
		}	

		//convert
		$page_len = -(strlen($page)+1);
		$base_url = substr('http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"], 0, $page_len);		
		
		$ccollection = new clipCollection() ;
		
		$convert_url = $base_url.'/conversion_service/?cc_url='.urlencode('../projects/'.$this->id.'/'.basename($capfile['name'])).'&cc_result=0&cc_target=JSONcc&cc_name=noname.___';	
		$json_captions = json_decode(@file_get_contents($convert_url));
		
		if (!empty($json_captions) && $json_captions != "The format of source Caption was not recognized.") {			
			
			foreach ($json_captions->captionCollection as $clip) {				
				$this_clip = new clip($clip->inTime, $clip->outTime, trim($clip->caption));
				$ccollection->addClip($this_clip);
			}	

			$_SESSION['feedback'][] = "Captions imported successfully.";
										
		} else  {
			$_SESSION['errors'][] = "Problem uploading caption file - the format is incorrect, or unsupported.";
		}		
		
		$this->clip_collection = $ccollection;
			
		//save reference file
		$json = json_encode(get_object_vars($this));		
		$this->saveJson($json, $this->id);		

		return;

	}		
	
	function exportCaption($format) {

		if ($format == "all") {
				$this->export_pkg();				
						
		} else if ($format == "json") {
				$cappath = INCLUDE_PATH.'../projects/'.$this->id.'/'.'opencaps.json';
				//make a copy of the json that has the proj name as prefix
				
						
		} else {
			$base_url = substr('http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"], 0, -10);		
			
			$convert_url = $base_url.'conversion_service/?cc_url='.urlencode('../projects/'.$this->id.'/opencaps.json').'&cc_result=0&cc_target='.$format.'&cc_name=noname.___';	
			$formatted_captions = @file_get_contents($convert_url);
			if (!empty($formatted_captions)) {
				$formatted_captions = trim($formatted_captions);				
				$cappath = INCLUDE_PATH.'../projects/'.$this->id.'/'.str_replace(' ', '_', $this->name).'_';
			
				switch($format) {
					case "DFXP":
						$cappath = $cappath.'captions.dfxp.xml';
						break;
	
					case "DvdStl":
						$cappath = $cappath.'captions.stl';
						break;					
						
					case "MicroDvd":
						$cappath = $cappath.'captions.sub';
						break;
						
					case "MPlayer":
						$cappath = $cappath.'captions.MPsub';
						break;
						
					case "QTtext":						
						$formatted_captions = $formatted_captions."\r\n".'['.$this->duration.']'; /* white cap background hack */						
						$cappath = $cappath.'captions.txt';
						break;					
	
					case "RealText":
						$cappath = $cappath.'captions.rt';
						break;	
											
					case "Sami":
						$cappath = $cappath.'capscribe.smi';
						break;
	
					case "SubRipSrt":
						$cappath = $cappath.'captions.srt';
						break;
						
					case "Scc":
						$cappath = $cappath.'captions.scc';
						break;		
	
					case "SubViewer":
						$cappath = $cappath.'captions.sub';
						break;						
				}
	
				@file_put_contents($cappath, $formatted_captions);
			}
		}
		export_file($cappath);	
		exit;		
	}
	
	function delete($proj_id) {
		global $this_db;
				
		if (deleteDir('../projects/'.$proj_id."/")) {				
			$sql = "DELETE from projects WHERE project_id=".$proj_id." AND member_id=".$_SESSION['mid'];
			$result = mysql_query($sql, $this_db->db);			
		} else {
			//echo "Project could not be deleted.";
		}
	}

	function preview($layout) {	
		$this->layout = $layout;
		
		//send this to the conversion service
		$base_url = substr('http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"], 0, -20);		
		$json_url = urlencode($base_url.'projects/'.$this->id.'/opencaps.json');
		$request = $base_url."conversion_service/?cc_url=".$json_url."&cc_result=0&cc_target=QTtext&cc_name=noname.___";
			
		//create the qt-text file
		$qt_text = @file_get_contents($request)."\r\n".'['.$this->duration.']';	
		@file_put_contents('../projects/'.$this->id.'/captions.txt', $qt_text);

		//send the embed height back as a response
		$eheight = $this->get_smil('save');
		if ($eheight >= 100)
			echo $eheight;
		else 
			echo '100';
	}
	

	function get_smil($method="") {
		$layout = $this->layout; 
						
		$contents = @file_get_contents(INCLUDE_PATH.'../projects/layouts/smil_'.$layout.'.mov');
		$contents = str_replace("%rootwidth%", $this->media_width, $contents);
		
		$vid_file_name = end(explode('/',$this->media_loc));
	
		if ($this->media_loc == 'projects/'.$this->id.'/'.$vid_file_name)
			$contents = str_replace("%video%", $vid_file_name, $contents);
		else
			$contents = str_replace("%video%", $this->media_loc, $contents);
			
		$contents = str_replace("%duration%", $this->duration, $contents);
		$contents = str_replace("%width%", $this->media_width, $contents);
		$contents = str_replace("%height%", $this->media_height, $contents);
		
		$contents = str_replace("%capwidth%", $this->media_width-10, $contents);
		
		if ($layout == 0) { //cap below
			$contents = str_replace("%rootheight%", $this->media_height+85, $contents);
			$obj_height = $this->media_height+100;
			$contents = str_replace("%captop%", $this->media_height+10, $contents);
			$contents = str_replace("%capheight%", 135, $contents);  //affects font size
			
		} else if ($layout == 1) { //cap bottom
			$contents = str_replace("%rootheight%", $this->media_height, $contents);
			$obj_height = $this->media_height+30;
			$contents = str_replace("%captop%", $this->media_height-75, $contents);
			$contents = str_replace("%capheight%", 135, $contents);
			
		} else { //caption only
			$contents = str_replace("%rootheight%", 85, $contents);
			$obj_height = 155;
			$contents = str_replace("%captop%", 10, $contents);
			$contents = str_replace("%capheight%", 135, $contents);
		}

		if ($method == "save") {
			$proj_smil = INCLUDE_PATH.'../projects/'.$this->id.'/smil_'.$layout.'.mov';
			@file_put_contents($proj_smil, $contents);
			return $obj_height;	
		} else {
			return $contents;
		}
	}	
	
	function export_pkg() {						
		//create zipfile
		$zip = new ZipArchive();
		
		$proj_name = str_replace(' ', '', $this->name);
		$proj_name = str_replace("'", '', $proj_name);	
		$zipfile = $proj_name.".zip";
		if ($zip->open(INCLUDE_PATH.'../projects/'.$this->id.'/'.$zipfile, ZIPARCHIVE::CREATE)!==TRUE) {
			exit("cannot open <$zipfile>\n");
		}
		
		$zip->addEmptyDir($proj_name);
		
		//add smil
		$contents = $this->get_smil();		
		$zip->addFromString($proj_name.'/smil.mov', $contents);
	
		//create player from template	
		$contents = file_get_contents(INCLUDE_PATH.'../projects/player_template.php');
		$contents = str_replace("%width%", $this->media_width, $contents);
		$contents = str_replace("%height%", $this->media_height+135, $contents);		
			
		//add player
		$zip->addFromString($proj_name.'/player.html', $contents);
				
		//add captions		
		$zip->addFile(INCLUDE_PATH.'../projects/'.$this->id.'/captions.txt', $proj_name.'/captions.txt');
		
		//add movie if not a URL
		if (current(explode('/', $this->media_loc)) == "projects") {
			$media_name = end(explode('/', $this->media_loc));
			$zip->addFile($this->media_loc, $proj_name.'/'.$media_name);
		}
				
		$zip->close();		
		//export_file(INCLUDE_PATH.'../projects/'.$this->id.'/'.$zipfile);
		unlink(INCLUDE_PATH.'../projects/'.$this->id.'/'.$zipfile);
		exit;		
	}	
	
	function editName($name) {
		global $addslashes, $this_db;
				
		$this->name = $addslashes($name);
		
		//save reference file
		$json = json_encode(get_object_vars($this));		
		$this->saveJson($json, $this->id);				
		$this_db->updateProj($this);		
	}
	
}


/* utility functions */

/* force download of a file in browser */
function export_file($exfile) {
	if (file_exists($exfile)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($exfile));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: '.filesize($exfile));
		ob_clean();
		flush();
		readfile($exfile);
	}
}

/* delete a directory and its contents */
function deleteDir($dir) {
	$dh = @opendir($dir);
	while ( $file = @readdir($dh) ) {
		if ( $file != '.' || $file != '..') {
			@unlink($dir.$file);
			//if ( ! @unlink ( $dir . '/' . $obj ) ) deleteDir ( $dir . '/' . $obj, true )
		}
	}
	@closedir ($dh);
	if (@rmdir($dir))
		return true;
	else
		return false;
}


?>