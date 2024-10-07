<?php
define("REG_KEY", "ac25c67ddd8f38c1b37a2348828e222e");

class FqReq
{
    private $ch;
	private $var;

    public function __construct($var)
    {
        $this->var = $var;
		$this->ch = curl_init();
    }

    public function batchGet($itemIds, $download = false)
    {
        $headers = [
            "Cookie: install_id=" . $this->var->install_id
        ];

        $url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v";
        $params = [
            "item_ids" => $itemIds,
            "req_type" => $download ? "0" : "1",
            "aid" => $this->var->aid,
            "update_version_code" => $this->var->update_version_code
        ];

        $url = $url . '?' . http_build_query($params);

        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);// 这个是主要参数
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);// 这个是主要参数

        $response = curl_exec($this->ch);
        if (curl_errno($this->ch)) {
            throw new Exception(curl_error($this->ch));
        }

		$retArr = json_decode($response, true);
		ksort($retArr['data']);

        return $retArr;
    }

    public function getRegisterKey()
    {
        $headers = [
            "Cookie: install_id=" . $this->var->install_id
        ];

        $url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/crypt/registerkey";
        $params = [
            "aid" => $this->var->aid
        ];

        $url = $url . '?' . http_build_query($params);

        $crypto = new FqCrypto(REG_KEY);
		$payload = json_encode([
            "content" => $crypto->newRegisterKeyContent($this->var->server_device_id, "0"),
            "keyver" => 1
        ]);

        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array_merge($headers, [
            "Content-Type: application/json"
        ]));
        curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);// 这个是主要参数
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);// 这个是主要参数
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        if (curl_errno($this->ch)) {
            throw new Exception(curl_error($this->ch));
        }

		$retArr = json_decode($response, true);
		$keyStr = $retArr['data']['key'];
		$byteKey = $crypto->decrypt($keyStr);

        return bin2hex($byteKey);
    }

	public function getDecryptContents($resArr)
    {
        $key = $this->getRegisterKey();
		//$key = '2ea29f632fa34bb72eb5de172cce8b89';
		$crypto = new FqCrypto($key);

		//print_r($resArr);

        //$res = [];
        foreach ($resArr['data'] as $itemId => $content) {
            $byteContent = $crypto->decrypt($content['content']);
            $s = gzdecode($byteContent);
			$resArr['data'][$itemId]['originContent'] = $s;
            //$res[] = [$itemId, $content['title'], $s];
        }

        return $resArr;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}

class FqCrypto
{
    private $key;
    private $cipher;

    public function __construct($key)
    {
        $this->key = hex2bin($key);

        if (strlen($this->key) !== 16) {
            throw new Exception("Key length mismatch! key: " . bin2hex($this->key));
        }

        $this->cipher = 'aes-128-cbc'; // 对应Rust中的Cipher::new_128
    }

    public function encrypt($data, $iv)
    {
        $res = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($res === false || empty($res)) {
            throw new Exception("Encrypt failed");
        }

        return $res;
    }

    public function decrypt($data)
    {
        $decodedData = base64_decode($data, true);

        if ($decodedData === false) {
            throw new Exception("Failed to decode data");
        }

        $iv = substr($decodedData, 0, 16);
        $encryptedData = substr($decodedData, 16);

        $res = openssl_decrypt($encryptedData, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($res === false || empty($res)) {
            throw new Exception("Decrypt failed");
        }

        return $res;
    }

    public function newRegisterKeyContent($serverDeviceId, $strVal)
    {
        if (!is_numeric($serverDeviceId) || !is_numeric($strVal)) {
            throw new Exception("Parse failed\nserver_device_id: {$serverDeviceId}\nstr_val:{$strVal}");
        }

        $serverDeviceId = (int)$serverDeviceId;
        $strVal = (int)$strVal;

        $combinedBytes = pack('P2', $serverDeviceId, $strVal); // P for native unsigned long (machine word size)
        $iv = openssl_random_pseudo_bytes(16); // Generate 16 bytes of random data

        $encData = $this->encrypt($combinedBytes, $iv);
        $combinedBytes = $iv . $encData;

        return base64_encode($combinedBytes);
    }
}

class FqVariable
{
    public $install_id;
    public $server_device_id;
    public $aid;
    public $update_version_code;

    public function __construct($install_id, $server_device_id, $aid, $update_version_code)
    {
        $this->install_id = $install_id;
        $this->server_device_id = $server_device_id;
        $this->aid = $aid;
        $this->update_version_code = $update_version_code;
    }
}

$var = new FqVariable(
    "4427064614339001",
    "4427064614334905",
    "1967",
    "62532"
);

$client = new FqReq($var);
$itemIds = "7392244682832495129,7392447334413517337,7392543933567336985";

try {
    $batchResArr = $client->batchGet($itemIds, false);
    $res = $client->getDecryptContents($batchResArr);
	//print_r($res);

    foreach ($res['data'] as $k => $v) {
        echo "编号:\t{$k}<br>标题:\t{$v['title']}<br>内容:{$v['originContent']}<br><br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>