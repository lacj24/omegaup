<?php

/**
 *  GroupController
 *
 * @author joemmanuel
 */
require_once 'SessionController.php';

class GroupController extends Controller {
	
	/**
	 * New group 
	 * 
	 * @param Request $r
	 */
	public static function apiCreate(Request $r) {		
		self::authenticateRequest($r);
		
		Validators::isStringNonEmpty($r["name"], "name", true);
		Validators::isStringNonEmpty($r["description"], "description", false);
						
		try {
			$group = new Groups(array(
				"owner_id" => $r["current_user_id"],
				"name" => $r["name"],
				"description" =>$r["description"]
			));
			
			GroupsDAO::save($group);
		} catch(Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}
		
		return array("status" => "ok");
	}
	
	/**
	 * Validate group param
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 * @throws InvalidParameterException
	 * @throws ForbiddenAccessException
	 */
	private static function validateGroup(Request $r) {
		self::authenticateRequest($r);
		
		Validators::isNumber($r["group_id"], "group_id");
		try {
			$r["group"] = GroupsDAO::getByPK($r["group_id"]);						
		} catch (Exception $ex) {
			throw new InvalidDatabaseOperationException($ex);
		}
		
		if (is_null($r["group"])) {
			throw new InvalidParameterException("parameterNotFound", "Group");
		}
		
		if (!Authorization::IsGroupOwner($r["current_user_id"], $r["group"])) {
			throw new ForbiddenAccessException();
		}
	}
		
	/**
	 * Validate common params for these APIs
	 * 
	 * @param Request $r
	 */
	private static function validateGroupAndOwner(Request $r) {
		self::validateGroup($r);		
		$r["user"] = self::resolveTargetUser($r);
	}
	
	/**
	 * Add user to group
	 * 
	 * @param Request $r
	 */
	public static function apiAddUser(Request $r) {
		self::validateGroupAndOwner($r);
						
		try {
			$groups_user = new GroupsUsers(array(
				"group_id" => $r["group_id"],
				"user_id" => $r["user"]->user_id
			));
			GroupsUsersDAO::save($groups_user);
		} catch (Exception $ex) {
			throw new InvalidDatabaseOperationException($ex);
		}
		
		return array("status" => "ok");
	}
	
	/**
	 * Remove user from group
	 * 
	 * @param Request $r
	 */
	public static function apiRemoveUser(Request $r) {
		self::validateGroupAndOwner($r);
		
		try {
			$key = new GroupsUsers(array(
				"group_id" => $r["group_id"],
				"user_id" => $r["user"]->user_id 
			));
			
			// Check user is actually in group
			$groups_user = GroupsUsersDAO::search($key);			
			if (count($groups_user) === 0) {				
				throw new InvalidParameterException("parameterNotFound", "User");
			}
			
			GroupsUsersDAO::delete($key);			
			
		} catch (ApiException $ex) {
			throw $ex;
		} catch (Exception $ex) {
			throw new InvalidDatabaseOperationException($ex);
		}
		
		return array("status" => "ok");
	}
	
	/**
	 * Returns a list of groups by owner
	 * 
	 * @param Request $r
	 */
	public static function apiList(Request $r) {
		self::authenticateRequest($r);
		
		$response = array();
		$response["groups"] = array();
		
		try {
			$groups = GroupsDAO::search(new Groups(array(
				"owner_id" => $r["current_user_id"]
			)));
			
			foreach ($groups as $group) {
				$response["groups"][] = $group->asArray();
			}					
			
		} catch (Exception $ex) {
			throw new InvalidDatabaseOperationException($ex);
		}
		
		$response["status"] = "ok";
		return $response;
	}
	
	/**
	 * Details of a group (users in a group)
	 * 
	 * @param Request $r
	 */
	public static function apiDetails(Request $r) {
		self::validateGroupAndOwner($r);
		
		$response = array();
		$response["group"] = array();
		$response["users"] = array();
		
		try {
			$response["group"] = $r["group"]->asArray();
			
			$userGroups = GroupsUsersDAO::search(new GroupsUsers(array(
				"group_id" => $r["group_id"]
			)));
			
			foreach ($userGroups as $userGroup) {
				$userProfile = array();
				$r["user"] = UsersDAO::getByPK($userGroup->user_id);				
				UserController::getProfile($r, $userProfile);
				
				$response["users"][] = $userProfile;
			}
			
		} catch (Exception $ex) {
			throw new InvalidDatabaseOperationException($ex);
		}
		
		$response["status"] = "ok";
		return $response;
	}
}
