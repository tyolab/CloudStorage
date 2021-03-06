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
*
*******************************************************************************
*
* @created  		27/Jun/2015
* @author			Eric Tang
*
*******************************************************************************/
global $wgRunOnGae, $wgUploadPath, $wgUploadDirectory;

// 
if ($wgRunOnLocalGae) {
	if (empty($wgGaeHome)) {
		die ("Please setup \$wgGaeHome in your LocalSettings.php, for example, \$wgGaeHome=/data/tools/GAE/google_appengine/php/sdk/");
	}
}
else 
	$wgGaeHome = '';

$wgCssProtocol = "gs";

if ($wgRunOnGae || $wgUseGoogleStorage === true) {
	$wgCloudStorageUploadPath = 'images';
}
else {
	$wgCssProtocol = "file";
	$wgCloudStorageBucket = $IP; // for cloud storage always empty, but for local file bucket is $IP
	//$wgCloudStorageUploadPath = $wgUploadDirectory;
	if (empty($wgCloudStorageUploadPath))
		$wgCloudStorageUploadPath = "images";
}

$wgFsBackend = $wgCssProtocol;

$wgCloudStorageBaseUrl = $wgCssProtocol . "://";
$wgCloudStorageUrl = $wgCloudStorageBaseUrl . $wgCloudStorageBucket . '/';
$wgCloudStorageDirectory = $wgCloudStorageUrl . $wgCloudStorageUploadPath;

/*******************************************************************************************************************
		'backend' => $wgFsBackend,

		'url' => $wgCloudStorageUrl ? $wgCloudStorageUrl . $wgCloudStorageUploadPath : $wgCloudStorageUploadPath,
		'urlbase' => $wgCloudStorageBaseUrl ? $wgCloudStorageBaseUrl : "",
		'hashLevels' => $wgHashedUploadDirectory ? 2 : 0,
		'thumbScriptUrl' => $wgThumbnailScriptPath,
		'transformVia404' => !$wgGenerateThumbnailOnParse,
		'initialCapital' => $wgCapitalLinks,
		'deletedDir' => $wgCloudStorageDirectory.'/deleted',
		'deletedHashLevels' => $wgHashedUploadDirectory ? 3 : 0,
 ********************************************************************************************************************/
if (!class_exists('CloudStorageFileBackend')) require_once( "CloudStorageFileBackend.php");

require_once("CloudStorageRepo.php");
require_once("GoogleCloudStorageFile.php");
require_once("CloudStorageFileArchive.php");
require_once("GoogleCloudStorageRepo.php");

if (!class_exists('GoogleCloudStorageService')) require_once( "GoogleCloudStorageService.php");

# Cloud Storage Service instance
$wgCss = new GoogleCloudStorageService();
$wgCss->setProtocol($wgCssProtocol);
$wgCss->setBucketName($wgCloudStorageBucket);


$wgLocalFileRepo = array(
		'class' => 'GoogleCloudStorageRepo',
		'name' => 'gs',
		'backend' => new CloudStorageFileBackend( array( name => 'CloudStorageFileBackend',
								'wikiId' => wfWikiID(), ) ),
		'directory' => $wgCloudStorageDirectory,
		'scriptDirUrl' => $wgScriptPath,
		'scriptExtension' => $wgScriptExtension,
		'url' => $wgUploadBaseUrl ? $wgUploadBaseUrl . $wgUploadPath : $wgUploadPath,
		'thumbUrl' => $wgUploadThumbUrl ? $wgUploadThumbUrl : $wgUploadPath . '/thumb',
		'bucket' => $wgCloudStorageBucket
);
