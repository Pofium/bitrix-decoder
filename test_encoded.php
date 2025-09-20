<?php
// Тестовый закодированный файл для проверки декодера
eval(base64_decode('ZWNobyAiSGVsbG8gV29ybGQhIjs='));

$encoded_string = base64_decode('VGVzdCBzdHJpbmc=');

$hex_string = pack('H*', '48656c6c6f20576f726c64');

$chr_function = chr(72).chr(101).chr(108).chr(108).chr(111);

$rot13_string = str_rot13('Uryyb Jbeyq');

$global_array = array(
    'key1' => 'value1',
    'key2' => base64_decode('dmFsdWUy'),
    'key3' => chr(116).chr(101).chr(115).chr(116)
);

function test_function($param) {
    return base64_decode($param);
}

$variable1 = "test";
$variable2 = 123;
$variable3 = true;

// Более сложная обфускация
eval(gzinflate(base64_decode('eJwLycxLVUjOzytJzSvRy87PS9VLLsosKMnILFGwVTDUM9AzMNAzNNAzMjBQsAUAXw4M/A==')));
?>