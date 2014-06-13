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
	$data = "/qiniu.php\n".file_get_contents('php://input');
	return true;
	//return URLSafeBase64Encode(hash_hmac('sha1',$data,C("secretKey"), true)) == $auth[1];
}

if(!isQiniuCallback()) {
	echo '{"success": false}';
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

$s = "";
foreach($_POST as $k => $v) {
	$s.= $k.':'.$v.',';
}
throw new Exception($s);

$origin = $_POST['origin'];
if($origin == 'developer') {
	$dbName = 'cms_test';
} else {
	$websiteId = $_POST['websiteId'];
	$ownerId = $_POST['ownerId'];
	$groupId = $_POST['groupId'];
	
	$db = $m->selectDb('account_fucms');
	$siteArr = $db->website->findOne(array('_id' => $websiteId));
	
	if(is_null($siteArr)) {
		echo "not-found";
		exit(0);
	}
	if(!$siteArr['active']) {
		echo "expired";
		exit(0);
	}
	
	$server = $db->server->findOne(array('_id' => $siteArr['server']['$id']));
	$internalIpAddress = $server['internalIpAddress'];
	
	$host		= $server['internalIpAddress'];
	$username	= $server['user'];
	$password	= $server['pass'];
	$m = new MongoClient($host, array(
			'username' => $username,
			'password' => $password,
			'db' => 'admin')
	);
	
	$filetype = $_POST['filetype'];
	$isImage = false;
	if(in_array($fileType, array('image/jpeg', 'image/gif', 'image/png'))) {
		$isImage = true;
	}
	
	$dbName = 'cms_'.$siteArr['globalSiteId'];
}

$db = $m->selectDb($dbName);
$file = $db->file->insert(array(
	'ownerId' => $ownerId,
	'groupId' => $groupId,
	'filename' => $_POST['filename'],
	'urlname' => $_POST['urlname'],
	'size' => $_POST['size'],
	'storage' => 'qiniu',
	'filetype' => $filetype,
	'isImage' => $isImage
));
echo '{"success":true,"name":"'.$_POST['filename'].'"}';
