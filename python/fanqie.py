import requests
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
from Crypto.Random import get_random_bytes
import base64
import json
import gzip
from urllib.parse import urlencode
requests.packages.urllib3.disable_warnings()

REG_KEY = "ac25c67ddd8f38c1b37a2348828e222e"

class FqCrypto:
    def __init__(self, key):
        self.key = bytes.fromhex(key)
        if len(self.key) != 16:
            raise ValueError(f"Key length mismatch! key: {self.key.hex()}")
        self.cipher_mode = AES.MODE_CBC

    def encrypt(self, data, iv):
        cipher = AES.new(self.key, self.cipher_mode, iv)
        ct_bytes = cipher.encrypt(pad(data, AES.block_size))
        return ct_bytes

    def decrypt(self, data):
        iv = data[:16]
        ct = data[16:]
        cipher = AES.new(self.key, self.cipher_mode, iv)
        pt = unpad(cipher.decrypt(ct), AES.block_size)
        return pt

    def new_register_key_content(self, server_device_id, str_val):
        if not str_val.isdigit() or not server_device_id.isdigit():
            raise ValueError(f"Parse failed\nserver_device_id: {server_device_id}\nstr_val:{str_val}")
        combined_bytes = int(server_device_id).to_bytes(8, byteorder='little') + int(str_val).to_bytes(8, byteorder='little')
        iv = get_random_bytes(16)
        enc_data = self.encrypt(combined_bytes, iv)
        combined_bytes = iv + enc_data
        return base64.b64encode(combined_bytes).decode('utf-8')

class FqVariable:
    def __init__(self, install_id, server_device_id, aid, update_version_code):
        self.install_id = install_id
        self.server_device_id = server_device_id
        self.aid = aid
        self.update_version_code = update_version_code

class FqReq:
    def __init__(self, var):
        self.var = var
        self.session = requests.Session()

    def batch_get(self, item_ids, download=False):
        headers = {
            "Cookie": f"install_id={self.var.install_id}"
        }
        url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v"
        params = {
            "item_ids": item_ids,
            "req_type": "0" if download else "1",
            "aid": self.var.aid,
            "update_version_code": self.var.update_version_code
        }
        response = self.session.get(url, headers=headers, params=params, verify=False)
        response.raise_for_status()
        ret_arr = response.json()
        return ret_arr

    def get_register_key(self):
        headers = {
            "Cookie": f"install_id={self.var.install_id}",
            "Content-Type": "application/json"
        }
        url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/crypt/registerkey"
        params = {
            "aid": self.var.aid
        }
        crypto = FqCrypto(REG_KEY)
        payload = json.dumps({
            "content": crypto.new_register_key_content(self.var.server_device_id, "0"),
            "keyver": 1
        }).encode('utf-8')
        response = self.session.post(url, headers=headers, params=params, data=payload, verify=False)
        response.raise_for_status()
        ret_arr = response.json()
        key_str = ret_arr['data']['key']
        byte_key = crypto.decrypt(base64.b64decode(key_str))
        return byte_key.hex()

    def get_decrypt_contents(self, res_arr):
        key = self.get_register_key()
        crypto = FqCrypto(key)
        for item_id, content in res_arr['data'].items():
            byte_content = crypto.decrypt(base64.b64decode(content['content']))
            s = gzip.decompress(byte_content).decode('utf-8')
            res_arr['data'][item_id]['originContent'] = s
        return res_arr

    def __del__(self):
        self.session.close()

var = FqVariable(
    "4427064614339001",
    "4427064614334905",
    "1967",
    "62532"
)

client = FqReq(var)
item_ids = "7392244682832495129,7392447334413517337,7392543933567336985"
try:
    batch_res_arr = client.batch_get(item_ids, False)
    res = client.get_decrypt_contents(batch_res_arr)
    for k, v in res['data'].items():
        print(f"编号:\t{k}\n标题:\t{v['title']}\n内容:{v['originContent']}\n")
except Exception as e:
    print(f"Error: {e}")
