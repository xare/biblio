<?php
/**
 * @package geslib
 */

 namespace Inc\Geslib\Base;

 class BaseController
 {
  public $plugin_path;
  public $plugin_url;
  public $plugin;
  public $plugin_templates_path;
  public array $managers;

  public function __construct() {
    $this->plugin_path = plugin_dir_path( dirname( __FILE__, 3));
    $this->plugin_templates_path = plugin_dir_path( dirname( __FILE__, 3)).'templates';
    $this->plugin_url = plugin_dir_url( dirname( __FILE__, 3));
    $this->plugin = plugin_basename( dirname( __FILE__, 3) ) . '/biblio.php';
    $this->managers = [
      '1L' => 'Editoriales',
        '1A' => 'Compañías discográficas',
        '1P' => 'Familias de papelería',
        '1R' => 'Publicaciones de prensa',
        '2' => 'Colecciones editoriales',
        '3' => 'Materias',
        'GP4' => 'Artículos',
        'EB' => 'eBooks (igual que los libros)',
        'IEB' => 'Información propia del eBook',
        '5' => 'Materias asociadas a los artículos',
        'BIC' => 'Materias IBIC asociadas a los artículos',
        '6' => 'Referencias de la librería',
        '6E' => 'Referencias del editor',
        '6I' => 'Índice del libro',
        '6T' => 'Referencias de la librería (traducidas)',
        '6TE' => 'Referencias del editor (traducidas)',
        '6IT' => 'Índice del libro (traducido)',
        'LA' => 'Autores normalizados asociados a un artículo',
        '7' => 'Formatos de encuadernación',
        '8' => 'Idiomas',
        '9' => 'Palabras vacías',
        'B' => 'Stock',
        'B2' => 'Stock por centros',
        'E' => 'Estados de artículos',
        'CLI' => 'Clientes',
        'AUT' => 'Autores',
        'AUTBIO' => 'Biografías de Autores',
        'I' => 'Indicador de carga inicial',
        'IPC' => 'Incidencias en pedidos de clientes',
        'P' => 'Promociones de artículos (globales a todos los centros)',
        'PROCEN' => 'Promociones de artículos por centros',
        'PC' => 'Pedidos de clientes',
        'VTA' => 'Ventas',
        'PAIS' => 'Países',
        'CLOTE' => 'Lotes de artículos: Cabecera',
        'LLOTE' => 'Lotes de artículos: Líneas',
        'TIPART' => 'Tipos de artículos',
        'CLASIF' => 'Clasificaciones de artículos',
        'ATRA' => 'Traducciones asociadas a los artículos',
        'ARTATR' => 'Artículos Traducción',
        'CA' => 'Claves alternativas asociadas a los artículos',
        'CLOTCLI' => 'Lotes de clientes: Cabecera',
        'LLOTCLI' => 'Lotes de clientes: Líneas',
        'PROFES' => 'Profesiones',
        'PROVIN' => 'Provincias',
        'CAGRDTV' => 'Agrupaciones de descuentos de ventas: Cabecera',
        'LAGRDTV' => 'Agrupaciones de descuentos de ventas: Líneas'
    ];
  }

  public function activated(string $key)
  {
    $option = get_option('geslib');
    return isset($option[$key]) ? $option[$key] : false;
  }
 }