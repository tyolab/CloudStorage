<?php
/******************************************************************************
 * This file is part of the MediaWiki/extensions/CloudStorage extension.
*
* (c) Copyright 2015 TYONLINE TECHNOLOGY PTY. LTD.
*
* This file may be distributed and/or modified under the terms of the
* GNU LESSER GENERAL PUBLIC LICENSE, Version 3 as published by the Free Software
* Foundation and appearing in the file LICENSE.LGPL included in the
* packaging of this file.
*
* This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING THE
* WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
*
*****************
*
* @created  		27/Jun/2015
* @author			Eric Tang
*
*******************************************************************************/

$IP = getenv( 'MW_INSTALL_PATH' );

if ( empty($wgScriptPath) || $wgScriptPath === false)
	$wgScriptPath = "/wiki";

if (empty($wgRunOnGae)) {
	if(isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'],'Google App Engine') !== false) 
		$wgRunOnGae = true;
	else 
		$wgRunOnGae = false;
}

if ( $IP === false ) {
	$IP = (realpath( '.' ) ?: dirname( __DIR__ )) . '/../../..' . $wgScriptPath;
	putenv("MW_INSTALL_PATH=$IP");
}

require "$IP/includes/WebStart.php";

require_once( "$IP/GoogleAppEngineSettings.php" );

$file = ($wgGaeHome ? $wgGaeHome : '') . 'google/appengine/api/cloud_storage/CloudStorageTools.php';

require_once $file;
use google\appengine\api\cloud_storage\CloudStorageTools;

require_once( "GoogleCloudStorage.php" );

$url = $_SERVER['REQUEST_URI'];

if ( !preg_match( '!^https?://!', $url ) ) {
	$url = 'http://unused' . $url;
}
wfSuppressWarnings();
$a = parse_url( $url );
$path = $a['path'];
$pos = strpos($path, 'images');
/*
 * 
 */
$ext = pathinfo($path, PATHINFO_EXTENSION);

header("Content-Type:image/" . $ext);

/*
 * check if we run on GAE, the local php runtime won't be able to recognize the
 * cloud storage path, such as "gs://"
 */
if ($pos) {
	$imagePathFile = substr($path, $pos);
	
	if (!$wgRunOnGae) {
		/* OK, we redirect it back what is requested */
		/* the following will lead into infinite loop */
		// header('Location:' . $url);
		
		$localfile = "$IP/" . $imagePathFile; 
		header("CloudStoragePath:" . $localfile);
		if(!file_exists($localfile)) {
			header('HTTP/1.0 404 Not Found');
			exit();
		}
		
		/* 
		 * not to worry about the caching yet
		 */
		// Handle caching
		/*
		$fileModificationTime = gmdate('D, d M Y H:i:s', File::modificationTime($path)).' GMT';
		$headers = getallheaders();
		if(isset($headers['If-Modified-Since']) && $headers['If-Modified-Since'] == $fileModificationTime) {
			header('HTTP/1.1 304 Not Modified');
			exit();
		}
		header('Last-Modified: '.$fileModificationTime);
		*/
		
		// Read the file
		readfile($localfile);
		
		exit();
	}
	else {
		global $wgCss;
		
		$object_image_file = $wgCss->getBucketUrl() . $imagePathFile;
		$object_image_url = CloudStorageTools::getImageServingUrl($object_image_file, ['size' => 0/* , 'crop' => true] */]);
		//header("CloudStoragePath:" . $object_image_url);
		header('Location:' . $object_image_url);
	}
}
else {
	header('HTTP/1.0 404 Not Found');
	exit();
}