<?php
date_default_timezone_set('UTC');

require 'vendor/autoload.php';
use Firebase\JWT\JWT;

// Inka設定キー
define('INKA_ACCESS_KEY', 'INKA_ACCESS_KEY'); // inkaDRMアクセスキー
define('INKA_SITE_KEY', 'INKA_SITE_KEY');     // inkaDRMサイトキー
define('INKA_SITE_ID', 'INKA_SITE_ID');		  // inkaDRMサイトID
define('INKA_IV', '0123456789abcdef');        // inkaDRM AES 256暗号化初期化ベクトル

// Kollus設定キー
define('KOLLUS_SECURITY_KEY', 'KOLLUS_SECURITY_KEY'); // Kollusアカウントキー
define('KOLLUS_CUSTOM_KEY', 'KOLLUS_CUSTOM_KEY');     // Kollusカスタムユーザーキー


$clientUserId = 'CLIENT_USER_ID'; // クライアントユーザーID
$cid = 'CONTENTS_ID';             // マルチDRMコンテンツID、Kollusアップロードファイルキー
$mckey = 'MEDIA_CONTENT_KEY';     // Kollusメディアコンテンツキー

// Kollus JWT生成
$jwt = createKollusJWT($clientUserId, $mckey, $cid);
?>

<?php
// ブラウザチェック関数
function getStreamingType()
{
	//echo $_SERVER['HTTP_USER_AGENT'];
	//$arrBrowsers = ["CriOS","Edge","Firefox", "Chrome", "Safari", "Opera", "MSIE", "Trident"];
	// ブラウザリスト
	$arrBrowsers = ["CriOS", "Edge", "Edg", "Firefox", "Chrome", "Safari", "Opera", "MSIE", "Trident"];
	$agent = $_SERVER['HTTP_USER_AGENT'];
	$userBrowser = '';

	// ブラウザ検出
	foreach ($arrBrowsers as $browser) {
		if (strpos($agent, $browser) !== false) {
			$userBrowser = $browser;
			break;
		}
	}

	// ブラウザ別DRMタイプおよびストリーミングタイプ設定
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
		case 'Edg':
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
			$drmType = "Widevine";
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

	// Mac Edge例外処理
	if (strpos($agent, "Macintosh") && strpos($agent, "Edg")) {
		$drmType = "Widevine";
		$streamingType = "dash";
	}

	//echo '<br> drmType : ' .$drmType;
	//echo '<br> streamingType : ' .$streamingType;

	return [$drmType, $streamingType];
}

// Kollusウェブトークン生成関数
function createKollusJWT($clientUserId, $mckey, $cid)
{
	// JWTペイロード構成
	$payload = (object) array(
		'expt' => time() + 86400, // 5 min
		'cuid' => $clientUserId,  // クライアントユーザーID
		'mc' => array(
			array(
				'mckey' => $mckey,
				'drm_policy' => array(
					'kind' => 'inka',
					'streaming_type' => getStreamingType()[1],
					'data' => array(
						'license_url' => 'https://license.pallycon.com/ri/licenseManager.do',
						'certificate_url' => 'https://license.pallycon.com/ri/fpsKeyManager.do?siteId=' . INKA_SITE_ID,
						'custom_header' => array(
							'key' => 'pallycon-customdata-v2',
							'value' => createInkaPayload($clientUserId, $cid),
						)
					)
				)
			)
		),
	);

	// JWTエンコード
	return JWT::encode($payload, KOLLUS_SECURITY_KEY);
}


// inkaDRMペイロード生成関数
function createInkaPayload($clientUserId, $cid)
{
	$timestamp = date("Y-m-d") . "T" . date("H:i:s") . "Z";  // inkaDRM TimeStemp
	$drmType = getStreamingType()[0];                          // inkaDRM DRM Type

	// ステップ1 - 設定値入力
	if ($drmType == 'Widevine') {
		// Widevineポリシー設定
		$token = array(
			'policy_version' => 2,
			'playback_policy' =>
				array(
					'limit' => true,
					'persistent' => false,
					'duration' => 86400  // 再生可能時間（24時間）
				),
			'security_policy' =>
				array(
					array(
						'widevine' =>
							array(
								'security_level' => 1,
								'required_hdcp_version' => 'HDCP_NONE',
								'required_cgms_flags' => 'CGMS_NONE',
								'disable_analog_output' => false,
								'hdcp_srm_rule' => 'HDCP_SRM_RULE_NONE',
								'override_device_revocation' => true
							)
					)
				)
		);

	} else {
		// PlayReady/FairPlay/NCGポリシー設定
		$token = array(
			'playback_policy' =>
				array(
					'limit' => true,
					'persistent' => false,
					'duration' => 86400  // 再生可能時間（24時間）
				),
			'security_policy' =>
				array(
					'playready' =>
						array(
							'security_level' => 150,
							'digital_video_protection_level' => 100,
							'analog_video_protection_level' => 100,
							'digital_audio_protection_level' => 100,
							'require_hdcp_type_1' => false
						),
					'fairplay' =>
						array(
							'hdcp_enforcement' => -1,
							'allow_airplay' => true,
							'allow_av_adapter' => true
						),
					'ncg' =>
						array(
							'allow_mobile_abnormal_device' => false,
							'allow_external_display' => false,
							'control_hdcp' => 0
						),
				)
		);
	}

	// ステップ2 - ライセンスルール暗号化
	$token = json_encode($token);
	$token = base64_encode(openssl_encrypt($token, 'AES-256-CBC', INKA_SITE_KEY, OPENSSL_RAW_DATA, INKA_IV));

	// ステップ3 - ハッシュ値生成
	$hash = INKA_ACCESS_KEY . $drmType . INKA_SITE_ID . $clientUserId . $cid . $token . $timestamp;
	$hash = base64_encode(hash("sha256", $hash, true));

	// ステップ4 - ライセンストークン生成
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
		position: relative;
		width: 100%;
		height: 0;
		padding-bottom: 56.25%;
	}

	.video {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
	}
</style>

<html lang="ja">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0,maximum-scale=1.0" />

</head>

<body>
	<div class="countsort">
		<!-- Kollusビデオプレーヤーiframe -->
		<iframe id="iframe"
			src="https://v.kr.kollus.com/s?jwt=<?php echo $jwt; ?>&custom_key=<?php echo KOLLUS_CUSTOM_KEY; ?>&player_version=html5"
			allowfullscreen webkitallowfullscreen mozallowfullscreen allow="encrypted-media" class="video"></iframe>
	</div>
</body>

</html>
