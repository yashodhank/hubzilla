<?php /** @file */

require_once('library/ASNValue.class.php');
require_once('library/asn1.php');

function rsa_sign($data,$key,$alg = 'sha256') {
	if(! $key)
		return 'no key';
	$sig = '';
	if(intval(OPENSSL_ALGO_SHA256) && $alg === 'sha256')
		$alg = OPENSSL_ALGO_SHA256;
	openssl_sign($data,$sig,$key,$alg);
	return $sig;
}

function rsa_verify($data,$sig,$key,$alg = 'sha256') {

	if(! $key)
		return false;

	if(intval(OPENSSL_ALGO_SHA256) && $alg === 'sha256')
		$alg = OPENSSL_ALGO_SHA256;
	$verify = openssl_verify($data,$sig,$key,$alg);
	return $verify;
}

function pkcs5_pad ($text, $blocksize)
{
    $pad = $blocksize - (strlen($text) % $blocksize);
    return $text . str_repeat(chr($pad), $pad);
}

function pkcs5_unpad($text)
{
    $pad = ord($text{strlen($text)-1});
    if ($pad > strlen($text)) return false;
    if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false;
    return substr($text, 0, -1 * $pad);
} 

function AES256CBC_encrypt($data,$key,$iv) {
	if(get_config('system','openssl_encrypt')) {
		return openssl_encrypt($data,'aes-256-cbc',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
	}
	return mcrypt_encrypt(
		MCRYPT_RIJNDAEL_128, 
		str_pad($key,32,"\0"), 
		pkcs5_pad($data,16), 
		MCRYPT_MODE_CBC, 
		str_pad($iv,16,"\0"));
}

function AES256CBC_decrypt($data,$key,$iv) {
	if(get_config('system','openssl_encrypt')) {
		return openssl_decrypt($data,'aes-256-cbc',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
	}
	return pkcs5_unpad(mcrypt_decrypt(
		MCRYPT_RIJNDAEL_128, 
		str_pad($key,32,"\0"), 
		$data, 
		MCRYPT_MODE_CBC, 
		str_pad($iv,16,"\0")));
}

function crypto_encapsulate($data,$pubkey,$alg='aes256cbc') {
	if($alg === 'aes256cbc')
		return aes_encapsulate($data,$pubkey);

}


function aes_encapsulate($data,$pubkey) {
	if(! $pubkey)
		logger('aes_encapsulate: no key. data: ' . $data);
	$key = random_string(32,RANDOM_STRING_TEXT);
	$iv  = random_string(16,RANDOM_STRING_TEXT);
	$result['data'] = base64url_encode(AES256CBC_encrypt($data,$key,$iv),true);
	// log the offending call so we can track it down
	if(! openssl_public_encrypt($key,$k,$pubkey)) {
		$x = debug_backtrace();
		logger('aes_encapsulate: RSA failed. ' . print_r($x[0],true));
	}
	$result['alg'] = 'aes256cbc';
 	$result['key'] = base64url_encode($k,true);
	openssl_public_encrypt($iv,$i,$pubkey);
	$result['iv'] = base64url_encode($i,true);
	return $result;
}

function crypto_unencapsulate($data,$prvkey) {
	if(! $data)
		return;
	$alg = ((array_key_exists('alg',$data)) ? $data['alg'] : 'aes256cbc');
	if($alg === 'aes256cbc')
		return aes_unencapsulate($data,$prvkey);

}


function aes_unencapsulate($data,$prvkey) {
	openssl_private_decrypt(base64url_decode($data['key']),$k,$prvkey);
	openssl_private_decrypt(base64url_decode($data['iv']),$i,$prvkey);
	return AES256CBC_decrypt(base64url_decode($data['data']),$k,$i);
}

function new_keypair($bits) {

	$openssl_options = array(
		'digest_alg'       => 'sha1',
		'private_key_bits' => $bits,
		'encrypt_key'      => false 
	);

	$conf = get_config('system','openssl_conf_file');
	if($conf)
		$openssl_options['config'] = $conf;
	
	$result = openssl_pkey_new($openssl_options);

	if(empty($result)) {
		logger('new_keypair: failed');
		return false;
	}

	// Get private key

	$response = array('prvkey' => '', 'pubkey' => '');

	openssl_pkey_export($result, $response['prvkey']);

	// Get public key
	$pkey = openssl_pkey_get_details($result);
	$response['pubkey'] = $pkey["key"];

	return $response;

}

function pkcs1to8($oldkey,$len) {

	if($len == 4096)
		$c = 'g';
	if($len == 2048)
		$c = 'Q';

	if(strstr($oldkey,'BEGIN PUBLIC'))
		return $oldkey;

	$oldkey = str_replace('-----BEGIN RSA PUBLIC KEY-----', '', $oldkey);
	$oldkey = trim(str_replace('-----END RSA PUBLIC KEY-----', '', $oldkey));
	$key = 'MIICIjANBgkqhkiG9w0BAQEFAAOCA' . $c . '8A' . str_replace("\n", '', $oldkey);
	$key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
	return $key;
}

function pkcs8to1($oldkey,$len) {

	if(strstr($oldkey,'BEGIN RSA'))
		return $oldkey;

	$oldkey = str_replace('-----BEGIN PUBLIC KEY-----', '', $oldkey);
	$oldkey = trim(str_replace('-----END PUBLIC KEY-----', '', $oldkey));
	$key = str_replace("\n",'',$oldkey);
	$key = substr($key,32);
	$key = "-----BEGIN RSA PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END RSA PUBLIC KEY-----";
	return $key;
}


function DerToPem($Der, $Private=false)
{
    //Encode:
    $Der = base64_encode($Der);
    //Split lines:
    $lines = str_split($Der, 65);
    $body = implode("\n", $lines);
    //Get title:
    $title = $Private? 'RSA PRIVATE KEY' : 'PUBLIC KEY';
    //Add wrapping:
    $result = "-----BEGIN {$title}-----\n";
    $result .= $body . "\n";
    $result .= "-----END {$title}-----\n";
 
    return $result;
}

function DerToRsa($Der)
{
    //Encode:
    $Der = base64_encode($Der);
    //Split lines:
    $lines = str_split($Der, 64);
    $body = implode("\n", $lines);
    //Get title:
    $title = 'RSA PUBLIC KEY';
    //Add wrapping:
    $result = "-----BEGIN {$title}-----\n";
    $result .= $body . "\n";
    $result .= "-----END {$title}-----\n";
 
    return $result;
}


function pkcs8_encode($Modulus,$PublicExponent) {
	//Encode key sequence
	$modulus = new ASNValue(ASNValue::TAG_INTEGER);
	$modulus->SetIntBuffer($Modulus);
	$publicExponent = new ASNValue(ASNValue::TAG_INTEGER);
	$publicExponent->SetIntBuffer($PublicExponent);
	$keySequenceItems = array($modulus, $publicExponent);
	$keySequence = new ASNValue(ASNValue::TAG_SEQUENCE);
	$keySequence->SetSequence($keySequenceItems);
	//Encode bit string
	$bitStringValue = $keySequence->Encode();
	$bitStringValue = chr(0x00) . $bitStringValue; //Add unused bits byte
	$bitString = new ASNValue(ASNValue::TAG_BITSTRING);
	$bitString->Value = $bitStringValue;
	//Encode body
	$bodyValue = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" . $bitString->Encode();
	$body = new ASNValue(ASNValue::TAG_SEQUENCE);
	$body->Value = $bodyValue;
	//Get DER encoded public key:
	$PublicDER = $body->Encode();
	return $PublicDER;
}


function pkcs1_encode($Modulus,$PublicExponent) {
	//Encode key sequence
	$modulus = new ASNValue(ASNValue::TAG_INTEGER);
	$modulus->SetIntBuffer($Modulus);
	$publicExponent = new ASNValue(ASNValue::TAG_INTEGER);
	$publicExponent->SetIntBuffer($PublicExponent);
	$keySequenceItems = array($modulus, $publicExponent);
	$keySequence = new ASNValue(ASNValue::TAG_SEQUENCE);
	$keySequence->SetSequence($keySequenceItems);
	//Encode bit string
	$bitStringValue = $keySequence->Encode();
	return $bitStringValue;
}


function metopem($m,$e) {
	$der = pkcs8_encode($m,$e);
	$key = DerToPem($der,false);
	return $key;
}	


function pubrsatome($key,&$m,&$e) {
	require_once('library/asn1.php');

	$lines = explode("\n",$key);
	unset($lines[0]);
	unset($lines[count($lines)]);
	$x = base64_decode(implode('',$lines));

	$r = ASN_BASE::parseASNString($x);

	$m = base64url_decode($r[0]->asnData[0]->asnData);
	$e = base64url_decode($r[0]->asnData[1]->asnData);
}


function rsatopem($key) {
	pubrsatome($key,$m,$e);
	return(metopem($m,$e));
}

function pemtorsa($key) {
	pemtome($key,$m,$e);
	return(metorsa($m,$e));
}

function pemtome($key,&$m,&$e) {
	$lines = explode("\n",$key);
	unset($lines[0]);
	unset($lines[count($lines)]);
	$x = base64_decode(implode('',$lines));

	$r = ASN_BASE::parseASNString($x);

	$m = base64url_decode($r[0]->asnData[1]->asnData[0]->asnData[0]->asnData);
	$e = base64url_decode($r[0]->asnData[1]->asnData[0]->asnData[1]->asnData);
}

function metorsa($m,$e) {
	$der = pkcs1_encode($m,$e);
	$key = DerToRsa($der);
	return $key;
}	

function salmon_key($pubkey) {
	pemtome($pubkey,$m,$e);
	return 'RSA' . '.' . base64url_encode($m,true) . '.' . base64url_encode($e,true) ;
}

// old function for providing mysql compatible encryption and is also
// used in Friendica 'RINO'. This function is messy and should be retired. 
 

if(! function_exists('aes_decrypt')) {
function aes_decrypt($val,$ky)
{
    $key="\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
    for($a=0;$a<strlen($ky);$a++)
      $key[$a%16]=chr(ord($key[$a%16]) ^ ord($ky[$a]));
    $mode = MCRYPT_MODE_ECB;
    $enc = MCRYPT_RIJNDAEL_128;
    $dec = @mcrypt_decrypt($enc, $key, $val, $mode, @mcrypt_create_iv( @mcrypt_get_iv_size($enc, $mode), MCRYPT_DEV_URANDOM ) );
    return rtrim($dec,(( ord(substr($dec,strlen($dec)-1,1))>=0 and ord(substr($dec, strlen($dec)-1,1))<=16)? chr(ord( substr($dec,strlen($dec)-1,1))):null));
}}


if(! function_exists('aes_encrypt')) {
function aes_encrypt($val,$ky)
{
    $key="\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
    for($a=0;$a<strlen($ky);$a++)
      $key[$a%16]=chr(ord($key[$a%16]) ^ ord($ky[$a]));
    $mode=MCRYPT_MODE_ECB;
    $enc=MCRYPT_RIJNDAEL_128;
    $val=str_pad($val, (16*(floor(strlen($val) / 16)+(strlen($val) % 16==0?2:1))), chr(16-(strlen($val) % 16)));
    return mcrypt_encrypt($enc, $key, $val, $mode, mcrypt_create_iv( mcrypt_get_iv_size($enc, $mode), MCRYPT_DEV_URANDOM));
}} 


