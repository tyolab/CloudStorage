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

$wgCloundStorageDriver = "gs";

/******* Your S3 / GS bucket to be used *******/
/*
 * $wgCloudStorageBucket should be overrided after initiali 
 */
if (empty($wgCloudStorageBucket)){
	$wgCloudStorageBucket = 'BUCKET_NAME'; // set the name
	die ("Cloud Storage Bucket is not set yet.");
}
$wgCloudStorageDirectory = 'images'; // prefix to uploaded files
$wgUseSSL = false; // true if SSL should be used
$wgPublic = true; // true if public, false if authentication should be used

require_once 'CloudStorageService.php';

# Cloud Storage Service instance
$wgCss = false;

if ($wgCloundStorageDriver == "gs") {
	$wgUploadToRepoName = 'GoogleCloudStorage';
	if ($wgRunOnGae === true)
		$wgUploadFromUrlClass = 'UploadFromUrlToGoogleCloudStorage';
	require_once 'GoogleCloudStorage.php';
	require_once 'UploadFromUrlToGoogleCloudStorage.php';
}
elseif ($wgCloundStorageDriver == "s3") {
	$wgUploadToRepoName = 'AmazonS3';
	require_once 'AmazonS3.php';
}
else {
	die ("No Approprieate Cloud Storage Driver Found");
}

$wgUploadDirectory = $wgCloudStorageDirectory;