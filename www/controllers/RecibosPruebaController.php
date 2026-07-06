<?php
// controllers/RecibosPruebaController.php

require_once "models/RecibosPruebaModel.php";
require_once "views/RecibosPruebaView.php";
require_once "models/EstudiantesModel.php"; // Necesario para obtener la lista de alumnos

class RecibosPruebaController {

    private function generarNumeroRecibo($aleatorio) {
        // Formato: AÑO-MES-DÍA-HORA-ALEATORIO (ej: 20251119-2319-1234)
        $prefijo = date('Ymd-Hi');
        return $prefijo . '-' . str_pad($aleatorio, 4, '0', STR_PAD_LEFT);
    }

    public function handle($action) {
        $mensaje = $_GET['msg'] ?? null; // Capturar mensaje de éxito

        switch ($action) {
            
            case 'form_ingresar':
                $alumnos = EstudiantesModel::obtenerTodos(); // Asumiendo que existe este método en EstudiantesModel
                RecibosPruebaView::mostrarFormularioIngreso($alumnos, $mensaje);
                break;

            case 'guardar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    
                    // 1. Recolección de datos POST
                    $idAlumno = $_POST['idAlumno'] ?? null;
                    $mesPago = $_POST['mesPago'] ?? '';
                    $detalle = trim($_POST['detalle'] ?? '');
                    $efectivo = $_POST['efectivo'] ?? 0.00;
                    $deposito = $_POST['deposito'] ?? 0.00;
                    
                    $errores = [];

                    // 2. Validación
                    if (empty($idAlumno)) $errores[] = "Debe seleccionar un Alumno.";
                    if (empty($mesPago)) $errores[] = "Debe seleccionar el mes de pago.";
                    if (empty($detalle)) $errores[] = "El detalle es obligatorio.";

                    // Asegurar que al menos un monto sea ingresado
                    if ((float)$efectivo <= 0 && (float)$deposito <= 0) {
                        $errores[] = "Debe ingresar un monto en Efectivo o Depósito.";
                    }

                    // 3. Generación de datos automáticos
                    $numeroAleatorio = mt_rand(1, 9999);
                    $numeroRecibo = $this->generarNumeroRecibo($numeroAleatorio);
                    
                    // La fecha se genera en el Modelo (NOW())

                    if (count($errores) > 0) {
                        $alumnos = EstudiantesModel::obtenerTodos();
                        $data = $_POST;
                        RecibosPruebaView::mostrarFormularioIngreso($alumnos, $errores, $data);
                    } else {
                        // 4. Inserción en el Modelo
                        $resultado = RecibosPruebaModel::insertar([
                            'idAlumno' => $idAlumno,
                            'mesPago' => $mesPago,
                            'detalle' => $detalle,
                            'efectivo' => $efectivo,
                            'deposito' => $deposito,
                            'numeroRecibo' => $numeroRecibo,
                            'numeroAleatorio' => $numeroAleatorio
                        ]);

                        if ($resultado) {
                            // Éxito: Redirigir al mismo formulario con mensaje de éxito
                            header("Location: index.php?action=form_ingresar&msg=exito");
                            exit;
                        } else {
                            // Error de la base de datos (ej. duplicidad de numeroRecibo)
                             $alumnos = EstudiantesModel::obtenerTodos();
                            RecibosPruebaView::mostrarFormularioIngreso($alumnos, ["Error al guardar el recibo. Intente de nuevo."], $_POST);
                        }
                    }
                }
                break;

            default:
                // Redirige al formulario de ingreso por defecto
                header("Location: index.php?action=form_ingresar");
                exit;
        }
    }
}
?>
