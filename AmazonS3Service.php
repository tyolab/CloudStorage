<?php
/**
* $Id: S3.php 47 2009-07-20 01:25:40Z don.schonknecht $
*
* Copyright (c) 2008, Donovan Schönknecht.  All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* Amazon S3 is a trademark of Amazon.com, Inc. or its affiliates.
*/

/**
* Amazon S3 PHP class
*
* @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
* @version 0.4.0
*/
class AmazonS3Service extends CloudStorageService {

	/**
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param boolean $useSSL Enable SSL
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $useSSL = true) {
		parent::__construct($accessKey, $secretKey, $useSSL);
	}

	/**
	* Get a list of buckets
	*
	* @param boolean $detailed Returns detailed bucket list when true
	* @return array | false
	*/
	public function listBuckets($detailed = false) {
		$rest = new S3Request('GET', '', '');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::listBuckets(): [%s] %s", $rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		$results = array();
		if (!isset($rest->body->Buckets)) return $results;

		if ($detailed) {
			if (isset($rest->body->Owner, $rest->body->Owner->ID, $rest->body->Owner->DisplayName))
			$results['owner'] = array(
				'id' => (string)$rest->body->Owner->ID, 'name' => (string)$rest->body->Owner->ID
			);
			$results['buckets'] = array();
			foreach ($rest->body->Buckets->Bucket as $b)
				$results['buckets'][] = array(
					'name' => (string)$b->Name, 'time' => strtotime((string)$b->CreationDate)
				);
		} else
			foreach ($rest->body->Buckets->Bucket as $b) $results[] = (string)$b->Name;

		return $results;
	}


	/*
	* Get contents for a bucket
	*
	* If maxKeys is null this method will loop through truncated result sets
	*
	* @param string $bucket Bucket name
	* @param string $prefix Prefix
	* @param string $marker Marker (last file listed)
	* @param string $maxKeys Max keys (maximum number of keys to return)
	* @param string $delimiter Delimiter
	* @param boolean $returnCommonPrefixes Set to true to return CommonPrefixes
	* @return array | false
	*/
	public function getBucket($bucket, $prefix = null, $marker = null, $maxKeys = null, $delimiter = null, $returnCommonPrefixes = false) {
		$rest = new S3Request('GET', $bucket, '');
		if ($prefix !== null && $prefix !== '') $rest->setParameter('prefix', $prefix);
		if ($marker !== null && $marker !== '') $rest->setParameter('marker', $marker);
		if ($maxKeys !== null && $maxKeys !== '') $rest->setParameter('max-keys', $maxKeys);
		if ($delimiter !== null && $delimiter !== '') $rest->setParameter('delimiter', $delimiter);
		$response = $rest->getResponse();
		if ($response->error === false && $response->code !== 200)
			$response->error = array('code' => $response->code, 'message' => 'Unexpected HTTP status');
		if ($response->error !== false) {
			trigger_error(sprintf("S3::getBucket(): [%s] %s", $response->error['code'], $response->error['message']), E_USER_WARNING);
			return false;
		}

		$results = array();

		$nextMarker = null;
		if (isset($response->body, $response->body->Contents))
		foreach ($response->body->Contents as $c) {
			$results[(string)$c->Key] = array(
				'name' => (string)$c->Key,
				'time' => strtotime((string)$c->LastModified),
				'size' => (int)$c->Size,
				'hash' => substr((string)$c->ETag, 1, -1)
			);
			$nextMarker = (string)$c->Key;
		}

		if ($returnCommonPrefixes && isset($response->body, $response->body->CommonPrefixes))
			foreach ($response->body->CommonPrefixes as $c)
				$results[(string)$c->Prefix] = array('prefix' => (string)$c->Prefix);

		if (isset($response->body, $response->body->IsTruncated) &&
		(string)$response->body->IsTruncated == 'false') return $results;

		if (isset($response->body, $response->body->NextMarker))
			$nextMarker = (string)$response->body->NextMarker;

		// Loop through truncated results if maxKeys isn't specified
		if ($maxKeys == null && $nextMarker !== null && (string)$response->body->IsTruncated == 'true')
		do {
			$rest = new S3Request('GET', $bucket, '');
			if ($prefix !== null && $prefix !== '') $rest->setParameter('prefix', $prefix);
			$rest->setParameter('marker', $nextMarker);
			if ($delimiter !== null && $delimiter !== '') $rest->setParameter('delimiter', $delimiter);

			if (($response = $rest->getResponse(true)) == false || $response->code !== 200) break;

			if (isset($response->body, $response->body->Contents))
			foreach ($response->body->Contents as $c) {
				$results[(string)$c->Key] = array(
					'name' => (string)$c->Key,
					'time' => strtotime((string)$c->LastModified),
					'size' => (int)$c->Size,
					'hash' => substr((string)$c->ETag, 1, -1)
				);
				$nextMarker = (string)$c->Key;
			}

			if ($returnCommonPrefixes && isset($response->body, $response->body->CommonPrefixes))
				foreach ($response->body->CommonPrefixes as $c)
					$results[(string)$c->Prefix] = array('prefix' => (string)$c->Prefix);

			if (isset($response->body, $response->body->NextMarker))
				$nextMarker = (string)$response->body->NextMarker;

		} while ($response !== false && (string)$response->body->IsTruncated == 'true');

		return $results;
	}


	/**
	* Put a bucket
	*
	* @param string $bucket Bucket name
	* @param constant $acl ACL flag
	* @param string $location Set as "EU" to create buckets hosted in Europe
	* @return boolean
	*/
	public function putBucket($bucket, $acl = self::ACL_PRIVATE, $location = false) {
		$rest = new S3Request('PUT', $bucket, '');
		$rest->setAmzHeader('x-amz-acl', $acl);

		if ($location !== false) {
			$dom = new DOMDocument;
			$createBucketConfiguration = $dom->createElement('CreateBucketConfiguration');
			$locationConstraint = $dom->createElement('LocationConstraint', strtoupper($location));
			$createBucketConfiguration->appendChild($locationConstraint);
			$dom->appendChild($createBucketConfiguration);
			$rest->data = $dom->saveXML();
			$rest->size = strlen($rest->data);
			$rest->setHeader('Content-Type', 'application/xml');
		}
		$rest = $rest->getResponse();

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::putBucket({$bucket}, {$acl}, {$location}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Delete an empty bucket
	*
	* @param string $bucket Bucket name
	* @return boolean
	*/
	public function deleteBucket($bucket) {
		$rest = new S3Request('DELETE', $bucket);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::deleteBucket({$bucket}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Create input info array for putObject()
	*
	* @param string $file Input file
	* @param mixed $md5sum Use MD5 hash (supply a string if you want to use your own)
	* @return array | false
	*/
	public function inputFile($file, $md5sum = true) {
		if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
			trigger_error('S3::inputFile(): Unable to open input file: '.$file, E_USER_WARNING);
			return false;
		}
		return array('file' => $file, 'size' => filesize($file),
		'md5sum' => $md5sum !== false ? (is_string($md5sum) ? $md5sum :
		base64_encode(md5_file($file, true))) : '');
	}


	/**
	* Create input array info for putObject() with a resource
	*
	* @param string $resource Input resource to read from
	* @param integer $bufferSize Input byte size
	* @param string $md5sum MD5 hash to send (optional)
	* @return array | false
	*/
	public function inputResource(&$resource, $bufferSize, $md5sum = '') {
		if (!is_resource($resource) || $bufferSize < 0) {
			trigger_error('S3::inputResource(): Invalid resource or buffer size', E_USER_WARNING);
			return false;
		}
		$input = array('size' => $bufferSize, 'md5sum' => $md5sum);
		$input['fp'] =& $resource;
		return $input;
	}


	/**
	* Put an object
	*
	* @param mixed $input Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param array $requestHeaders Array of request headers or content type as a string
	* @return boolean
	*/
	public function putObject($input, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array()) {
		if ($input === false) return false;
		$rest = new S3Request('PUT', $bucket, $uri);

		if (is_string($input)) $input = array(
			'data' => $input, 'size' => strlen($input),
			'md5sum' => base64_encode(md5($input, true))
		);

		// Data
		if (isset($input['fp']))
			$rest->fp =& $input['fp'];
		elseif (isset($input['file']))
			$rest->fp = @fopen($input['file'], 'rb');
		elseif (isset($input['data']))
			$rest->data = $input['data'];

		// Content-Length (required)
		if (isset($input['size']) && $input['size'] >= 0)
			$rest->size = $input['size'];
		else {
			if (isset($input['file']))
				$rest->size = filesize($input['file']);
			elseif (isset($input['data']))
				$rest->size = strlen($input['data']);
		}

		// Custom request headers (Content-Type, Content-Disposition, Content-Encoding)
		if (is_array($requestHeaders))
			foreach ($requestHeaders as $h => $v) $rest->setHeader($h, $v);
		elseif (is_string($requestHeaders)) // Support for legacy contentType parameter
			$input['type'] = $requestHeaders;

		// Content-Type
		if (!isset($input['type'])) {
			if (isset($requestHeaders['Content-Type']))
				$input['type'] =& $requestHeaders['Content-Type'];
			elseif (isset($input['file']))
				$input['type'] = self::__getMimeType($input['file']);
			else
				$input['type'] = 'application/octet-stream';
		}

		// We need to post with Content-Length and Content-Type, MD5 is optional
		if ($rest->size >= 0 && ($rest->fp !== false || $rest->data !== false)) {
			$rest->setHeader('Content-Type', $input['type']);
			if (isset($input['md5sum'])) $rest->setHeader('Content-MD5', $input['md5sum']);

			$rest->setAmzHeader('x-amz-acl', $acl);
			foreach ($metaHeaders as $h => $v) $rest->setAmzHeader('x-amz-meta-'.$h, $v);
			$rest->getResponse();
		} else
			$rest->response->error = array('code' => 0, 'message' => 'Missing input parameters');

		if ($rest->response->error === false && $rest->response->code !== 200)
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		if ($rest->response->error !== false) {
			trigger_error(sprintf("S3::putObject(): [%s] %s", $rest->response->error['code'], $rest->response->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Put an object from a file (legacy function)
	*
	* @param string $file Input file path
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public function putObjectFile($file, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = null) {
		return self::putObject(self::inputFile($file), $bucket, $uri, $acl, $metaHeaders, $contentType);
	}


	/**
	* Put an object from a string (legacy function)
	*
	* @param string $string Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public function putObjectString($string, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = 'text/plain') {
		return self::putObject($string, $bucket, $uri, $acl, $metaHeaders, $contentType);
	}


	/**
	* Get an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param mixed $saveTo Filename or resource to write to
	* @return mixed
	*/
	public function getObject($bucket, $uri, $saveTo = false) {
		$rest = new S3Request('GET', $bucket, $uri);
		if ($saveTo !== false) {
			if (is_resource($saveTo))
				$rest->fp =& $saveTo;
			else
				if (($rest->fp = @fopen($saveTo, 'wb')) !== false)
					$rest->file = realpath($saveTo);
				else
					$rest->response->error = array('code' => 0, 'message' => 'Unable to open save file for writing: '.$saveTo);
		}
		if ($rest->response->error === false) $rest->getResponse();

		if ($rest->response->error === false && $rest->response->code !== 200)
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		if ($rest->response->error !== false) {
			trigger_error(sprintf("S3::getObject({$bucket}, {$uri}): [%s] %s",
			$rest->response->error['code'], $rest->response->error['message']), E_USER_WARNING);
			return false;
		}
		return $rest->response;
	}


	/**
	* Get object information
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param boolean $returnInfo Return response information
	* @return mixed | false
	*/
	public function getObjectInfo($bucket, $uri, $returnInfo = true) {
		$rest = new S3Request('HEAD', $bucket, $uri);
		$rest = $rest->getResponse();
		if ($rest->error === false && ($rest->code !== 200 && $rest->code !== 404))
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::getObjectInfo({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return $rest->code == 200 ? $returnInfo ? $rest->headers : true : false;
	}


	/**
	* Copy an object
	*
	* @param string $bucket Source bucket name
	* @param string $uri Source object URI
	* @param string $bucket Destination bucket name
	* @param string $uri Destination object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Optional array of x-amz-meta-* headers
	* @param array $requestHeaders Optional array of request headers (content type, disposition, etc.)
	* @return mixed | false
	*/
	public function copyObject($srcBucket, $srcUri, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array()) {
		$rest = new S3Request('PUT', $bucket, $uri);
		$rest->setHeader('Content-Length', 0);
		foreach ($requestHeaders as $h => $v) $rest->setHeader($h, $v);
		foreach ($metaHeaders as $h => $v) $rest->setAmzHeader('x-amz-meta-'.$h, $v);
		$rest->setAmzHeader('x-amz-acl', $acl);
		$rest->setAmzHeader('x-amz-copy-source', sprintf('/%s/%s', $srcBucket, $srcUri));
		if (sizeof($requestHeaders) > 0 || sizeof($metaHeaders) > 0)
			$rest->setAmzHeader('x-amz-metadata-directive', 'REPLACE');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::copyObject({$srcBucket}, {$srcUri}, {$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return isset($rest->body->LastModified, $rest->body->ETag) ? array(
			'time' => strtotime((string)$rest->body->LastModified),
			'hash' => substr((string)$rest->body->ETag, 1, -1)
		) : false;
	}


	/**
	* Set logging for a bucket
	*
	* @param string $bucket Bucket name
	* @param string $targetBucket Target bucket (where logs are stored)
	* @param string $targetPrefix Log prefix (e,g; domain.com-)
	* @return boolean
	*/
	public function setBucketLogging($bucket, $targetBucket, $targetPrefix = null) {
		// The S3 log delivery group has to be added to the target bucket's ACP
		if ($targetBucket !== null && ($acp = self::getAccessControlPolicy($targetBucket, '')) !== false) {
			// Only add permissions to the target bucket when they do not exist
			$aclWriteSet = false;
			$aclReadSet = false;
			foreach ($acp['acl'] as $acl)
			if ($acl['type'] == 'Group' && $acl['uri'] == 'http://acs.amazonaws.com/groups/s3/LogDelivery') {
				if ($acl['permission'] == 'WRITE') $aclWriteSet = true;
				elseif ($acl['permission'] == 'READ_ACP') $aclReadSet = true;
			}
			if (!$aclWriteSet) $acp['acl'][] = array(
				'type' => 'Group', 'uri' => 'http://acs.amazonaws.com/groups/s3/LogDelivery', 'permission' => 'WRITE'
			);
			if (!$aclReadSet) $acp['acl'][] = array(
				'type' => 'Group', 'uri' => 'http://acs.amazonaws.com/groups/s3/LogDelivery', 'permission' => 'READ_ACP'
			);
			if (!$aclReadSet || !$aclWriteSet) self::setAccessControlPolicy($targetBucket, '', $acp);
		}

		$dom = new DOMDocument;
		$bucketLoggingStatus = $dom->createElement('BucketLoggingStatus');
		$bucketLoggingStatus->setAttribute('xmlns', 'http://s3.amazonaws.com/doc/2006-03-01/');
		if ($targetBucket !== null) {
			if ($targetPrefix == null) $targetPrefix = $bucket . '-';
			$loggingEnabled = $dom->createElement('LoggingEnabled');
			$loggingEnabled->appendChild($dom->createElement('TargetBucket', $targetBucket));
			$loggingEnabled->appendChild($dom->createElement('TargetPrefix', $targetPrefix));
			// TODO: Add TargetGrants?
			$bucketLoggingStatus->appendChild($loggingEnabled);
		}
		$dom->appendChild($bucketLoggingStatus);

		$rest = new S3Request('PUT', $bucket, '');
		$rest->setParameter('logging', null);
		$rest->data = $dom->saveXML();
		$rest->size = strlen($rest->data);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::setBucketLogging({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Get logging status for a bucket
	*
	* This will return false if logging is not enabled.
	* Note: To enable logging, you also need to grant write access to the log group
	*
	* @param string $bucket Bucket name
	* @return array | false
	*/
	public function getBucketLogging($bucket) {
		$rest = new S3Request('GET', $bucket, '');
		$rest->setParameter('logging', null);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::getBucketLogging({$bucket}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		if (!isset($rest->body->LoggingEnabled)) return false; // No logging
		return array(
			'targetBucket' => (string)$rest->body->LoggingEnabled->TargetBucket,
			'targetPrefix' => (string)$rest->body->LoggingEnabled->TargetPrefix,
		);
	}


	/**
	* Disable bucket logging
	*
	* @param string $bucket Bucket name
	* @return boolean
	*/
	public function disableBucketLogging($bucket) {
		return self::setBucketLogging($bucket, null);
	}


	/**
	* Get a bucket's location
	*
	* @param string $bucket Bucket name
	* @return string | false
	*/
	public function getBucketLocation($bucket) {
		$rest = new S3Request('GET', $bucket, '');
		$rest->setParameter('location', null);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::getBucketLocation({$bucket}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return (isset($rest->body[0]) && (string)$rest->body[0] !== '') ? (string)$rest->body[0] : 'US';
	}


	/**
	* Set object or bucket Access Control Policy
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param array $acp Access Control Policy Data (same as the data returned from getAccessControlPolicy)
	* @return boolean
	*/
	public function setAccessControlPolicy($bucket, $uri = '', $acp = array()) {
		$dom = new DOMDocument;
		$dom->formatOutput = true;
		$accessControlPolicy = $dom->createElement('AccessControlPolicy');
		$accessControlList = $dom->createElement('AccessControlList');

		// It seems the owner has to be passed along too
		$owner = $dom->createElement('Owner');
		$owner->appendChild($dom->createElement('ID', $acp['owner']['id']));
		$owner->appendChild($dom->createElement('DisplayName', $acp['owner']['name']));
		$accessControlPolicy->appendChild($owner);

		foreach ($acp['acl'] as $g) {
			$grant = $dom->createElement('Grant');
			$grantee = $dom->createElement('Grantee');
			$grantee->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
			if (isset($g['id'])) { // CanonicalUser (DisplayName is omitted)
				$grantee->setAttribute('xsi:type', 'CanonicalUser');
				$grantee->appendChild($dom->createElement('ID', $g['id']));
			} elseif (isset($g['email'])) { // AmazonCustomerByEmail
				$grantee->setAttribute('xsi:type', 'AmazonCustomerByEmail');
				$grantee->appendChild($dom->createElement('EmailAddress', $g['email']));
			} elseif ($g['type'] == 'Group') { // Group
				$grantee->setAttribute('xsi:type', 'Group');
				$grantee->appendChild($dom->createElement('URI', $g['uri']));
			}
			$grant->appendChild($grantee);
			$grant->appendChild($dom->createElement('Permission', $g['permission']));
			$accessControlList->appendChild($grant);
		}

		$accessControlPolicy->appendChild($accessControlList);
		$dom->appendChild($accessControlPolicy);

		$rest = new S3Request('PUT', $bucket, $uri);
		$rest->setParameter('acl', null);
		$rest->data = $dom->saveXML();
		$rest->size = strlen($rest->data);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::setAccessControlPolicy({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Get object or bucket Access Control Policy
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @return mixed | false
	*/
	public function getAccessControlPolicy($bucket, $uri = '') {
		$rest = new S3Request('GET', $bucket, $uri);
		$rest->setParameter('acl', null);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::getAccessControlPolicy({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}

		$acp = array();
		if (isset($rest->body->Owner, $rest->body->Owner->ID, $rest->body->Owner->DisplayName)) {
			$acp['owner'] = array(
				'id' => (string)$rest->body->Owner->ID, 'name' => (string)$rest->body->Owner->DisplayName
			);
		}
		if (isset($rest->body->AccessControlList)) {
			$acp['acl'] = array();
			foreach ($rest->body->AccessControlList->Grant as $grant) {
				foreach ($grant->Grantee as $grantee) {
					if (isset($grantee->ID, $grantee->DisplayName)) // CanonicalUser
						$acp['acl'][] = array(
							'type' => 'CanonicalUser',
							'id' => (string)$grantee->ID,
							'name' => (string)$grantee->DisplayName,
							'permission' => (string)$grant->Permission
						);
					elseif (isset($grantee->EmailAddress)) // AmazonCustomerByEmail
						$acp['acl'][] = array(
							'type' => 'AmazonCustomerByEmail',
							'email' => (string)$grantee->EmailAddress,
							'permission' => (string)$grant->Permission
						);
					elseif (isset($grantee->URI)) // Group
						$acp['acl'][] = array(
							'type' => 'Group',
							'uri' => (string)$grantee->URI,
							'permission' => (string)$grant->Permission
						);
					else continue;
				}
			}
		}
		return $acp;
	}


	/**
	* Delete an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @return boolean
	*/
	public function deleteObject($bucket, $uri) {
		$rest = new S3Request('DELETE', $bucket, $uri);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::deleteObject(): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Get a query string authenticated URL
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param integer $lifetime Lifetime in seconds
	* @param boolean $hostBucket Use the bucket name as the hostname
	* @param boolean $https Use HTTPS ($hostBucket should be false for SSL verification)
	* @return string
	*/
	public function getAuthenticatedURL($bucket, $uri, $lifetime, $hostBucket = false, $https = false) {
		$expires = time() + $lifetime;
		$uri = str_replace('%2F', '/', rawurlencode($uri)); // URI should be encoded (thanks Sean O'Dea)
		return sprintf(($https ? 'https' : 'http').'://%s/%s?AWSAccessKeyId=%s&Expires=%u&Signature=%s',
		$hostBucket ? $bucket : $bucket.'.s3.amazonaws.com', $uri, self::$__accessKey, $expires,
		urlencode(self::__getHash("GET\n\n\n{$expires}\n/{$bucket}/{$uri}")));
	}

	/**
	* Get upload POST parameters for form uploads
	*
	* @param string $bucket Bucket name
	* @param string $uriPrefix Object URI prefix
	* @param constant $acl ACL constant
	* @param integer $lifetime Lifetime in seconds
	* @param integer $maxFileSize Maximum filesize in bytes (default 5MB)
	* @param string $successRedirect Redirect URL or 200 / 201 status code
	* @param array $amzHeaders Array of x-amz-meta-* headers
	* @param array $headers Array of request headers or content type as a string
	* @param boolean $flashVars Includes additional "Filename" variable posted by Flash
	* @return object
	*/
	public function getHttpUploadPostParams($bucket, $uriPrefix = '', $acl = self::ACL_PRIVATE, $lifetime = 3600, $maxFileSize = 5242880, $successRedirect = "201", $amzHeaders = array(), $headers = array(), $flashVars = false) {
		// Create policy object
		$policy = new stdClass;
		$policy->expiration = gmdate('Y-m-d\TH:i:s\Z', (time() + $lifetime));
		$policy->conditions = array();
		$obj = new stdClass; $obj->bucket = $bucket; array_push($policy->conditions, $obj);
		$obj = new stdClass; $obj->acl = $acl; array_push($policy->conditions, $obj);

		$obj = new stdClass; // 200 for non-redirect uploads
		if (is_numeric($successRedirect) && in_array((int)$successRedirect, array(200, 201)))
			$obj->success_action_status = (string)$successRedirect;
		else // URL
			$obj->success_action_redirect = $successRedirect;
		array_push($policy->conditions, $obj);

		array_push($policy->conditions, array('starts-with', '$key', $uriPrefix));
		if ($flashVars) array_push($policy->conditions, array('starts-with', '$Filename', ''));
		foreach (array_keys($headers) as $headerKey)
			array_push($policy->conditions, array('starts-with', '$'.$headerKey, ''));
		foreach ($amzHeaders as $headerKey => $headerVal) {
			$obj = new stdClass; $obj->{$headerKey} = (string)$headerVal; array_push($policy->conditions, $obj);
		}
		array_push($policy->conditions, array('content-length-range', 0, $maxFileSize));
		$policy = base64_encode(str_replace('\/', '/', json_encode($policy)));
	
		// Create parameters
		$params = new stdClass;
		$params->CSSAccessKeyId = self::$__accessKey;
		$params->key = $uriPrefix.'${filename}';
		$params->acl = $acl;
		$params->policy = $policy; unset($policy);
		$params->signature = self::__getHash($params->policy);
		if (is_numeric($successRedirect) && in_array((int)$successRedirect, array(200, 201)))
			$params->success_action_status = (string)$successRedirect;
		else
			$params->success_action_redirect = $successRedirect;
		foreach ($headers as $headerKey => $headerVal) $params->{$headerKey} = (string)$headerVal;
		foreach ($amzHeaders as $headerKey => $headerVal) $params->{$headerKey} = (string)$headerVal;
		return $params;
	}

	/**
	* Create a CloudFront distribution
	*
	* @param string $bucket Bucket name
	* @param boolean $enabled Enabled (true/false)
	* @param array $cnames Array containing CNAME aliases
	* @param string $comment Use the bucket name as the hostname
	* @return array | false
	*/
	public function createDistribution($bucket, $enabled = true, $cnames = array(), $comment = '') {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('POST', '', '2008-06-30/distribution', 'cloudfront.amazonaws.com');
		$rest->data = self::getCloudFrontDistributionConfigXML($bucket.'.s3.amazonaws.com', $enabled, $comment, (string)microtime(true), $cnames);
		$rest->size = strlen($rest->data);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest = self::getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 201)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::createDistribution({$bucket}, ".(int)$enabled.", '$comment'): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		} elseif ($rest->body instanceof SimpleXMLElement)
			return self::parseCloudFrontDistributionConfig($rest->body);
		return false;
	}


	/**
	* Get CloudFront distribution info
	*
	* @param string $distributionId Distribution ID from listDistributions()
	* @return array | false
	*/
	public function getDistribution($distributionId) {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('GET', '', '2008-06-30/distribution/'.$distributionId, 'cloudfront.amazonaws.com');
		$rest = self::getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::getDistribution($distributionId): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		} elseif ($rest->body instanceof SimpleXMLElement) {
			$dist = self::parseCloudFrontDistributionConfig($rest->body);
			$dist['hash'] = $rest->headers['hash'];
			return $dist;
		}
		return false;
	}


	/**
	* Update a CloudFront distribution
	*
	* @param array $dist Distribution array info identical to output of getDistribution()
	* @return array | false
	*/
	public function updateDistribution($dist) {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('PUT', '', '2008-06-30/distribution/'.$dist['id'].'/config', 'cloudfront.amazonaws.com');
		$rest->data = self::getCloudFrontDistributionConfigXML($dist['origin'], $dist['enabled'], $dist['comment'], $dist['callerReference'], $dist['cnames']);
		$rest->size = strlen($rest->data);
		$rest->setHeader('If-Match', $dist['hash']);
		$rest = self::getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::updateDistribution({$dist['id']}, ".(int)$enabled.", '$comment'): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		} else {
			$dist = self::parseCloudFrontDistributionConfig($rest->body);
			$dist['hash'] = $rest->headers['hash'];
			return $dist;
		}
		return false;
	}


	/**
	* Delete a CloudFront distribution
	*
	* @param array $dist Distribution array info identical to output of getDistribution()
	* @return boolean
	*/
	public function deleteDistribution($dist) {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('DELETE', '', '2008-06-30/distribution/'.$dist['id'], 'cloudfront.amazonaws.com');
		$rest->setHeader('If-Match', $dist['hash']);
		$rest = self::getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::deleteDistribution({$dist['id']}): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		}
		return true;
	}


	/**
	* Get a list of CloudFront distributions
	*
	* @return array
	*/
	public function listDistributions() {
		self::$useSSL = true; // CloudFront requires SSL
		$rest = new S3Request('GET', '', '2008-06-30/distribution', 'cloudfront.amazonaws.com');
		$rest = self::getCloudFrontResponse($rest);

		if ($rest->error === false && $rest->code !== 200)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false) {
			trigger_error(sprintf("S3::listDistributions(): [%s] %s",
			$rest->error['code'], $rest->error['message']), E_USER_WARNING);
			return false;
		} elseif ($rest->body instanceof SimpleXMLElement && isset($rest->body->DistributionSummary)) {
			$list = array();
			if (isset($rest->body->Marker, $rest->body->MaxItems, $rest->body->IsTruncated)) {
				//$info['marker'] = (string)$rest->body->Marker;
				//$info['maxItems'] = (int)$rest->body->MaxItems;
				//$info['isTruncated'] = (string)$rest->body->IsTruncated == 'true' ? true : false;
			}
			foreach ($rest->body->DistributionSummary as $summary) {
				$list[(string)$summary->Id] = self::parseCloudFrontDistributionConfig($summary);
			}
			return $list;
		}
		return array();
	}


	/**
	* Get a DistributionConfig DOMDocument
	*
	* @internal Used to create XML in createDistribution() and updateDistribution()
	* @param string $bucket Origin bucket
	* @param boolean $enabled Enabled (true/false)
	* @param string $comment Comment to append
	* @param string $callerReference Caller reference
	* @param array $cnames Array of CNAME aliases
	* @return string
	*/
	public function getCloudFrontDistributionConfigXML($bucket, $enabled, $comment, $callerReference = '0', $cnames = array()) {
		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;
		$distributionConfig = $dom->createElement('DistributionConfig');
		$distributionConfig->setAttribute('xmlns', 'http://cloudfront.amazonaws.com/doc/2008-06-30/');
		$distributionConfig->appendChild($dom->createElement('Origin', $bucket));
		$distributionConfig->appendChild($dom->createElement('CallerReference', $callerReference));
		foreach ($cnames as $cname)
			$distributionConfig->appendChild($dom->createElement('CNAME', $cname));
		if ($comment !== '') $distributionConfig->appendChild($dom->createElement('Comment', $comment));
		$distributionConfig->appendChild($dom->createElement('Enabled', $enabled ? 'true' : 'false'));
		$dom->appendChild($distributionConfig);
		return $dom->saveXML();
	}


	/**
	* Parse a CloudFront distribution config
	*
	* @internal Used to parse the CloudFront DistributionConfig node to an array
	* @param object &$node DOMNode
	* @return array
	*/
	public function parseCloudFrontDistributionConfig(&$node) {
		$dist = array();
		if (isset($node->Id, $node->Status, $node->LastModifiedTime, $node->DomainName)) {
			$dist['id'] = (string)$node->Id;
			$dist['status'] = (string)$node->Status;
			$dist['time'] = strtotime((string)$node->LastModifiedTime);
			$dist['domain'] = (string)$node->DomainName;
		}
		if (isset($node->CallerReference))
			$dist['callerReference'] = (string)$node->CallerReference;
		if (isset($node->Comment))
			$dist['comment'] = (string)$node->Comment;
		if (isset($node->Enabled, $node->Origin)) {
			$dist['origin'] = (string)$node->Origin;
			$dist['enabled'] = (string)$node->Enabled == 'true' ? true : false;
		} elseif (isset($node->DistributionConfig)) {
			$dist = array_merge($dist, self::parseCloudFrontDistributionConfig($node->DistributionConfig));
		}
		if (isset($node->CNAME)) {
			$dist['cnames'] = array();
			foreach ($node->CNAME as $cname) $dist['cnames'][(string)$cname] = (string)$cname;
		}
		return $dist;
	}


	/**
	* Grab CloudFront response
	*
	* @internal Used to parse the CloudFront S3Request::getResponse() output
	* @param object &$rest S3Request instance
	* @return object
	*/
	public function getCloudFrontResponse(&$rest) {
		$rest->getResponse();
		if ($rest->response->error === false && isset($rest->response->body) &&
		is_string($rest->response->body) && substr($rest->response->body, 0, 5) == '<?xml') {
			$rest->response->body = simplexml_load_string($rest->response->body);
			// Grab CloudFront errors
			if (isset($rest->response->body->Error, $rest->response->body->Error->Code,
			$rest->response->body->Error->Message)) {
				$rest->response->error = array(
					'code' => (string)$rest->response->body->Error->Code,
					'message' => (string)$rest->response->body->Error->Message
				);
				unset($rest->response->body);
			}
		}
		return $rest->response;
	}

}

final class S3Request extends CloudStorageSerivceRequest {
	
	public $amzHeaders = array(); 

	/**
	* Set x-amz-meta-* header
	*
	* @param string $key Key
	* @param string $value Value
	* @return void
	*/
	public function setAmzHeader($key, $value) {
		$this->amzHeaders[$key] = $value;
	}

}
