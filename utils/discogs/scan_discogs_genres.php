<?php

$known_genres = array();
$known_formats = array();

$xml = new XMLReader();
$xml->open('php://stdin',null,LIBXML_NOERROR);
while($xml->read())
{
  if ($xml->nodeType == XMLReader::ELEMENT) {
    //echo '------' . $xml->readString() . "\n";
    if ($xml->name == 'genre') $known_genres[$xml->readString()] = true;
    if ($xml->name == 'format') $known_formats[$xml->getAttribute('name')] = true;
  } 
}
$xml->close();
echo '-- CREATE TYPE genre_t AS ENUM (' . implode(',',array_keys($known_genres)) . ");\n";
echo '-- CREATE TYPE format_t AS ENUM (' . implode(',',array_keys($known_formats)) . ");\n";

