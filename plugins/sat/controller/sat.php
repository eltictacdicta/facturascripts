<?php
class sat extends fs_controller
{   
    public $resultado;
    public $estado;
    public function __construct() 
    {
        parent::__construct(__CLASS__, 'Sat', 'general', FALSE, TRUE);
    }
    protected function process() {
        
        if(isset($_GET['id']))
        {
            
            $this->template="edita";
            $this->resultado=$this->devuelveSat($_GET['id']);
            if(isset($_POST['nombre']))
            {
                
               $this->edita_sat($_GET['id']);
            }
            $this->resultado=$this->devuelveSat($_GET['id']);
            $this->page->title=">> Edita SAT :".$this->resultado['nsat'];
        }
        elseif (isset ($_GET['opcion'])) 
        {
            if($_GET['opcion']=="nuevosat")
            {
                $this->page->title=">>Nuevo SAT";
                if(isset($_GET['codcliente']))
                {
                    if(isset($_POST['modelo']))
                    {
                   
                        $nsat=$this->agrega_sat($_GET['codcliente']);
                        $this->template="edita";
                        $this->resultado=$this->devuelveSat($nsat);
                        $this->page->title=">> Edita SAT :".$nsat;
                    }
                    else 
                    {
                      $this->template="agregasat";
                      $this->resultado=  $this->listar_clientes();
                      $this->resultado=  $this->resultado[0];  
                    }
                    
                }
                else 
                {
                     $this->nuevo_cliente();
                }

            }
        
        }
        else 
        {
            $this->custom_search = TRUE;
            $this->buttons[] = new fs_button_img('b_nuevo_sat', 'nuevo sat', 'add.png', 'index.php?page=sat&opcion=nuevosat');
            $this->template="sat";
        }
        
    }
    
    public function nuevo_cliente()
    {
                    $numero = $this->nuevo_numero_cliente();
                    $numero = strval(str_pad($numero, 6, "0", STR_PAD_LEFT));  
                    $this->buttons[] = new fs_button_img('b_nuevo_sat', 'nuevo cliente', 'add.png', '#nuevo');
                    //----------------------------------------------
                    //muestra una vista para seleccion de clientes
                    //----------------------------------------------
                    $this->template="satlistaclientes";
                    if(isset($_POST['nombre']))
                    {
                        //agrega el cliente que se recibe del formulario Ajax
                        require_model('cliente.php');
                        $cliente = new cliente();
                        $cliente->codcliente=$cliente->get_new_codigo();
                        $cliente->nombre=$_POST['nombre'];
                        $cliente->nombrecomercial=$_POST['nombre'];
                        $cliente->telefono1=$_POST['telefono1'];
                        $cliente->telefono2=$_POST['telefono2'];
                        $cliente->save();
                    
                    }
    }
    
    public function devuelveEstado($id_estado)
    {
        $sql="Select nombre_estado from sat_estado where id_estado=".$id_estado;
        $data= $this->db->select($sql);
        if($id_estado==0)
        {
            $data[0]['nombre_estado']=="Sin estado";
        }
        
        if($data)
        {
            return $data[0];
        }
        else 
        {
            return array();
        }
    }
    
    public function devuelveSat($id)
    {
        $sql="Select clientes.codcliente,"
                . "sat.nsat,sat.prioridad,sat.fcomienzo,sat.ffin,sat.modelo,clientes.nombre,"
                . "clientes.telefono1,clientes.telefono2,sat.estado,sat.averia,sat.accesorios,"
                . "sat.observaciones FROM sat,clientes,sat_estados WHERE sat.codcliente=clientes.codcliente "
                . "AND sat.estado=sat_estados.id_estado AND sat.nsat=".$id;
        $data= $this->db->select($sql);
        if($data)
        {
            return $data[0];
        }
        else 
        {
            return array();
        }
    }
    
    public function agrega_sat($codcliente)//le paso como parametro el numero de sat para editar
    {
            //------------Por si hemos editado algo en el cliente---------
                $codcliente=$this->empresa->var2str($codcliente);
                $nombre = $this->empresa->var2str($_POST['nombre']);
                $telefono1 = $this->empresa->var2str($_POST['telefono1']);
                $telefono2 = $this->empresa->var2str($_POST['telefono2']);
                $sql = "UPDATE clientes SET nombre=".$nombre.",telefono1=".$telefono1.",telefono2=".$telefono2
                        . "WHERE codcliente=".$codcliente;
                $this->db->exec($sql);
                
            //--------------------------------------------------------------
            //------------------Ahora los datos del sat---------------------
            $modelo = $this->empresa->var2str($_POST['modelo']);
            $fcomienzo = $this->empresa->var2str($_POST['fcomienzo']);
            $ffin = $this->empresa->var2str($_POST['ffin']);
            $averia = $this->empresa->var2str($_POST['averia']);
            $accesorios = $this->empresa->var2str($_POST['accesorios']);
            $observaciones = $this->empresa->var2str($_POST['observaciones']);
            $nsat = $this->nuevo_numero();
            $sql = "INSERT INTO sat (nsat,codcliente,modelo,fcomienzo,ffin,prioridad,estado,averia,accesorios,observaciones)"
                        . " VALUES ('".$nsat."',".$codcliente.",".$modelo.",".$fcomienzo.",".$ffin.",1,1,".$averia.",".$accesorios.",".$observaciones.")";
            $this->db->exec($sql);
            return $nsat;
            
                            
    }
    
    public function edita_sat($nsat)//le paso como parametro el numero de sat para editar
    {
            //------------Por si hemos editado algo en el cliente---------
        
                $resultado=$this->devuelveSat($nsat);//esto lo hago para saber el codigo del cliente del sat actual
                $nombre = $this->empresa->var2str($_POST['nombre']);
                $telefono1 = $this->empresa->var2str($_POST['telefono1']);
                $telefono2 = $this->empresa->var2str($_POST['telefono2']);
                $sql = "UPDATE clientes SET nombre=".$nombre.",telefono1=".$telefono1.",telefono2=".$telefono2
                        . "WHERE codcliente=".$resultado['codcliente'];
                $this->db->exec($sql);
                
            //--------------------------------------------------------------
            //------------------Ahora los datos del sat---------------------
            $modelo = $this->empresa->var2str($_POST['modelo']);
            $fcomienzo = $this->empresa->var2str($_POST['fcomienzo']);
            $ffin = $this->empresa->var2str($_POST['ffin']);
            $averia = $this->empresa->var2str($_POST['averia']);
            $accesorios = $this->empresa->var2str($_POST['accesorios']);
            $observaciones = $this->empresa->var2str($_POST['observaciones']);
            $estado=$_POST['estado'];
            $sql = "UPDATE sat SET modelo=".$modelo.",fcomienzo=".$fcomienzo.",ffin=".$ffin.",estado=".$estado.",averia=".$averia.",accesorios=".$accesorios.",observaciones=".$observaciones
                        . "WHERE nsat=".$nsat;
            $this->db->exec($sql);
                            
    }


    public function listar_sat()
    {
        $sql="SELECT sat.nsat, sat.prioridad, sat_estados.nombre_estado, sat.fcomienzo, sat.ffin, sat.modelo, clientes.nombre, clientes.telefono1, clientes.telefono2, sat.estado 
            FROM sat, clientes, sat_estados
            WHERE sat.codcliente = clientes.codcliente
            AND sat.estado = sat_estados.id_estado ";
        if(isset($_POST['query']))
        {
            $sql="SELECT sat.nsat, sat.prioridad, sat_estados.nombre_estado, sat.fcomienzo, sat.ffin, sat.modelo, clientes.nombre, clientes.telefono1, clientes.telefono2, sat.estado 
                    FROM sat, clientes, sat_estados
                    WHERE sat.codcliente = clientes.codcliente
                    AND sat.estado = sat_estados.id_estado "
                    . "AND ((lower(modelo) LIKE lower('%".$_POST['query']."%')) OR (nsat LIKE '%".$_POST['query']."%') OR (lower(nombre) LIKE lower('%".$_POST['query']."%')))";
        }
        $data=  $this->db->select($sql);
        if($data)
        {
            return $data;
        }
        else 
        {
            return array();
        }
    }
    
    public function listar_clientes()
    {
        $sql="Select codcliente,nombre,telefono1,telefono2 FROM clientes";
        if(isset($_POST['query']))
        {
            $sql="Select codcliente,nombre,telefono1,telefono2 FROM clientes "
                    . "WHERE lower(nombre) LIKE lower('%".$_POST['query']."%')";
        }
        elseif(isset($_GET['codcliente']))//devuelve cliente con un codigo en concreto
        {
                      $sql="Select codcliente,nombre,telefono1,telefono2 FROM clientes "
                    . "WHERE codcliente=".$_GET['codcliente'];  
        }
        $data=  $this->db->select($sql);
        if($data)
        {
            return $data;
        }
        else 
        {
            return array();
        }
    }
    
    public function listar_estados()
    {
        $sql="Select id_estado,nombre_estado FROM sat_estados";
        $data=  $this->db->select($sql);
        if($data)
        {
            return $data;
        }
        else 
        {
            return array();
        }
    }
    
    
    public function nuevo_numero()
    {
        $sql="select max(nsat) as nsat from sat;";
        echo $sql;
        $data = $this->db->select($sql);
        if($data)
            return intval($data[0]['nsat']) + 1;
        else
            return 1;
    }
    
    public function nuevo_numero_cliente()
    {
        $data = $this->db->select("select max(codcliente) as codcliente from clientes;");
        if($data)
            return $data[0]['codcliente'] + 1;
        else
            return 1;
    }
}

