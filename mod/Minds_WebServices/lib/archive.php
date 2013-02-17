<?php
/**
 * Minds Web Services
 * Archive
 * 
 * @package Webservice
 * @author Mark Harding (mark@minds.com)
 *
 */
 
require_once(dirname(dirname(dirname(__FILE__))) ."/kaltura_video/kaltura/api_client/includes.php");

 
 /**
 * Web service to create a blank entry
 *
 * @return array (entry id, ks, uploadtoken)
 */
function archive_kaltura_create($filename, $filesize, $filetype) {
			
	$user = elgg_get_logged_in_user_entity();
	
	if(file_get_simple_type($filetype) == 'audio'){
		$mime_type = KalturaMediaType_AUDIO;
	} elseif(file_get_simple_type($filetype) == 'video'){
		$mime_type = KalturaMediaType_VIDEO;
	}
		
	$kmodel = KalturaModel::getInstance();
	$mediaEntry = new KalturaMediaEntry();
	$mediaEntry->name = 'Temporary Entry ID: '.time();
	$mediaEntry->mediaType = $mime_type;
	$mediaEntry->description = '';
	$mediaEntry->adminTags = KALTURA_ADMIN_TAGS;
	$mediaEntry = $kmodel->addMediaEntry($mediaEntry);
	
	$kmodel = KalturaModel::getInstance();
	$ks = $kmodel->getClientSideSession();
	
	$uploadToken = new KalturaUploadToken();
	$uploadToken->fileName = $filename;
	$uploadToken->fileSize = $filesize;
	$uploadToken = $kmodel->addUploadToken($uploadToken);

	$return = array( 'entryID'=> $mediaEntry->id,
					 'ks' => $ks,
					 'uploadToken' => $uploadToken->id
					);
					
	return $return;
}

expose_function('archive.kaltura.create',
				"archive_kaltura_create",
				array(	'filename' => array ('type' => 'string', 'required' => true),
					'filesize' => array ('type' => 'int', 'required' => true),
					'filetype' => array ('type' => 'string', 'required' => true),
					),
				"Create a kaltura entry",
				'POST',
				true,
				true);
				
/**
 * Web services to attach content to entry
 * 
 * @return bool true/false
 */
function archive_kaltura_link($entryID, $uploadToken) {
		
	$kmodel = KalturaModel::getInstance();
	
	$resource = new KalturaUploadedFileTokenResource();
	$resource->token = $uploadToken;
	$result = $kmodel->addContent($entryId, $resource);
	
	return $result;
}
expose_function('archive.kaltura.link',
				"archive_kaltura_link",
				array(	'entryID' => array ('type' => 'string', 'required' => true),
						'uploadToken' => array ('type' => 'string', 'required' => true),
					),
				"Link a kaltura upload token to an upload token",
				'POST',
				true,
				true);

/**
 * Web services to save an entry in to elgg
 * 
 * @return bool true/false
 */
function archive_kaltura_save($entryID, $title, $description, $tags, $license, $access) {
	
	$kmodel = KalturaModel::getInstance();

	$entry = $kmodel->getEntry($entryID);
	
	$ob = kaltura_get_entity($entryID);
	if(!$ob){
		$owner_guid = elgg_get_logged_in_user_guid();
	}
	
	$entry->name = strip_tags($title);
	$entry->description = $description;
	$entry->tags = $tags;

	$kmodel = KalturaModel::getInstance();
	$mediaEntry = new KalturaMediaEntry();
	$mediaEntry->name = $entry->name;
	$mediaEntry->description = $entry->description;
	$mediaEntry->tags = $entry->tags;
	$mediaEntry->adminTags = KALTURA_ADMIN_TAGS;
	$entry = $kmodel->updateMediaEntry($entryID,$mediaEntry);

		
	$tagarray = string_to_tag_array($tags);
	
	$ob = kaltura_update_object($entry,null,$access,$owner_guid,null,true, array('license'=> $license, 'thumbnail_sec'=>$thumbnail_sec));
	
	return true;
}
expose_function('archive.kaltura.save',
				"archive_kaltura_save",
				array(	'entryID' => array ('type' => 'string', 'required' => true),
						'title' => array ('type' => 'string', 'required' => true),
						'description' => array ('type' => 'string', 'required' => false),
						'tags' => array ('type' => 'string', 'required' => false, 'default'=>''),
						'license' => array ('type' => 'string', 'required' => true),
						'access' => array ('type' => 'int', 'required' => false, 'default'=> get_default_access()),
					),
				"save an entry to elgg",
				'POST',
				true,
				true);
				
/**
 * Retrieve archive list
 * 
 * @param string $contet eg. own, friends or all (default all)
 * @param int $limit  (optional) default 10
 * @param int $offset (optional) default 0
 * @param string $username (optional) the username of the user default loggedin user
 *
 * @return array $return Array of videos
 */
function archive_get_list($context, $type, $limit = 10, $offset = 0, $username){
	if(!$username) {
			$user = elgg_get_logged_in_user_entity();
		} else {
			$user = get_user_by_username($username);
			if (!$user) {
				throw new InvalidParameterException('registration:usernamenotvalid');
			}
		}
		
		if($type == 'all'){
			$subtypes = array('kaltura_video', 'image', 'album', 'file');
		}else{
			$subtypes = $type;
		}
		
		if($context == 'all'){
			$media = elgg_get_entities(array(
										'type' => 'object',
										'subtypes' => $subtypes,
										'limit' => $limit,
										'offset' => $offset,
										'full_view' => FALSE,
										));
		} elseif( $context == 'mine' || $context == 'user'){
			$media = elgg_get_entities(array(
										'type' => 'object',
										'subtypes' => $subtypes,
										'owner_guid' => $user->guid,
										'limit' => $limit,
										'offset' => $offset,
										'full_view' => FALSE,
										));
		} elseif( $context == 'friends'){
			$media = get_user_friends_objects($user->guid, $subtypes, $limit, $offset); 
		}
		
		if($media){
			foreach($media as $single ) {
				$item['guid'] = $single->guid;
				$item['title'] = $single->title;
				
				if($type == 'kaltura_video'){
					$item['video_id'] = $single->kaltura_video_id;
					$item['thumbnail'] = $single->kaltura_video_thumbnail;
				} else{
					$item['thumbnail'] = $single->iconURL('large');
				}
	
				$owner = get_entity($single->owner_guid);
				$item['owner']['guid'] = $owner->guid;
				$item['owner']['name'] = $owner->name;
				$item['owner']['username'] = $owner->username;
				$item['owner']['avatar_url'] = $owner->getIconUrl('small');
				
				$item['container_guid'] = $single->container_guid;
				$item['access_id'] = $single->access_id;
				$item['time_created'] = (int)$single->time_created;
				$item['time_updated'] = (int)$single->time_updated;
				$item['last_action'] = (int)$single->last_action;
				$return[] = $video;
			}
	
		} else {
			$msg = elgg_echo('kalturavideo:none');
			throw new InvalidParameterException($msg);
		}
	
	return $return;
}
expose_function('archive.list',
				"archive_get_list",
				array(
						'context' => array ('type' => 'string', 'required' => false, 'default' => 'all'),
						'type' => array ('type' => 'string', 'required' => false, 'default' => 'all'),
					  	'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
					  	'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
					   	'username' => array ('type' => 'string', 'required' => false),
					),
				"Get list of videos",
				'GET',
				false,
				false);
