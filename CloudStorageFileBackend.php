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

class CloudStorageFileBackend extends FSFileBackend {
	
	public function __construct( array $config ) {
		parent::__construct( $config );
	}
	
	/**
	 * Check if a given path is a "mwstore://" path.
	 * This does not do any further validation or any existence checks.
	 *
	 * @param string $path
	 * @return bool
	 */
	public static function isCloudStoragePath( $path ) {
		return ( strpos( $path, 'gs://' ) === 0 ||
				strpos( $path, 'file://' ) === 0 
				/* amazon file repo is not implemented
				/* ||
					strpos( $path, 'aws://' ) === 0 */);
	}
	
	public static function splitCloudStoragePath( $storagePath ) {
		if ( self::isCloudStoragePath( $storagePath ) ) {
			return array( '', null, $storagePath ); // e.g. "backend/container"
			// Remove the "gs://" or file:// prefix and split the path
// 			$pos = strpos( $path, '//' ) + 1; // the start of real part
// 			$parts = explode( '/', substr( $storagePath, $pos ), 3 );
// 			if ( count( $parts ) >= 2 && $parts[0] != '' && $parts[1] != '' ) {
// 				if ( count( $parts ) == 3 ) {
// 					return $parts; // e.g. "backend/container/path"
// 				} else {
// 					return array( $parts[0], $parts[1], '' ); // e.g. "backend/container"
// 				}
// 			}
		}
	
		return array( null, null, null );
	}
	
	/**
	 * Splits a storage path into an internal container name,
	 * an internal relative file name, and a container shard suffix.
	 * Any shard suffix is already appended to the internal container name.
	 * This also checks that the storage path is valid and within this backend.
	 *
	 * If the container is sharded but a suffix could not be determined,
	 * this means that the path can only refer to a directory and can only
	 * be scanned by looking in all the container shards.
	 *
	 * @param string $storagePath
	 * @return array (container, path, container suffix) or (null, null, null) if invalid
	 */
	protected function resolveCloudStoragePath( $storagePath ) {
		list( $backend, $container, $relPath ) = self::splitCloudStoragePath( $storagePath, false );
		if ( $backend === $this->name ) { // must be for this backend
			$relPath = self::normalizeContainerPath( $relPath );
			if ( $relPath !== null ) {
				// Get shard for the normalized path if this container is sharded
				$cShard = $this->getContainerShard( $container, $relPath );
				// Validate and sanitize the relative path (backend-specific)
				$relPath = $this->resolveContainerPath( $container, $relPath );
				if ( $relPath !== null ) {
					// Prepend any wiki ID prefix to the container name
					$container = $this->fullContainerName( $container );
					if ( self::isValidContainerName( $container ) ) {
						// Validate and sanitize the container name (backend-specific)
						$container = $this->resolveContainerName( "{$container}{$cShard}" );
						if ( $container !== null ) {
							return array( $container, $relPath, $cShard );
						}
					}
				}
			}
		}
	
		return array( null, null, null );
	}
	
	protected function resolveCloudStoragePathReal( $storagePath ) {
		list( $container, $relPath, $cShard ) = $this->resolveCloudStoragePath( $storagePath );
		if ( $cShard !== null && substr( $relPath, -1 ) !== '/' ) {
			return array( $container, $relPath );
		}
	
		return array( null, null );
	}
	
	/**
	 * Get the absolute file system path for a storage path
	 *
	 * @param string $storagePath Storage path
	 * @return string|null
	 */
	protected function resolveToFSPath( $storagePath ) {
		/*
		 * no need to do anything complicated
		 */
		return $storagePath;
		
// 		list( $fullCont, $relPath ) = $this->resolveCloudStoragePathReal( $storagePath );
// 		if ( $relPath === null ) {
// 			return null; // invalid
// 		}
// 		list( , $shortCont, ) = FileBackend::splitStoragePath( $storagePath );
// 		$fsPath = $this->containerFSRoot( $shortCont, $fullCont ); // must be valid
// 		if ( $relPath != '' ) {
// 			$fsPath .= "/{$relPath}";
// 		}
	
// 		return $fsPath;
	}
	
}