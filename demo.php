<?php

require 'vendor/autoload.php';

$url = 'http://sports.sina.com.cn/basketball/nba/2016-11-05/doc-ifxxnety7387922.shtml';

$html = file_get_contents($url);

$readability = new Readability\Readability($html);

// $readability->load($html);

echo 'Title: '.$readability->title().PHP_EOL;
echo 'Date: '.$readability->date().PHP_EOL;
echo 'Content: '.$readability->content().PHP_EOL;
echo 'Text: '.$readability->text().PHP_EOL;
echo 'WordCount: '.$readability->wordCount().PHP_EOL;

// image source
print_r($readability->images());