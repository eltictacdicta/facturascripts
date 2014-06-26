<?php

class sat_estado extends fs_model
{
    public $id_estado;
    public $nombre_estado;
    public function __construct() {
        parent::__construct('sat_estados', 'plugins/sat/');
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
