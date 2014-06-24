<?php

class gatos extends fs_controller
{
    public function __construct() {
        parent::__construct(__CLASS__, 'Gatos', 'general', FALSE, TRUE);
    }
    
    protected function process() {
        if( isset($_POST['peso']) )
        {
            $numero = $this->empresa->var2str($this->nuevo_numero());
            $peso = $this->empresa->var2str(intval($_POST['peso']));
            $entrada = $this->empresa->var2str($_POST['entrada']);
            $fecha_fin = $this->empresa->var2str($_POST['fechafin']);
            
            $this->db->exec("INSERT INTO gatos (numero,peso,entrada,fecha_fin)
                    VALUES (".$numero.",".$peso.",".$entrada.",".$fecha_fin.");");
        }
    }
    
    public function listar_gatos()
    {
        $data = $this->db->select("select * from gatos;");
        if($data)
            return $data;
        else
            return array();
    }
    
    public function nuevo_numero()
    {
        $data = $this->db->select("select max(numero) as num from gatos;");
        if($data)
            return intval($data[0]['num']) + 1;
        else
            return 1;
    }
}