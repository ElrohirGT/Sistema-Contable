<?php

function ObtenerLlaves($partida, $lado) {
  $llaves = [];
  foreach (array_column($partida, $lado) as $indice => $celda) {
    if (strlen($celda)) {
      $llaves[] = $indice;
    }
  }
  return $llaves;
}

class LibroDiario
{
  private $partidas = [];
  
  function __construct($csv){
    $partidas = [];
    $numeroPartida = 0;
    foreach ($csv as $line) {
      if (!empty($line[0])&&!is_numeric($line[0])) {
        $numeroPartida = explode(' ', $line[1])[1]-1;
        continue;
      }
      if (empty($line[2]) || empty($line[3])) {
        $partidas[$numeroPartida][] = $line;
      }
    }
    $this->partidas = $partidas;
  }
  function ObtenerPartida($numPartida){
    return $this->partidas[$numPartida-1];
  }
  function Descripcion($numPartida, $nombreCuenta){
    $partida = $this->ObtenerPartida($numPartida);
    $lineaCuenta = array_search($nombreCuenta, array_column($partida, 1));
    $ladoLinea = (empty($partida[$lineaCuenta][2])) ? 3: 2;
    $ladoContrario = ($ladoLinea === 2)? 3:2;
    
    $mensaje = ($ladoLinea === 2)? "A: ": "Por: ";

    $ladoLinea = ObtenerLlaves($partida, $ladoLinea);
    $ladoContrario = ObtenerLlaves($partida, $ladoContrario);
    
    // TODO Esta línea debe devolver los indices de las lineas que no son de la cuenta
    $lineasCuentasContrarias = array_filter($ladoContrario, function ($key) use ($ladoLinea){return !in_array($key, $ladoLinea);});

    $mensaje .= (count($lineasCuentasContrarias) === 1)? $partida[$lineasCuentasContrarias[0]][1]: "Varias Cuentas";
    return $mensaje;
  }
}


?>