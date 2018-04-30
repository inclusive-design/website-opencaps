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

/* edit with correct values and copy to /include/config.inc.php */


/* general config */
define('MAX_FILE_SIZE',	'10485760'); //10M
define('DISABLE_LOCAL',	false);

/* mysql config */
define('DB_USER',		'root');
define('DB_PASSWORD',	'password');
define('DB_HOST',		'localhost');
define('DB_PORT',		'3306');
define('DB_NAME',       'opencaps');

/* matterhorn config */
/*
$remote_systems[0]['url'] = 'http://localhost:8080';
$remote_systems[0]['name'] = 'Matterhorn Local';
$remote_systems[0]['username'] = 'username';
$remote_systems[0]['password'] = 'password';

$remote_systems[1]['url'] = 'http://nightly.opencastproject.org';
$remote_systems[1]['name'] = 'Matterhorn Nightly';
$remote_systems[1]['username'] = 'username';
$remote_systems[1]['password'] = 'password';
*/

?>