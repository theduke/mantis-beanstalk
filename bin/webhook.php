<?php 

require '../lib/MantisBeanstalk.php';
require '../lib/vendor/mantisconnect-php/lib/MantisConnector.php';

$mantisBeanstalk = new MantisBeanstalk(2);

$client = new MantisConnector(
  'http://ohnein.creativevolume.at/api/soap/mantisconnect.php?wsdl', 
  'christoph.herzog', 
  'pcadmin3', 
  array());
  
$mantisBeanstalk->setMantisClient($client);

$mantisBeanstalk->run();