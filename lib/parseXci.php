<?php

include "XCI.php";

function testXci($path)
{
    $xci = new XCI($path);
    $xci->getMasterPartitions();
    $xci->getSecurePartition();
    var_dump($xci);
}
