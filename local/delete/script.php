<?
use \Bitrix\Main\Type\DateTime;

$dt = DateTime::createFromTimestamp(time());
$res = $dt;
echo get_class(new \Bitrix\Main\Type\DateTime());
echo "<pre>".htmlspecialchars(print_r($res->toString(), true))."</pre>";