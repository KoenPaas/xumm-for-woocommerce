<?php
$dir = dirname(plugin_dir_path(__FILE__));

$locale = $dir . '/languages/xumm-payment.'.get_locale().'.json';
if(file_exists($locale)) {
    $lang = json_decode(file_get_contents($locale));
} else {
    $lang = json_decode(file_get_contents($dir . '/languages/xumm-payment.en_EN.json'));
}

?>