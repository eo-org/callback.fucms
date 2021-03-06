<?php
$localConfig = include '../../config/local.php';

function isQiniuCallback() {
	$h = apache_request_headers();
	if(isset($h['Authorization']) && !empty($h['Authorization'])) {
		$authstr = $h['Authorization'];
	} else {
		return false;
	}

	if(strpos($authstr,"QBox ") != 0) {
		return false;
	}
	$auth = explode(":", substr($authstr,5));
	if(sizeof($auth)!=2) {
		return false;
	}
// 	if(sizeof($auth)!=2 || $auth[0]!=C('accessKey')){
// 		return false;
// 	}
	$data = "/fileupload/qiniu.php\n".file_get_contents('php://input');
	return true;
	//return URLSafeBase64Encode(hash_hmac('sha1',$data,C("secretKey"), true)) == $auth[1];
}

if(!isQiniuCallback()) {
	echo '{"success": false, "error": "callback orgin error"}';
	exit(1);
}

define("BASE_PATH", $localConfig['env']['base_path']);

$host		= $localConfig['env']['account_fucms_db']['host'];
$username	= $localConfig['env']['account_fucms_db']['username'];
$password	= $localConfig['env']['account_fucms_db']['password'];

$m = new MongoClient($host, array(
	'username' => $username,
	'password' => $password,
	'db' => 'admin')
);

$origin = $_POST['origin'];
$websiteId = $_POST['websiteId'];
$ownerId = $_POST['ownerId'];
$groupId = $_POST['groupId'];
$urlname = $_POST['urlname'];
$urlnameArr = explode('/', $urlname);
$urlname = $urlnameArr[1];
$filetype = $_POST['filetype'];
$isImage = false;
if(in_array($filetype, array('image/jpeg', 'image/gif', 'image/png'))) {
	$isImage = true;
}

if($origin == 'developer') {
	$dbName = 'cms_test';
} else {
	$db = $m->selectDb('account_fucms');
	$siteArr = $db->website->findOne(array('_id' => new MongoId($websiteId)));
	
	if(is_null($siteArr)) {
		echo '{"success": false, "error": "site not-found"}';
		exit(0);
	}
	if(!$siteArr['active']) {
		echo '{"success": false, "error": "site expired"}';
		exit(0);
	}
	
	$server = $db->server->findOne(array('_id' => new MongoId($siteArr['server']['$id'])));
	$internalIpAddress = $server['internalIpAddress'];
	
	$host		= $server['internalIpAddress'];
	$username	= $server['user'];
	$password	= $server['pass'];
	$m = new MongoClient($host, array(
			'username' => $username,
			'password' => $password,
			'db' => 'admin')
	);
	
	$dbName = 'cms_'.$siteArr['globalSiteId'];
}

$db = $m->selectDb($dbName);
$file = $db->file->update(
	array("urlname" => $urlname),
	array('$set' => array(
		'ownerId' => $ownerId,
		'groupId' => $groupId,
		'size' => $_POST['size'],
		'urlname' => $urlname,
		'filetype' => $filetype,
		'isImage' => $isImage,
		'status' => 'uploaded'
	))
);
echo '{"success":true,"name":"'.$_POST['filename'].'"}';
