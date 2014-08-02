<?php
list($user, $apr1md5) = explode(':', file_get_contents(__DIR__.'/.htpasswd'));
$apr1md5 = trim($apr1md5);
list(,,$salt) = explode('$', $apr1md5);

if ($user !== $_SERVER['PHP_AUTH_USER'] || $apr1md5 !== crypt_apr1_md5($_SERVER['PHP_AUTH_PW'], $salt)) {
    http_response_code(403);
    echo '401 Unauthorized'.PHP_EOL;
    exit;
}
echo $_SERVER['PHP_AUTH_PW'];
echo $_SERVER['PHP_AUTH_USER'];

if (!preg_match('|^/([^/]+)$|', $_SERVER["REQUEST_URI"], $matches)) {
    http_response_code(404);
    echo '404 Not Found'.PHP_EOL;
    exit;
}

$endpoint =  $matches[1];

switch ($endpoint) {
    case 'clipboard':
        if (!isset($_POST['data'])) {
            http_response_code(400);
            echo '400 Bad Request'.PHP_EOL;
            exit;
        }

        $pipe = popen('/Users/tmsanrinsha/git/tmsanrinsha/remote2local/bin/pbcopy', 'w');
        fwrite($pipe, $_POST['data']);
        pclose($pipe);
        break;
    case 'browser':
        if (!preg_match("/\Ahttps?:\/\/[-_.!~*'()a-zA-Z0-9;\/?:@&=+$,%#]+\z/", $_POST['url'])) {
            http_response_code(400);
            echo '400 Bad Request'.PHP_EOL;
            exit;
        }

        exec('open '.escapeshellarg($_POST['url']));
        break;
    default:
        http_response_code (404);
        echo '404 Not Found'.PHP_EOL;
        exit;
}
http_response_code();

function crypt_apr1_md5($plainpasswd, $salt = null) {
    $salt = is_null($salt) ? substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8) : $salt;
    $len = strlen($plainpasswd);
    $text = $plainpasswd.'$apr1$'.$salt;
    $bin = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
    for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
    for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $plainpasswd{0}; }
    $bin = pack("H32", md5($text));
    for($i = 0; $i < 1000; $i++) {
        $new = ($i & 1) ? $plainpasswd : $bin;
        if ($i % 3) $new .= $salt;
        if ($i % 7) $new .= $plainpasswd;
        $new .= ($i & 1) ? $bin : $plainpasswd;
        $bin = pack("H32", md5($new));
    }
    for ($i = 0; $i < 5; $i++) {
        $k = $i + 6;
        $j = $i + 12;
        if ($j == 16) $j = 5;
        $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
    }
    $tmp = chr(0).chr(0).$bin[11].$tmp;
    $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
    "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
    return "$"."apr1"."$".$salt."$".$tmp;
}
