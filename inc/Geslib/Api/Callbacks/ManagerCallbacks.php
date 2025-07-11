<?php
/**
 * @package biblio
 */

 namespace Inc\Geslib\Api\Callbacks;

use Inc\Geslib\Base\BaseController;

 class ManagerCallbacks extends BaseController
 {

  public function checkboxSanitize( $input ) {

    $output = [];
    foreach ( $this->managers as $key => $value ){
      $output[$key] = isset( $input[$key] ) ? true : false;
    }
    return $output;
  }

  public function adminSectionManager() {
    echo 'manage the Sections and Features of this plugin by activating the checkboxes in the list below';
  }

  public function checkboxField( $args )
  {
    $name = $args['label_for'];
    $classes = $args['class'];
    $option_name = $args['option_name'];
    $checkbox = get_option( $option_name );
    $checked = isset($checkbox[$name]) ? ($checkbox[$name] ? true : false ) : false;
    echo "<div class='".$classes."'>
          <input
            class=''
            type='checkbox'
            id='" . $name . "'
            name='" . $option_name . "[" . $name . "]'
            value='1'".
            ($checked ? " checked" : "") . ">
              <label for='".$name."'><div></div></label>
          </div>";
  }
  public static function getLineTypes() {
    return [
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
}