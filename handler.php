<?php
// Izinkan akses dari domain lain
header("Access-Control-Allow-Origin: *");
// Atur Content-Type menjadi JSON secara default
header("Content-Type: application/json");

// --- Bagian Helper: FUNGSI ESENSIAL ---
class Helper {
    public static function makeId() {
        return "Tiktok_Web_ID_" . time() . rand(100, 999);
    }
    public static function string_between($string, $start, $end) {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }
    public static function parseData($data) {
        return $data; 
    }
    public static function finalUrl($url) {
        return $url; 
    }
}
// --- Akhir Helper ---

// --- Definisikan Semua Kelas dalam Namespace Sovit\TikTok ---
namespace Sovit\TikTok;

// =========================================================
// KELAS API (Mengambil Tautan Unduhan)
// =========================================================
if (!\class_exists('\Sovit\TikTok\Api')) {
    class Api
    {
        const API_BASE = "https://www.tiktok.com/node/";
        private $_config = [];
        private $defaults = [
            "user-agent"     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36',
            "proxy-host"     => false,
            "cache-timeout"  => 3600,
            "nwm_endpoint"   => false,
            "api_key"   => false
        ];
        
        public function __construct($config = array(), $cacheEngine = false)
        {
            $this->_config = array_merge(['cookie_file' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tiktok.txt'], $this->defaults, $config);
        }
        
        public function failure() { return false; }
        
        private function remote_call($url, $header_only = false, $custom_headers = [])
        {
            $ch = curl_init();
            $options = [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => $header_only,
                CURLOPT_NOBODY         => $header_only,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => $this->_config['user-agent'],
                CURLOPT_REFERER        => 'https://www.tiktok.com/',
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_COOKIEJAR      => $this->_config['cookie_file'],
                CURLOPT_COOKIEFILE     => $this->_config['cookie_file'],
                CURLOPT_HTTPHEADER     => array_merge([
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Encoding: gzip, deflate, br',
                    'Accept-Language: en-US,en;q=0.9',
                ], $custom_headers),
            ];
            curl_setopt_array($ch, $options);
            $result = curl_exec($ch);
            $json_result = @json_decode($result);
            return $json_result ? $json_result : $result;
        }

        public function getVideoByUrl($url = "")
        {
            $result = $this->remote_call($url, false);
            $json_string = \Helper::string_between($result, "window['SIGI_STATE']=", ";window['SIGI_RETRY']=");
            if (!empty($json_string)) {
                $jsonData = json_decode($json_string, true);
                
                $awemeId = null;
                foreach ($jsonData['ItemModule'] as $itemId => $itemData) {
                    $awemeId = $itemId;
                    break; 
                }

                if ($awemeId && isset($jsonData['ItemModule'][$awemeId])) {
                    $videoData = $jsonData['ItemModule'][$awemeId];
                    return (object)[
                        'items' => [(object)[
                            'id' => $awemeId,
                            'createTime' => $videoData['createTime'] ?? 0,
                            'video' => (object)['downloadAddr' => 'placeholder'] 
                        ]]
                    ];
                }
            }
            return $this->failure();
        }

        public function getNoWatermark($url)
        {
            if (!preg_match("/https?:\/\/([^\.]+)?\.tiktok\.com/", $url)) {
                throw new \Exception("Invalid VIDEO URL");
            }
            $data = $this->getVideoByUrl($url);
            if ($data) {
                $video = $data->items[0];
                $result = $this->remote_call("https://api2.musical.ly/aweme/v1/aweme/detail/?" . \http_build_query(["aweme_id" => $video->id]));
                if ($result) {
                    if (isset($result->aweme_detail->video->play_addr->uri)) {
                        return (object) [
                            "id" => $result->aweme_detail->video->play_addr->uri,
                            "url" => $result->aweme_detail->video->play_addr->url_list[0],
                        ];
                    }
                }
            }
            return $this->failure();
        }
    }
}


// =========================================================
// KELAS DOWNLOAD (FIXED: Memaksa unduhan dari sisi server)
// =========================================================
if (!\class_exists('\Sovit\TikTok\Download')) {
    class Download
    {
        protected $buffer_size = 1000000;
        public function __construct($config = [])
        {
            $this->config = array_merge(['cookie_file' => sys_get_temp_dir().DIRECTORY_SEPARATOR . 'tiktok.txt', 'user-agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36"], $config);
            $this->tt_webid_v2 = \Helper::makeId(); 
        }
        
        public function file_size($url)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Referer: https://www.tiktok.com/foryou?lang=en',
            ]);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->config['user-agent']);
            curl_setopt($ch, CURLOPT_REFERER, "https://www.tiktok.com/");
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->config['cookie_file']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->config['cookie_file']);
            $data = curl_exec($ch);
            $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);
            return (int) $size;
        }

        public function url($url, $file_name = "tiktok-video", $ext = "mp4")
        {
            $file_size = $this->file_size($url);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_name . '.' . $ext . '"');
            header("Content-Transfer-Encoding: binary");
            header('Expires: 0');
            header('Pragma: public');

            if ($file_size > 100) {
                header('Content-Length: ' . $file_size);
            }
            header('Connection: Close');
            ob_clean();
            flush();
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->config['user-agent']);
            // PENTING: Referer yang benar agar file bisa diakses oleh server
            curl_setopt($ch, CURLOPT_REFERER, "https://www.tiktok.com/"); 
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->config['cookie_file']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->config['cookie_file']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            curl_close($ch);
            echo $output;
            exit;
        }
    }
}


// --- SKRIP UTAMA: MENANGANI PERMINTAAN DARI JAVASCRIPT ---
namespace { 
    
    // 1. Tangani Permintaan AJAX (POST)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['url'])) {
        $tiktokUrl = $data['url'];

        try {
            $api = new \Sovit\TikTok\Api(); 
            $videoData = $api->getNoWatermark($tiktokUrl);

            if ($videoData && isset($videoData->url)) {
                echo json_encode([
                    "success" => true,
                    "download_url" => $videoData->url,
                    "message" => "Tautan berhasil diambil."
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Gagal mendapatkan tautan unduhan dari API TikTok."]);
            }

        } catch (\Exception $e) {
            echo json_encode(["success" => false, "message" => "Error Server: " . $e->getMessage()]);
        }
        exit;
    } 
    
    // 2. Tangani Permintaan Unduhan Langsung (GET) - KODE INI YANG MEMASTIKAN UNDUHAN BERFUNGSI
    else if (isset($_GET['download_url'])) {
        $downloadUrl = $_GET['download_url'];
        $fileName = $_GET['filename'] ?? 'tiktok-video';
        
        try {
            $downloader = new \Sovit\TikTok\Download();
            $downloader->url($downloadUrl, $fileName);
            
        } catch (\Exception $e) {
            header('Content-Type: text/plain');
            echo "Gagal mengunduh file: " . $e->getMessage();
        }
        exit;
    }
    
    // Default response
    else {
        echo json_encode(["success" => false, "message" => "Permintaan tidak valid."]);
        exit;
    }
}
?>
    {
        const API_BASE = "https://www.tiktok.com/node/";
        private $_config = [];
        private $cacheEnabled = false;
        private $defaults = [
            "user-agent"     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36',
            "proxy-host"     => false,
            "cache-timeout"  => 3600,
            "nwm_endpoint"   => false,
            "api_key"   => false
        ];
        
        public function __construct($config = array(), $cacheEngine = false)
        {
            $this->_config = array_merge(['cookie_file' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tiktok.txt'], $this->defaults, $config);
            if ($cacheEngine) {
                $this->cacheEnabled = true;
                $this->cacheEngine        = $cacheEngine;
            }
        }
        
        public function failure() { return false; }
        
        // Fungsi Remote Call (Penting)
        private function remote_call($url, $header_only = false, $custom_headers = [])
        {
            $ch = curl_init();
            $options = [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => $header_only,
                CURLOPT_NOBODY         => $header_only,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => $this->_config['user-agent'],
                CURLOPT_REFERER        => 'https://www.tiktok.com/',
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_COOKIEJAR      => $this->_config['cookie_file'],
                CURLOPT_COOKIEFILE     => $this->_config['cookie_file'],
                CURLOPT_HTTPHEADER     => array_merge([
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Encoding: gzip, deflate, br',
                    'Accept-Language: en-US,en;q=0.9',
                ], $custom_headers),
            ];
            curl_setopt_array($ch, $options);
            $result = curl_exec($ch);
            $json_result = @json_decode($result);
            return $json_result ? $json_result : $result;
        }

        public function getVideoByUrl($url = "")
        {
            $result = $this->remote_call($url, false);
            // Menggunakan helper dari global namespace
            $json_string = \Helper::string_between($result, "window['SIGI_STATE']=", ";window['SIGI_RETRY']=");
            if (!empty($json_string)) {
                $jsonData = json_decode($json_string, true);
                
                $awemeId = null;
                foreach ($jsonData['ItemModule'] as $itemId => $itemData) {
                    $awemeId = $itemId;
                    break; 
                }

                if ($awemeId && isset($jsonData['ItemModule'][$awemeId])) {
                    $videoData = $jsonData['ItemModule'][$awemeId];
                    return (object)[
                        'items' => [(object)[
                            'id' => $awemeId,
                            'createTime' => $videoData['createTime'] ?? 0,
                            'video' => (object)['downloadAddr' => 'placeholder'] 
                        ]]
                    ];
                }
            }
            return $this->failure();
        }

        // Metode Utama untuk unduhan tanpa watermark
        public function getNoWatermark($url)
        {
            if (!preg_match("/https?:\/\/([^\.]+)?\.tiktok\.com/", $url)) {
                throw new \Exception("Invalid VIDEO URL");
            }
            $data = $this->getVideoByUrl($url);
            if ($data) {
                $video = $data->items[0];
                $result = $this->remote_call("https://api2.musical.ly/aweme/v1/aweme/detail/?" . \http_build_query(["aweme_id" => $video->id]));
                if ($result) {
                    if (isset($result->aweme_detail->video->play_addr->uri)) {
                        return (object) [
                            "id" => $result->aweme_detail->video->play_addr->uri,
                            "url" => $result->aweme_detail->video->play_addr->url_list[0],
                        ];
                    }
                }
            }
            return $this->failure();
        }
        // ... (Metode Api lainnya dihilangkan)
    }
}


// =========================================================
// KELAS DOWNLOAD (Untuk memaksa Unduhan File) - Dari Prompt Kedua
// =========================================================
if (!\class_exists('\Sovit\TikTok\Download')) {
    class Download
    {
        protected $buffer_size = 1000000;
        public function __construct($config = [])
        {
            $this->config = array_merge(['cookie_file' => sys_get_temp_dir().DIRECTORY_SEPARATOR . 'tiktok.txt', 'user-agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36"], $config);
            $this->tt_webid_v2 = \Helper::makeId(); 
        }
        
        public function file_size($url)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Referer: https://www.tiktok.com/foryou?lang=en',
            ]);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->config['user-agent']);
            curl_setopt($ch, CURLOPT_REFERER, "https://www.tiktok.com/");
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->config['cookie_file']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->config['cookie_file']);
            $data = curl_exec($ch);
            $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);
            return (int) $size;
        }

        public function url($url, $file_name = "tiktok-video", $ext = "mp4")
        {
            $file_size = $this->file_size($url);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_name . '.' . $ext . '"');
            header("Content-Transfer-Encoding: binary");
            header('Expires: 0');
            header('Pragma: public');

            if ($file_size > 100) {
                header('Content-Length: ' . $file_size);
            }
            header('Connection: Close');
            ob_clean();
            flush();
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->config['user-agent']);
            curl_setopt($ch, CURLOPT_REFERER, "https://www.tiktok.com/");
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->config['cookie_file']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->config['cookie_file']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            curl_close($ch);
            echo $output;
            exit;
        }
    }
}


// --- SKRIP UTAMA: MENANGANI PERMINTAAN DARI JAVASCRIPT ---
namespace { // Kembali ke global namespace
    
    // Tangani Permintaan AJAX (POST dari JavaScript)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['url'])) {
        $tiktokUrl = $data['url'];

        try {
            $api = new \Sovit\TikTok\Api(); 
            $videoData = $api->getNoWatermark($tiktokUrl);

            if ($videoData && isset($videoData->url)) {
                echo json_encode([
                    "success" => true,
                    "download_url" => $videoData->url,
                    "message" => "Tautan berhasil diambil."
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Gagal mendapatkan tautan unduhan dari API TikTok (Video mungkin sudah dihapus atau URL tidak valid)."]);
            }

        } catch (\Exception $e) {
            echo json_encode(["success" => false, "message" => "Error Server: " . $e->getMessage()]);
        }
        exit;
    } 
    
    // Tangani Permintaan Unduhan Langsung (GET) - Opsi jika Anda ingin memaksa unduhan server side
    else if (isset($_GET['download_url'])) {
        $downloadUrl = $_GET['download_url'];
        $fileName = $_GET['filename'] ?? 'tiktok-video';
        
        try {
            $downloader = new \Sovit\TikTok\Download();
            $downloader->url($downloadUrl, $fileName);
            
        } catch (\Exception $e) {
            header('Content-Type: text/plain');
            echo "Gagal mengunduh file: " . $e->getMessage();
        }
        exit;
    }
    
    // Default response jika tidak ada data yang valid
    else {
        echo json_encode(["success" => false, "message" => "Permintaan tidak valid. Harap gunakan formulir di index.html."]);
        exit;
    }
}
?>

