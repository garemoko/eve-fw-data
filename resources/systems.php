<?php
/* 
This is a working document used to generate a complete list of regions, constellations 
and systems. The resulting file is saved as systems.json.

This file should be added to the same folder as the eve directory 
available within the Static Data Export 
here: https://developers.eveonline.com/resource/resources
*/

$fileData = fillArrayWithFileNodes( new DirectoryIterator( 'eve/' ) );
$fileData = removeEmptyObjects($fileData);
echo('<pre>');
echo json_encode($fileData, JSON_PRETTY_PRINT);

function fillArrayWithFileNodes( DirectoryIterator $dir )
{
  $data = array();
  foreach ( $dir as $node )
  {
    if ( $node->isDir() && !$node->isDot() )
    {
      $data[$node->getFilename()] = fillArrayWithFileNodes( new DirectoryIterator( $node->getPathname() ) );
    }
  }
  return $data;
}

function removeEmptyObjects($list){
  if (reset($list) === []){
    $list = array_keys($list);
  }
  else {
    foreach ($list as $key => $value) {
      $list[$key] = removeEmptyObjects($value);
    }
  }
  return $list;
}