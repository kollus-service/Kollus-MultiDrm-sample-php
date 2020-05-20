<?php
	date_default_timezone_set('UTC');

	require 'vendor/autoload.php';
	use Firebase\JWT\JWT;

	// Inka setting key
	define('INKA_ACCESS_KEY', 'INKA_ACCESS_KEY'); // inkaDRM Access Key
	define('INKA_SITE_KEY', 'INKA_SITE_KEY');     // inkaDRM Site Key
	define('INKA_SITE_ID', 'INKA_SITE_ID');		  // inkaDRM Site ID
	define('INKA_IV', 'INKA_IV');                 // inkaDRM AES 256 Encryption Initialization

	// Kollus setting key
	define('KOLLUS_SECURITY_KEY', 'KOLLUS_SECURITY_KEY'); //Kollus Account Key
	define('KOLLUS_CUSTOM_KEY', 'KOLLUS_CUSTOM_KEY');     //Kollus Custom User Key


	$clientUserId = 'CLIENT_USER_ID'; // Client User ID
	$cid = 'CONTENTS_ID';             // Multi DRM Contents ID, Kollus Upload File Key
	$mckey = 'MEDIA_CONTENT_KEY';     // Kollus MediaContentKey

	$jwt = createKollusJWT($clientUserId, $mckey, $cid);
?>

<?php
	// function - 브라우저 체크 
	function getStreamingType() {
		//echo $_SERVER['HTTP_USER_AGENT'];
		$arrBrowsers = ["CriOS","Edge","Firefox", "Chrome", "Safari", "Opera", "MSIE", "Trident"];
		$agent = $_SERVER['HTTP_USER_AGENT'];
		$userBrowser = '';
		
		foreach ($arrBrowsers as $browser) {
			if (strpos($agent, $browser) !== false) {
				$userBrowser = $browser;
				break;
			}
		}
		switch ($userBrowser) {
		case 'MSIE':
			$drmType = "PlayReady";
			$streamingType = "dash";
			break;
		case 'Trident':
			$drmType = "PlayReady";
			$streamingType = "dash";
			break;
		case 'Edge':
			$drmType = "PlayReady";
			$streamingType = "dash";
			break;
		case 'Chrome':
			$drmType = "Widevine";
			$streamingType = "dash";
			break;
		case 'Firefox':
			$drmType = "Widevine";
			$streamingType = "dash";
			break;
		case 'Opera':
			$drmType = "PlayReady";
			$streamingType = "dash";
			break;
		case 'Safari':
			$drmType = "FairPlay";
			$streamingType = "hls";
			break;
		case 'CriOS':
			$drmType = "FairPlay";
			$streamingType = "hls";
			break;
		}

		//echo '<br> drmType : ' .$drmType;
		//echo '<br> streamingType : ' .$streamingType;

		return [$drmType, $streamingType];
	}

	// function - 콜러스 웹토큰 생성
	function createKollusJWT ($clientUserId, $mckey, $cid) {
		$payload = (object)array(
			'expt' => time() + 86400, // 5 min
			'cuid' => $clientUserId,
			'mc' => array(
				array(
					'mckey' => $mckey,
					'drm_policy'=>array(
						'kind'=>'inka',
						'streaming_type'=>getStreamingType()[1],
						'data'=>array(
							'license_url'=>'https://license.pallycon.com/ri/licenseManager.do',
							'certificate_url'=>'https://license.pallycon.com/ri/fpsKeyManager.do?siteId='.INKA_SITE_ID,
							'custom_header'=>array(
								'key'=>'pallycon-customdata-v2',
								'value'=> createInkaPayload($clientUserId, $cid),
							)
						)
					)
				)
			),
		);

		return JWT::encode($payload, KOLLUS_SECURITY_KEY);
	}


	// function - inkaDRM 페이로드 생성 
	function createInkaPayload($clientUserId, $cid) {
		$timestamp = date("Y-m-d")."T".date("H:i:s")."Z";  // inkaDRM TimeStemp
		$drmType = getStreamingType()[0];                  // inkaDRM DRM Type

		// step1 - 설정 값 입력
		$token = array(
			'playback_policy'=> 
				array(
					'limit' => true,
					'persistent' => false,
					'duration' => 86400
				),
			'allow_mobile_abnormal_device' => false,
			'playready_security_level' => 0
		);

		// step2 - 라이선스 룰 암호화
		$token = json_encode($token);
		$token = base64_encode(openssl_encrypt($token,'AES-256-CBC', INKA_SITE_KEY, OPENSSL_RAW_DATA, INKA_IV));

		// step3 - 해시 값 생성
		$hash = INKA_ACCESS_KEY.$drmType.INKA_SITE_ID.$clientUserId.$cid.$token.$timestamp;
		$hash = base64_encode(hash("sha256", $hash, true));

		// step4 - 라이선스 토큰 생성
		$inka_payload = array(
			'drm_type' => $drmType,
			'site_id' => INKA_SITE_ID,
			'user_id' => $clientUserId,
			'cid' => $cid,
			'token' => $token,
			'timestamp' => $timestamp,
			'hash' => $hash
		);

		$inka_payload = json_encode($inka_payload);
		$inka_payload = base64_encode($inka_payload);

		//echo '<br> inka_payload : ' .$inka_payload;
		return $inka_payload;
	}
?>

<style>
.countsort {
	position : relative;
	width : 100%;
	height : 0;
	padding-bottom : 56.25%;
}

.video {
	position : absolute;
	top : 0;
	left : 0;
	width : 100%;
	height : 100%;
}
</style>

<html lang="ko">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0,maximum-scale=1.0" />

</head>
<body>
<div class="countsort">

<iframe id="iframe" src="https://v.kr.kollus.com/s?jwt=<?php echo $jwt;?>&custom_key=<?php echo KOLLUS_CUSTOM_KEY;?>&player_version=html5" allowfullscreen webkitallowfullscreen mozallowfullscreen allow="encrypted-media" class="video"></iframe>
</div>
</body>
</html>