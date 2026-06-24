
<?

echo "<pre>".htmlspecialchars(print_r($arResult, true))."</pre>";

file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/test.txt", print_r($res, true),FILE_APPEND);

array_filter(array_unique(array_values($arr)));

?>