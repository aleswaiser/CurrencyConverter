<?php
namespace aleswaiser\CurrencyConverter;

define('SOURSE_PATH', '');

function currencyConverter($amount, $from = 'RUB', $to = 'RUB', $source = '')
{
    $fromRate = 1;
    $toRate = 1;

    $conversionRate = inCache($from, $to);

    if (!$conversionRate) {
        $filename = SOURSE_PATH . $source;
        if (file_exists($filename)) {
            include_once $filename;
        } else {
            $filename = 'http://www.cbr.ru/scripts/XML_daily.asp';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 1
                ]
            ]);
            $path = file_get_contents($filename, false, $context);
            $xml = new SimpleXMLElement($path);

            if ($from !== 'RUB') {
                $nominal = $xml->xpath("//Valute/CharCode[text()='$from']/following-sibling::Nominal");
                $nominal = $nominal[0];
                if ($nominal == 0) $nominal = 1;
                $fromRate = $xml->xpath("//Valute/CharCode[text()='$from']/following-sibling::Value");
                $fromRate = str_replace(',', '.', $fromRate[0]);
                if ($fromRate == 0) $fromRate = 1;
                $fromRate = $fromRate / $nominal;
            }
            if ($to !== 'RUB') {
                $nominal = $xml->xpath("//Valute/CharCode[text()='$to']/following-sibling::Nominal");
                $nominal = $nominal[0];
                if ($nominal == 0) $nominal = 1;
                $toRate = $xml->xpath("//Valute/CharCode[text()='$to']/following-sibling::Value");
                $toRate = str_replace(',', '.', $toRate[0]);
                if ($toRate == 0) $toRate = 1;
                $toRate = $toRate / $nominal;
            }
        }

        inCache($from, $to, $act = 'set', $fromRate, $toRate);

        $conversionRate = $fromRate / $toRate;
    }

    return round($amount * $conversionRate, 2, PHP_ROUND_HALF_DOWN);
}

function inCache($from, $to, $act = 'get', $fromRate = '', $toRate = '')
{
    $m = new Memcache;
    $m->addServer('localhost', 11211);

    $key = md5($from . ' ' . $to);

    switch($act) {
        case 'set':
            $items = [
                $key => $fromRate / $toRate,
                md5($to . ' ' . $from) => $toRate / $fromRate,
                md5('RUB' . ' ' . $to) => 1 / $toRate,
                md5($from . ' ' . 'RUB') => $fromRate / 1
            ];
            $m->set($items, time() + 43200);
            break;
        case 'get':
            return $m->get($key);
            break;
    }
}
?>