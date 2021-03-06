<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if(strtolower(FS_DB_TYPE) == 'mysql')
   require_once 'base/fs_mysql.php';
else
   require_once 'base/fs_postgresql.php';

require_once 'base/fs_button.php';
require_once 'base/fs_cache.php';
require_once 'base/fs_default_items.php';
require_once 'base/fs_model.php';

require_model('agente.php');
require_model('divisa.php');
require_model('empresa.php');
require_model('fs_access.php');
require_model('fs_page.php');
require_model('fs_user.php');

/**
 * La clase principal de la que deben heredar todos los controladores
 * (las páginas) de FacturaScripts.
 */
class fs_controller
{
   /**
    * Este objeto permite acceso directo a la base de datos.
    * @var type es una instancia de fs_mysql o fs_postgresql
    */
   protected $db;
   private $uptime;
   private $errors;
   private $messages;
   private $advices;
   private $last_changes;
   private $simbolo_divisas;
   
   /**
    * El usuario que ha hecho login
    * @var type 
    */
   public $user;
   
   /**
    * El elemento del menú de esta página
    * @var type 
    */
   public $page;
   
   /**
    * La página previa (si está definida)
    * @var type 
    */
   public $ppage;
   private $admin_page;
   protected $menu;
   
   /**
    * Indica que archivo HTML hay que cargar
    * @var type 
    */
   public $template;
   public $css_file;
   
   /**
    * TRUE si queremos mostrar el cuadro de búsqueda.
    * @var type 
    */
   public $custom_search;
   
   /**
    * La cadena obtenida del formulario de búsqueda
    * @var type 
    */
   public $query;
   
   /**
    * Lista de botones que aparecen en la parte superior de la cabecera
    * @var type 
    */
   public $buttons;
   
   /**
    * La empresa
    * @var type 
    */
   public $empresa;
   public $default_items;
   
   /**
    * Este objeto permite interactuar con memcache
    * @var type 
    */
   protected $cache;
   
   /**
    * @param type $name sustituir por __CLASS__
    * @param type $title es el título de la página, y el texto que aparecerá en el menú
    * @param type $folder es el menú dónde quieres colocar el acceso directo
    * @param type $admin debe ser TRUE si quieres que solamente un administrador pueda ver esta página
    * @param type $shmenu debe ser TRUE si quieres añadir el acceso directo en el menú
    * @param type $important debe ser TRUE si quieres que se la primera página que ven los nuevos usuarios
    */
   public function __construct($name='', $title='home', $folder='', $admin=FALSE, $shmenu=TRUE, $important=FALSE)
   {
      $tiempo = explode(' ', microtime());
      $this->uptime = $tiempo[1] + $tiempo[0];
      $this->admin_page = $admin;
      $this->errors = array();
      $this->messages = array();
      $this->advices = array();
      $this->simbolo_divisas = array();
      
      if(strtolower(FS_DB_TYPE) == 'mysql')
         $this->db = new fs_mysql();
      else
         $this->db = new fs_postgresql();
      
      $this->cache = new fs_cache();
      $this->set_css_file();
      
      /// comprobamos la versión de PHP
      if( floatval( substr(phpversion(), 0, 3) ) < 5.3 )
         $this->new_error_msg('FacturaScripts necesita PHP 5.3 o superior, y tú tienes PHP '.phpversion().'.');
      
      if( $this->db->connect() )
      {
         $this->user = new fs_user();
         $this->page = new fs_page( array('name'=>$name, 'title'=>$title, 'folder'=>$folder,
             'version'=>$this->version(), 'show_on_menu'=>$shmenu, 'important'=>$important) );
         $this->ppage = FALSE;
         $this->empresa = new empresa();
         $this->default_items = new fs_default_items();
         
         if( isset($_GET['logout']) )
         {
            $this->template = 'login/default';
            $this->log_out();
         }
         else if( isset($_POST['new_password']) AND isset($_POST['new_password2']) )
         {
            $ips = array();
            
            if($_POST['new_password'] != $_POST['new_password2'])
               $this->new_error_msg('Las contraseñas no coinciden.');
            else if($_POST['new_password'] == '')
               $this->new_error_msg('Tienes que escribir una contraseña nueva.');
            else if($_POST['db_password'] != FS_DB_PASS)
               $this->new_error_msg('La contraseña de la base de datos es incorrecta.');
            else if( $this->ip_baneada($ips) )
            {
               $this->banear_ip($ips);
               $this->new_error_msg('Tu IP ha sido baneada. Tendrás que esperar
                  10 minutos antes de volver a intentar entrar.');
            }
            else
            {
               $suser = $this->user->get($_POST['user']);
               if($suser)
               {
                  $suser->set_password($_POST['new_password']);
                  if( $suser->save() )
                     $this->new_message('Contraseña cambiada correctamente.');
                  else
                     $this->new_error_msg('Imposible cambiar la contraseña del usuario.');
               }
            }
            
            $this->template = 'login/default';
         }
         else if( !$this->log_in() )
         {
            $this->template = 'login/default';
         }
         else if( $this->user->have_access_to($this->page->name, $this->admin_page) )
         {
            if($name == '')
            {
               $this->template = 'index';
            }
            else
            {
               $this->set_default_items();
               
               $this->template = $name;
               $this->buttons = array();
               
               $this->custom_search = FALSE;
               if( isset($_POST['query']) )
                  $this->query = $_POST['query'];
               else if( isset($_GET['query']) )
                  $this->query = $_GET['query'];
               else
                  $this->query = '';
               
               $this->process();
            }
         }
         else if($name == '')
         {
            $this->template = 'index';
         }
         else
         {
            $this->template = 'access_denied';
            $this->user->clean_cache(TRUE);
            $this->empresa->clean_cache();
         }
      }
      else
      {
         $this->template = 'no_db';
         $this->new_error_msg('¡Imposible conectar con la base de datos <b>'.FS_DB_NAME.'</b>!');
      }
   }
   
   /**
    * Devuelve la versión de FacturaScripts
    * @return type versión de FacturaScripts
    */
   public function version()
   {
      return file_get_contents('VERSION');
   }
   
   /**
    * Cierra la conexión con la base de datos
    */
   public function close()
   {
      $this->db->close();
   }
   
   /**
    * Muestra al usuario un mensaje de error
    * @param type $msg el mensaje a mostrar
    */
   public function new_error_msg($msg=FALSE)
   {
      if( $msg )
         $this->errors[] = str_replace("\n", ' ', $msg);
   }
   
   /**
    * Devuelve la lista de errores
    * @return type lista de errores
    */
   public function get_errors()
   {
      $full = array_merge( $this->errors, $this->db->get_errors() );
      
      if( isset($this->empresa) )
         $full = array_merge( $full, $this->empresa->get_errors() );
      
      return $full;
   }
   
   /**
    * Muestra un mensaje al usuario
    * @param type $msg mensaje a mostrar
    */
   public function new_message($msg=FALSE)
   {
      if( $msg )
         $this->messages[] = str_replace("\n", ' ', $msg);
   }
   
   /**
    * Devuelve la lista de mensajes
    * @return type lista de mensajes
    */
   public function get_messages()
   {
      return $this->messages;
   }
   
   /**
    * Muestra un consejo al usuario
    * @param type $msg el consejo a mostrar
    */
   public function new_advice($msg=FALSE)
   {
      if( $msg )
         $this->advices[] = str_replace("\n", ' ', $msg);
   }
   
   /**
    * Devuelve la lista de consejos
    * @return type lista de consejos
    */
   public function get_advices()
   {
      return $this->advices;
   }
   
   /**
    * Devuelve la URL de esta página (index.php?page=LO-QUE-SEA)
    * @return type
    */
   public function url()
   {
      return $this->page->url();
   }
   
   /**
    * Una IP será baneada si falla más de 5 intentos de login en menos de 10 minutos
    * @param type $ips es un array de IP;intentos;hora
    * @return boolean
    */
   private function ip_baneada(&$ips)
   {
      $baneada = FALSE;
      
      if( file_exists('tmp/ip.log') )
      {
         $file = fopen('tmp/ip.log', 'r');
         if($file)
         {
            /// leemos las líneas
            while( !feof($file) )
            {
               $linea = explode(';', trim(fgets($file)));
               
               if( intval($linea[2]) > time() )
               {
                  if($linea[0] == $_SERVER['REMOTE_ADDR'] AND intval($linea[1]) > 5)
                     $baneada = TRUE;
                  
                  $ips[] = $linea;
               }
            }
            
            fclose($file);
         }
      }
      
      return $baneada;
   }
   
   /**
    * Baneamos las IPs que fallan más de 5 intentos de login en 10 minutos
    * @param type $ips es un array de IP;intentos;hora
    */
   private function banear_ip(&$ips)
   {
      $file = fopen('tmp/ip.log', 'w');
      if($file)
      {
         $encontrada = FALSE;
         
         foreach($ips as $ip)
         {
            if($ip[0] == $_SERVER['REMOTE_ADDR'])
            {
               fwrite( $file, $ip[0].';'.( 1+intval($ip[1]) ).';'.( time()+600 ) );
               $encontrada = TRUE;
            }
            else
               fwrite( $file, join(';', $ip) );
         }
         
         if(!$encontrada)
            fwrite( $file, $_SERVER['REMOTE_ADDR'].';1;'.( time()+600 ) );
         
         fclose($file);
      }
   }
   
   /**
    * Devuelve TRUE si el usuario realmente tiene acceso a esta página
    * @return type
    */
   private function log_in()
   {
      $ips = array();
      
      if( $this->ip_baneada($ips) )
      {
         $this->banear_ip($ips);
         $this->new_error_msg('Tu IP ha sido baneada. Tendrás que esperar
            10 minutos antes de volver a intentar entrar.');
      }
      else if( isset($_POST['user']) AND isset($_POST['password']) )
      {
         if( FS_DEMO ) /// en el modo demo nos olvidamos de la contraseña
         {
            $user = $this->user->get($_POST['user']);
            if( !$user )
            {
               $user = new fs_user();
               $user->nick = $_POST['user'];
               $user->set_password('demo');
               $user->admin = TRUE;
               
               /// creamos un agente para asociarlo
               $agente = new agente();
               $agente->codagente = $agente->get_new_codigo();
               $agente->nombre = $_POST['user'];
               $agente->apellidos = 'Demo';
               if( $agente->save() )
                  $user->codagente = $agente->codagente;
            }
            
            $user->new_logkey();
            if( $user->save() )
            {
               setcookie('user', $user->nick, time()+FS_COOKIES_EXPIRE);
               setcookie('logkey', $user->log_key, time()+FS_COOKIES_EXPIRE);
               $this->user = $user;
               $this->load_menu();
            }
         }
         else
         {
            $user = $this->user->get($_POST['user']);
            $password = strtolower($_POST['password']);
            if($user)
            {
               if( $user->password == sha1($password) )
               {
                  $user->new_logkey();
                  if( $user->save() )
                  {
                     setcookie('user', $user->nick, time()+FS_COOKIES_EXPIRE);
                     setcookie('logkey', $user->log_key, time()+FS_COOKIES_EXPIRE);
                     $this->user = $user;
                     $this->load_menu();
                  }
                  else
                     $this->new_error_msg('Imposible guardar los datos de usuario.');
               }
               else
               {
                  $this->new_error_msg('¡Contraseña incorrecta!');
                  $this->banear_ip($ips);
               }
            }
            else
            {
               $this->new_error_msg('El usuario no existe!');
               $this->user->clean_cache(TRUE);
               $this->cache->clean();
            }
         }
      }
      else if( isset($_COOKIE['user']) AND isset($_COOKIE['logkey']) )
      {
         $user = $this->user->get($_COOKIE['user']);
         if($user)
         {
            if($user->log_key == $_COOKIE['logkey'])
            {
               $user->logged_on = TRUE;
               $user->update_login();
               $this->user = $user;
               $this->load_menu();
            }
            else if( !is_null($user->log_key) )
            {
               $this->new_message('¡Cookie no válida! Tú o alguien ha accedido a esta cuenta desde otro PC.');
               $this->log_out();
            }
         }
         else
         {
            $this->new_message('¡El usuario no existe!');
            $this->log_out();
            $this->user->clean_cache(TRUE);
            $this->cache->clean();
         }
      }
      
      return $this->user->logged_on;
   }
   
   /**
    * Gestiona el cierre de sesión
    */
   private function log_out()
   {
      setcookie('logkey', '', time()-FS_COOKIES_EXPIRE);
   }
   
   /**
    * Devuelve la duración de la ejecución de la página
    * @return type un string con la duración de la ejecución
    */
   public function duration()
   {
      $tiempo = explode(" ", microtime());
      return (number_format($tiempo[1] + $tiempo[0] - $this->uptime, 3) . ' s');
   }
   
   /**
    * Devuelve el número de consultas SQL (SELECT) que se han ejecutado
    * @return type
    */
   public function selects()
   {
      return $this->db->get_selects();
   }
   
   /**
    * Devuleve el número de transacciones SQL que se han ejecutado
    * @return type
    */
   public function transactions()
   {
      return $this->db->get_transactions();
   }
   
   /**
    * Devuelve el listado de consultas SQL que se han ejecutados
    * @return type lista de consultas SQL
    */
   public function get_db_history()
   {
      return $this->db->get_history();
   }
   
   /**
    * Carga el menú de facturaScripts
    * @param type $reload TRUE si quieres recargar
    */
   protected function load_menu($reload=FALSE)
   {
      $this->menu = $this->user->get_menu($reload);
      
      /// actualizamos los datos de la página
      foreach($this->menu as $m)
      {
         if($m->name == $this->page->name AND ($m->version != $this->page->version OR $m->title != $this->page->title))
         {
            if( !$this->page->save() )
               $this->new_error_msg('Imposible actualizar los datos de esta página.');
            
            break;
         }
      }
   }
   
   /**
    * Devuelve la lista de menús
    * @return type lista de menús
    */
   public function folders()
   {
      $folders = array();
      foreach($this->menu as $m)
      {
         if($m->folder!='' AND $m->show_on_menu AND !in_array($m->folder, $folders) )
            $folders[] = $m->folder;
      }
      return $folders;
   }
   
   /**
    * Devuelve la lista de elementos de un menú seleccionado
    * @param type $f el menú seleccionado
    * @return type lista de elementos del menú
    */
   public function pages($f='')
   {
      $pages = array();
      foreach($this->menu as $p)
      {
         if($f == $p->folder AND $p->show_on_menu AND !in_array($p, $pages) )
            $pages[] = $p;
      }
      return $pages;
   }
   
   /**
    * Esta es la función que se ejecuta en el constructor si, y sólo si,
    * el usuario realmente tiene acceso a la página
    */
   protected function process()
   {
      
   }
   
   /**
    * Redirecciona a la página predeterminada para el usuario
    */
   public function select_default_page()
   {
      if( $this->db->connected() )
      {
         if( $this->user->logged_on )
         {
            $url = FALSE;
            
            if( is_null($this->user->fs_page) )
            {
               $url = 'index.php?page=admin_pages';
               
               /*
                * Cuando un usuario no tiene asignada una página por defecto,
                * se selecciona la primera página importante a la que tiene acceso.
                */
               foreach($this->menu as $p)
               {
                  if($p->important)
                  {
                     $url = $p->url();
                     break;
                  }
                  else if($p->show_on_menu)
                     $url = $p->url();
               }
            }
            else
               $url = 'index.php?page=' . $this->user->fs_page;
            
            Header('location: '.$url);
         }
      }
   }
   
   /**
    * Selecciona la hoja de estilos
    */
   private function set_css_file()
   {
      if( isset($_GET['css_file']) )
      {
         if( file_exists('view/css/'.$_GET['css_file']) )
         {
            $this->css_file = $_GET['css_file'];
            setcookie('css_file', $_GET['css_file'], time()+FS_COOKIES_EXPIRE);
         }
         else
         {
            $this->new_error_msg("Archivo CSS no encontrado.");
            $this->css_file = 'base.css';
         }
      }
      else if( isset($_COOKIE['css_file']) )
      {
         if( file_exists('view/css/'.$_COOKIE['css_file']) )
            $this->css_file = $_COOKIE['css_file'];
         else
         {
            $this->new_error_msg("Archivo CSS no encontrado.");
            $this->css_file = 'base.css';
            setcookie('css_file', $this->css_file, time()+FS_COOKIES_EXPIRE);
         }
      }
      else
         $this->css_file = 'base.css';
   }
   
   /**
    * Devuelve TRUE si la página sólo es accesible para administradores
    * @return type TRUE si la página sólo es accesible para administradores
    */
   public function is_admin_page()
   {
      return $this->admin_page;
   }
   
   /**
    * Establecemos los elementos por defecto, pero no se guardan.
    * Para guardarlos hay que usar las funciones fs_controller::save_lo_que_sea().
    * La clase fs_default_items sólo se usa para indicar valores
    * por defecto a los modelos.
    */
   private function set_default_items()
   {
      /// gestionamos la página de inicio
      if( isset($_GET['default_page']) )
      {
         if($_GET['default_page'] == 'FALSE')
         {
            $this->default_items->set_default_page(NULL);
            $this->user->fs_page = NULL;
         }
         else
         {
            $this->default_items->set_default_page( $this->page->name );
            $this->user->fs_page = $this->page->name;
         }
         
         $this->user->save();
      }
      else if( is_null($this->default_items->default_page()) )
         $this->default_items->set_default_page( $this->user->fs_page );
      
      if( is_null($this->default_items->showing_page()) )
         $this->default_items->set_showing_page( $this->page->name );
      
      $this->default_items->set_codejercicio( $this->user->codejercicio );
      
      if( isset($_COOKIE['default_almacen']) )
         $this->default_items->set_codalmacen( $_COOKIE['default_almacen'] );
      else
         $this->default_items->set_codalmacen( $this->empresa->codalmacen );
      
      if( isset($_COOKIE['default_cliente']) )
         $this->default_items->set_codcliente( $_COOKIE['default_cliente'] );
      
      if( isset($_COOKIE['default_divisa']) )
         $this->default_items->set_coddivisa( $_COOKIE['default_divisa'] );
      else
         $this->default_items->set_coddivisa( $this->empresa->coddivisa );
      
      if( isset($_COOKIE['default_familia']) )
         $this->default_items->set_codfamilia( $_COOKIE['default_familia'] );
      
      if( isset($_COOKIE['default_formapago']) )
         $this->default_items->set_codpago( $_COOKIE['default_formapago'] );
      else
         $this->default_items->set_codpago( $this->empresa->codpago );
      
      if( isset($_COOKIE['default_impuesto']) )
         $this->default_items->set_codimpuesto( $_COOKIE['default_impuesto'] );
      
      if( isset($_COOKIE['default_pais']) )
         $this->default_items->set_codpais( $_COOKIE['default_pais'] );
      else
         $this->default_items->set_codpais( $this->empresa->codpais );
      
      if( isset($_COOKIE['default_proveedor']) )
         $this->default_items->set_codproveedor( $_COOKIE['default_proveedor'] );
      
      if( isset($_COOKIE['default_serie']) )
         $this->default_items->set_codserie( $_COOKIE['default_serie'] );
      else
         $this->default_items->set_codserie( $this->empresa->codserie );
   }
   
   /**
    * Establece un ejercicio como predeterminado para este usuario
    * @param type $cod el código del ejercicio
    */
   protected function save_codejercicio($cod)
   {
      if($cod != $this->user->codejercicio)
      {
         $this->default_items->set_codejercicio($cod);
         $this->user->codejercicio = $cod;
         if( !$this->user->save() )
         {
            $this->new_error_msg('Error al establecer el ejercicio '.$cod.
               ' como ejercicio predeterminado para este usuario.');
         }
      }
   }
   
   /**
    * Establece un almacén como predeterminado para este usuario
    * @param type $cod el código del almacén
    */
   protected function save_codalmacen($cod)
   {
      setcookie('default_almacen', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codalmacen($cod);
   }
   
   /**
    * Establece un cliente como predeterminado para este usuario
    * @param type $cod el código del cliente
    */
   protected function save_codcliente($cod)
   {
      setcookie('default_cliente', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codcliente($cod);
   }
   
   /**
    * Establece una divisa como predeterminada para este usuario
    * @param type $cod el código de la divisa
    */
   protected function save_coddivisa($cod)
   {
      setcookie('default_divisa', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_coddivisa($cod);
   }
   
   /**
    * Establece una familia como predeterminada para este usuario
    * @param type $cod el código de la familia
    */
   protected function save_codfamilia($cod)
   {
      setcookie('default_familia', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codfamilia($cod);
   }
   
   /**
    * Establece una forma de pago como predeterminada para este usuario
    * @param type $cod el código de la forma de pago
    */
   protected function save_codpago($cod)
   {
      setcookie('default_formapago', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codpago($cod);
   }
   
   /**
    * Establece un impuesto (IVA) como predeterminado para este usuario
    * @param type $cod el código del iumpuesto
    */
   protected function save_codimpuesto($cod)
   {
      setcookie('default_impuesto', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codimpuesto($cod);
   }
   
   /**
    * Establece un código de país como predeterminado para este usuario
    * @param type $cod el código del país
    */
   protected function save_codpais($cod)
   {
      setcookie('default_pais', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codpais($cod);
   }
   
   /**
    * Establece un proveedor como predeterminado para este usuario
    * @param type $cod el código del proveedor
    */
   protected function save_codproveedor($cod)
   {
      setcookie('default_proveedor', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codproveedor($cod);
   }
   
   /**
    * Establece una serie como predeterminada para este usuario
    * @param type $cod el código de la serie
    */
   protected function save_codserie($cod)
   {
      setcookie('default_serie', $cod, time()+FS_COOKIES_EXPIRE);
      $this->default_items->set_codserie($cod);
   }
   
   /**
    * Devuelve la fecha actual
    * @return type la fecha en formato día-mes-año
    */
   public function today()
   {
      return date('d-m-Y');
   }
   
   /**
    * Devuelve la hora actual
    * @return type la hora en formato hora:minutos:segundos
    */
   public function hour()
   {
      return Date('H:i:s');
   }
   
   /**
    * Devuelve un string aleatorio de longitud $length
    * @param type $length la longitud del string
    * @return type la cadena aleatoria
    */
   public function random_string($length = 30)
   {
      return mb_substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"),
              0, $length);
   }
   
   /**
    * He detectado que algunos navegadores, en algunos casos, envían varias veces la
    * misma petición del formulario. En consecuencia se crean varios modelos (asientos,
    * albaranes, etc...) con los mismos datos, es decir, duplicados.
    * Para solucionarlo añado al formulario un campo petition_id con una cadena
    * de texto aleatoria. Al llamar a esta función se comprueba si esa cadena
    * ya ha sido almacenada, de ser así devuelve TRUE, así no hay que gabar los datos,
    * si no, se almacena el ID y se devuelve FALSE.
    * @param type $id el identificador de la petición
    * @return boolean TRUE si la petición está duplicada
    */
   protected function duplicated_petition($id)
   {
      $ids = $this->cache->get_array('petition_ids');
      if( in_array($id, $ids) )
         return TRUE;
      else
      {
         $ids[] = $id;
         $this->cache->set('petition_ids', $ids, 300);
         return FALSE;
      }
   }
   
   /**
    * Devuelve información del sistema para el informe de errores
    * @return type la información del sistema
    */
   public function system_info()
   {
      $txt = 'facturascripts: '.$this->version()."\n";
      $txt .= 'os: '.php_uname()."\n";
      $txt .= 'php: '.phpversion()."\n";
      $txt .= 'database type: '.FS_DB_TYPE."\n";
      $txt .= 'database version: '.$this->db->version()."\n";
      
      if( $this->cache->connected() )
         $txt .= "memcache: YES\n";
      else
         $txt .= "memcache: NO\n";
      
      $txt .= 'memcache version: '.$this->cache->version()."\n";
      
      if( isset($_SERVER['REQUEST_URI']) )
         $txt .= 'url: '.$_SERVER['REQUEST_URI']."\n------";
      
      foreach($this->get_errors() as $e)
         $txt .= "\n" . $e;
      
      return str_replace('"', "'", $txt);
   }
   
   /**
    * Devuelve el símbolo de divisa predeterminado
    * o bien el símbolo de la divisa seleccionada.
    * @param type $coddivisa
    * @return string
    */
   public function simbolo_divisa($coddivisa = FALSE)
   {
      if(!$coddivisa)
         $coddivisa = $this->empresa->coddivisa;
      
      if( isset($this->simbolo_divisas[$coddivisa]) )
         return $this->simbolo_divisas[$coddivisa];
      else
      {
         $divisa = new divisa();
         $divi0 = $divisa->get($coddivisa);
         if($divi0)
         {
            $this->simbolo_divisas[$coddivisa] = $divi0->simbolo;
            return $divi0->simbolo;
         }
         else
            return '?';
      }
   }
   
   /**
    * Devuelve un string con el precio en el formato predefinido y con la
    * divisa seleccionada (o la predeterminada).
    * 
    * @param type $precio
    * @param type $coddivisa
    * @param type $simbolo
    * @return type
    */
   public function show_precio($precio=0, $coddivisa=FALSE, $simbolo=TRUE)
   {
      if($coddivisa === FALSE)
         $coddivisa = $this->empresa->coddivisa;
      
      if(FS_POS_DIVISA == 'right')
      {
         if($simbolo)
            return number_format($precio, FS_NF0, FS_NF1, FS_NF2).' '.$this->simbolo_divisa($coddivisa);
         else
            return number_format($precio, FS_NF0, FS_NF1, FS_NF2).' '.$coddivisa;
      }
      else
      {
         if($simbolo)
            return $this->simbolo_divisa($coddivisa).number_format($precio, FS_NF0, FS_NF1, FS_NF2);
         else
            return $coddivisa.' '.number_format($precio, FS_NF0, FS_NF1, FS_NF2);
      }
   }
   
   /**
    * Devuelve un string con el número en el formato de número predeterminado.
    * @param type $num
    * @param type $decimales
    * @param type $js
    * @return type
    */
   public function show_numero($num=0, $decimales=FS_NF0, $js=FALSE)
   {
      if($js)
         return number_format($num, $decimales, '.', '');
      else
         return number_format($num, $decimales, FS_NF1, FS_NF2);
   }
   
   /**
    * Añade un elemento a la lista de cambios del usuario.
    * @param type $txt texto descriptivo.
    * @param type $url URL del elemento (albarán, factura, artículos...).
    * @param type $nuevo TRUE si el elemento es nuevo, FALSE si se ha modificado.
    */
   public function new_change($txt, $url, $nuevo=FALSE)
   {
      $this->get_last_changes();
      if( count($this->last_changes) > 0 )
      {
         if($this->last_changes[0]['url'] == $url)
            $this->last_changes[0]['nuevo'] = $nuevo;
         else
            array_unshift($this->last_changes, array('texto' => ucfirst($txt), 'url' => $url, 'nuevo' => $nuevo, 'cambio' => date('d-m-Y H:i:s')) );
      }
      else
         array_unshift($this->last_changes, array('texto' => ucfirst($txt), 'url' => $url, 'nuevo' => $nuevo, 'cambio' => date('d-m-Y H:i:s')) );
      
      /// sólo queremos 10 elementos
      $num = 10;
      foreach($this->last_changes as $i => $value)
      {
         if($num > 0)
         {
            $num--;
         }
         else
         {
            unset($this->last_changes[$i]);
         }
      }
      
      $this->cache->set('last_changes_'.$this->user->nick, $this->last_changes);
   }
   
   /**
    * Devuelve la lista con los últimos cambios del usuario.
    */
   public function get_last_changes()
   {
      if( !isset($this->last_changes) )
      {
         $this->last_changes = $this->cache->get_array('last_changes_'.$this->user->nick);
      }
      
      return $this->last_changes;
   }
}
