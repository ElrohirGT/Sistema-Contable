<?php

require './fpdf.php';
require './LibroDiario.php';

setlocale(LC_MONETARY, 'es_GT');

function UnformatMoney($string) {
  return 0+substr(str_replace(',', '', trim($string)), 1);
}

class SistemaContable {
  public $csv = [];
  public $cuentas = [];
  public $diario = [];
  public $mayor = [];
  public $balance = [];
  function __construct($csv=[]){
    $this->csv = $csv;
    $this->CrearDiario();
    $this->ObtenerCuentas();
    $this->CrearLibroMayor();
    $this->CrearBalance();
  }
  // TODO Termianr de crear diario para implementar el por en el libro mayor
  function CrearDiario() {
    $this->diario = new LibroDiario($this->csv);
  }
  function ObtenerCuentas() {
    foreach ($this->csv as $line) {
      if (is_numeric($line[0])&& !in_array($line[1], array_column($this->cuentas, 'nombre'))) {
        $indice = count($this->cuentas);
        $this->cuentas[$indice]['numero'] = $line[0];
        $this->cuentas[$indice]['nombre'] = $line[1];
        $this->cuentas[$indice]['tipo'] = (empty($line[3]))? 'Activo': 'Pasivo';
      }
    }
  }
  function CrearLibroMayor() {
    foreach ($this->cuentas as $cuenta) {
      $dia = 0;
      $partida = 0;
      $saldo = 0;
      foreach ($this->csv as $line) {
        if (empty($line[2]) && empty($line[3])) {
          $dia = explode(' ', $line[0])[1];
          $partida = explode(' ', $line[1])[1];
        }
        if ($cuenta['nombre'] === $line[1]) {
          $subIndice = "{$dia}|{$partida}";
          $tipo = ($cuenta['tipo']==='Activo')? 2: 3; $contrario= ($tipo===2)? 3: 2;
          $movimiento = (!empty($line[$tipo])) ? UnformatMoney($line[$tipo]) : -UnformatMoney($line[$contrario]);
          $this->mayor[$cuenta['nombre']]['movimientos'][] = [
            "partida" => $subIndice,
            "cantidad" => $movimiento,
            // TODO Esta función no sirve todavía
            "descripcion"=>$this->diario->Descripcion($partida, $cuenta['nombre'])
          ];
          $saldo += $movimiento;
        }
      }
      $this->mayor[$cuenta['nombre']]['SALDO ACTUAL'] = $saldo;
      $this->mayor[$cuenta['nombre']]['numero'] = $cuenta['numero'];
      $this->mayor[$cuenta['nombre']]['tipo'] = $cuenta['tipo'];
    }
  }
  function CrearBalance(){
    $totales = [0,0];
    foreach ($this->mayor as $nombre => $cuenta) {
      $this->balance['cuentas'][$nombre] = [
        "saldo"=>$cuenta['SALDO ACTUAL'],
        "numero"=>$cuenta['numero'],
        "tipo"=>$cuenta['tipo']
      ];
      
      if ($cuenta['tipo']==='Activo') {
        $totales[0]+=$cuenta['SALDO ACTUAL'];
      } else {
        $totales[1]+=$cuenta['SALDO ACTUAL'];
      }
    }
    $this->balance['totales'] = $totales;
    if ($totales[0] != $totales[1]) {
      $mayor = ($totales[0]<$totales[1]) ? $totales[1] : $totales[0];
      $menor = ($totales[0]<$totales[1]) ? $totales[0] : $totales[1];
      $diferencia = ($mayor-$menor) /2;
      $this->balance['error'] = "LOS SALDOS NO CUADRAN POR: {$diferencia}.";
    }
  }
  function CrearPDF() {
    $pdf = new FPDF('P', 'cm', 'Letter');
    $pdf->AddPage();
    $pdf->SetMargins(1, 1);
    $width = $pdf->GetPageWidth()-2;
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 2, "LIBRO MAYOR", 0, 1, 'C');
    
    $distribucion = [
      "numero"=>$width*5/100,
      "titulo"=>$width*65/100,
      "valores"=>$width*10/100
    ];
    //LIBRO MAYOR
    foreach ($this->mayor as $nombre => $cuenta) {
      $pdf->SetFont('Arial', 'B', 14);
      $pdf->Cell($distribucion['numero'], 1, $cuenta['numero'], 0, 0, 'C');
      $pdf->Cell($distribucion['titulo'], 1, $nombre, 0, 1, 'L');
      $pdf->SetFont('Arial', '', 12);
      foreach ($cuenta['movimientos'] as $movimiento) {
        $pdf->Cell($distribucion['numero'], 1, $movimiento['partida'], 0, 0, 'C');
        $pdf->Cell($distribucion['titulo'], 1, $movimiento['descripcion'], 0, 0, 'L');
        
        $columna = ($cuenta['tipo']==='Activo')? true: false;
        $columnaContraria = !$columna;
        
        if ($movimiento['cantidad'] < 0 && $columna) {
          $pdf->Cell($distribucion['valores'], 1, number_format(abs($movimiento['cantidad']), 2), 0, 0, 'R');
          $pdf->Cell($distribucion['valores'], 1, '', 0, 0, 'R');
        } else {
          $pdf->Cell($distribucion['valores'], 1, '', 0, 0, 'R');
          $pdf->Cell($distribucion['valores'], 1, number_format(abs($movimiento['cantidad']), 2), 0, 0, 'R');
        }
        $pdf->Cell($distribucion['valores'], 1, '', 0, 1, 'R');
      }
      $pdf->SetTextColor(255, 0, 0);
      $pdf->Cell($distribucion['numero']+$distribucion['titulo'], 1, 'SALDO ACTUAL', 0, 0, 'R');
      $pdf->SetTextColor(0, 0, 0);
      $pdf->Cell($distribucion['valores'], 1, '', 'B', 0, 'R');
      $pdf->Cell($distribucion['valores'], 1, '', 'B', 0, 'R');
      $pdf->Cell($distribucion['valores'], 1, number_format($cuenta['SALDO ACTUAL'], 2), 'B', 1, 'R');
    }unset($cuenta); unset($nombre);

    $distribucion = [
      "numero"=>$width*5/100,
      "titulo"=>$width*55/100,
      "valores"=>$width*20/100
    ];

    //LIBRO BALANCE
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 2, "BALANCE", 0, 1, 'C');
    if (isset($this->balance['error'])) {
      $pdf->SetFontSize(18);
      $pdf->SetTextColor(255, 0, 0);
      $pdf->Cell(0,1, "ERROR", 0, 1, 'C');
      $pdf->Cell(0,1, number_format($this->balance['error'], 2), 0, 1, 'C');
      $pdf->SetTextColor(0, 0, 0);
    }
    foreach ($this->balance['cuentas'] as $nombreCuenta => $cuenta) {
      $pdf->SetFont('Arial', '', 14);
      $pdf->Cell($distribucion['numero'], 1, $cuenta['numero'], 0, 0, 'C');
      $pdf->Cell($distribucion['titulo'], 1, $nombreCuenta, 0, 0, 'L');
      
      if ($cuenta['tipo']==='Activo') {
        $pdf->Cell($distribucion['valores'], 1, number_format($cuenta['saldo'], 2), 0, 0, 'R');
        $pdf->Cell($distribucion['valores'], 1, '', 0, 1, 'R');
      }else {
        $pdf->Cell($distribucion['valores'], 1, '', 0, 0, 'R');
        $pdf->Cell($distribucion['valores'], 1, number_format($cuenta['saldo'], 2), 0, 1, 'R');
      }
    }
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell($distribucion['numero']+$distribucion['titulo'], 1, 'SUMAS IGUALES', 0, 0, 'R');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($distribucion['valores'], 1, number_format($this->balance['totales'][0], 2), 'B', 0, 'R');
    $pdf->Cell($distribucion['valores'], 1, number_format($this->balance['totales'][1], 2), 'B', 1, 'R');
    $pdf->Output('I', "Libros", true);
  }
}


?>