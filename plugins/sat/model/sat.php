<?php

class modelosat extends fs_model
{
    public $nsat;
    public $prioridad;
    public $fcomienzo;
    public $ffin;
    public $modelo;
    public $codcliente;
    public $estado;
    public $averia;
    public $accesorios;
    public $observaciones;
    public function __construct() {
        parent::__construct('sat', 'plugins/sat/');
    }


    public function install() {
        ;
    }
    
    public function exists() {
        ;
    }
    
    public function delete() {
        ;
    }
    
    public function test() {
        
    }

    public function save() {
        ;
    }
}

?>
