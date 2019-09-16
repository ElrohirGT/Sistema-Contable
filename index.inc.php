<?php

require './SistemaContable.php';

$res = $_FILES['archivo'];

$file = "Z:\\".$_FILES['archivo']['name'];
$type = strtolower(pathinfo($file,PATHINFO_EXTENSION));

if ($type === 'csv') {
  $csv = array_map('str_getcsv', file($file));
  $SistemaContable = new SistemaContable($csv);
  // echo "DIARIO";
  // var_dump($SistemaContable->csv);
  // echo "CUENTAS";
  // var_dump($SistemaContable->cuentas);
  // echo "MAYOR";
  // var_dump($SistemaContable->mayor);
  // echo "BALANCE";
  // var_dump($SistemaContable->balance);
  $SistemaContable->crearPDF();
}

?>