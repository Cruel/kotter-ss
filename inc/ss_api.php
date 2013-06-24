<?php

// Config.php - SS_SERVER SS_USER SS_PASS
require_once("config.php");
define("SS_BASEURI", "/1/management");

class AnchorAPI {

	private $token, $last_error;
	
	public function __construct() {
		//
	}

	public static function slugify($text){
		// replace non letter or digits by -
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		// trim
		$text = trim($text, '-');
		// transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		// lowercase
		$text = strtolower($text);
		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);
		if (empty($text))
			return 'n-a';
		return $text;
	}

	public function errorMsg(){
		$e = $this->last_error;
		$this->last_error = NULL;
		return $e;
	}

	private function request($url, $params=NULL){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if ($params){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($ch);
		curl_close($ch);
		// var_dump($result);
		return json_decode($result, true);
	}

	private function apiRequest($uri, $params=NULL){
		if (!isset($this->token)){
			$this->token = $this->getToken();
		}
		$url = SS_SERVER.SS_BASEURI.$uri."?session_token=".$this->token;
		$result = $this->request($url, $params);
		// var_dump($result);
		// var_dump($params);
		if ($result['code'] == "success"){
			return $result;
		} else {
			$this->last_error = $result['message'];
			return false;
		}
		
	}

	// Get session token needed for api calls
	private function getToken() {
		$req = $this->request(SS_SERVER."/1/auth/", array(
			"username" => SS_USER,
			"password" => SS_PASS
		));
		if ($req['success']) {
			return $req['session_token'];
		}
	}

	public function createUser($company_id, $first_name, $last_name,
			$email, $password, $mobile_provider_id=NULL, $mobile_phone=NULL){
		
		$params = compact("company_id", "first_name", "last_name", "email", "password");
		$params['site_admin'] = "true";
		if (!empty($mobile_provider_id)){
			$params['mobile_phone'] = $mobile_phone;
			$params['mobile_service_provider_id'] = $mobile_provider_id;
		}
		$result = $this->apiRequest('/person/create/', $params);
		// return $result['code'] == "success";
		return $result;
	}

	public function deleteUser($user_id){
		$result = $this->apiRequest('/person/delete/', array('id'=>$user_id));
		return $result;
	}

	public function createOrganization($name, $contact_email){
		$slug = AnchorAPI::slugify($name);
		//https://ss.kotter.net:510/1/management/policy/1/
		$policy = '{"space_quota": "536870912000", "max_file_size": "5120", "purge_deleted": true, "trim_revisions": false, "excluded_extensions": ".$$,.$db,.113,.3g2,.3gp,.3gp2,.3gpp,.3mm,.a,.abf,.abk,.afm,.ani,.ann,.asf,.avi,.avs,.bac,.bak,.bck,.bcm,.bdb,.bdf,.bkf,.bkp,.bmk,.bsc,.bsf,.cab,.cf1,.chm,.chq,.chw,.cnt,.com,.cpl,.cur,.dev,.dfont,.dll,.dmp,.drv,.dv,.dvd,.dvr,.dvr-ms,.eot,.evt,.exe,.ffa,.ffl,.ffo,.ffx,.flc,.flv,.fnt,.fon,.ftg,.fts,.fxp,.gid,.grp,.hdd,.hlp,.hxi,.hxq,.hxr,.hxs,.ico,.idb,.idx,.ilk,.img,.inf,.ini,.ins,.ipf,.iso,.isp,.its,.jar,.jse,.kbd,.kext,.key,.lex,.lib,.library-ms,.lnk,.log,.lwfn,.m1p,.m1v,.m2p,.m2v,.m4v,.mem,.mkv,.mov,.mp2,.mp2v,.mp4,.mpe,.mpeg,.mpg,.mpv,.mpv2,.msc,.msi,.msm,.msp,.mst,.ncb,.nt,.nvram,.o,.obj,.obs,.ocx,.old,.ost,.otf,.pch,.pf,.pfa,.pfb,.pfm,.pnf,.pol,.pref,.prf,.prg,.prn,.pvs,.pwl,.qt,.rdb,.reg,.rll,.rox,.sbr,.scf,.scr,.sdb,.shb,.suit,.swf,.swp,.sys,.theme,.tivo,.tmp,.tms,.ttc,.ttf,.v2i,.vbe,.vga,.vgd,.vhd,.video,.vmc,.vmdk,.vmsd,.vmsn,.vmx,.vxd,.win,.wpk", "user_purge_deleted": true, "user_create_shares": false, "purge_deleted_frequency": "90", "user_trim_revisions": true, "user_create_backups": true, "trim_revisions_x": ""}';
		$parent_id = 1;
		$result = $this->apiRequest('/organization/create/', compact(
			"name", "contact_email", "slug", "policy", "parent_id"
		));
		return $result;
	}

	// public function upgradeOrganization($id){
	// 	$policy = FULL_POLICY;
	// 	$result = $this->apiRequest('/organization/update/', compact(
	// 		"id", "policy"
	// 	));
	// 	return $result;
	// }

	public function deleteOrganization($id){
		$result = $this->apiRequest('/organization/delete/', array('id'=>$id));
		return $result;
	}

	public function getOrganizationPersonCount($id){
		$result = $this->apiRequest("/organization/".$id."/persons/");
		return $result['result']['total_persons'];
		// return $result;
	}

}

?>