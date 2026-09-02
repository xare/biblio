<?php

namespace Inc\Geslib\Config;

class LineTypes
{
    const PRODUCT_DELETE_KEYS = [
        "type",
        "action",
        "geslib_id"
    ];

    const AUTHOR_DELETE_KEYS = [
        "type",
        "action",
        "geslib_id"
    ];

    const EDITORIAL_DELETE_KEYS = [
        "type",
        "action",
        "geslib_id"
    ];

    const CATEGORIA_DELETE_KEYS = [
        "type",
        "action",
        "geslib_id"
    ];

    const PRODUCT_KEYS = [
        "type",
        "action",
        "geslib_id",
        "description",
        "author",
        "pvp_ptas",
        "isbn",
        "ean",
        "num_paginas",
        "num_edicion",
        "origen_edicion",
        "fecha_edicion",
        "fecha_reedicion",
        "año_primera_edicion",
        "año_ultima_edicion",
        "ubicacion",
        "stock",
        "materia",
        "fecha_alta",
        "fecha_novedad",
        "Idioma",
        "formato_encuadernacion",
        "traductor",
        "ilustrador",
        "colección",
        "numero_coleccion",
        "subtitulo",
        "estado",
        "tmr",
        "pvp",
        "tipo_de_articulo",
        "clasificacion",
        "editorial",
        "pvp_sin_iva",
        "num_ilustraciones",
        "peso",
        "ancho",
        "alto",
        "fecha_aparicion",
        "descripcion_externa",
        "palabras_asociadas",
        "ubicacion_alternativa",
        "valor_iva",
        "valoracion",
        "calidad_literaria",
        "precio_referencia",
        "cdu",
        "en_blanco",
        "libre_1",
        "libre_2",
        "premiado",
        "pod",
        "distribuidor_pod",
        "codigo_old",
        "talla",
        "color",
        "idioma_original",
        "titulo_original",
        "pack",
        "importe_canon",
        "unidades_compra",
        "descuento_maximo"
    ];

    const EDITORIAL_KEYS = [
        "type",
        "action",
        "geslib_id",
        "name",
        "name_short",
        "country",
        "url",
        ""
    ];

    const COLECCION_KEYS = [
        "type",
        "action",
        "editorial_geslib_id",
        "geslib_id",
        "name",
    ];

    const CATEGORIA_KEYS = [
        "type",
        "action",
        "geslib_id",
        "name",
        "",
        ""
    ];

    const AUTHOR_KEYS = [
        "type",
        "action",
        "geslib_id",
        "name"
    ];

    const LINE_TYPES = [
        '1L',
        '1A',
        "3",
        "GP4",
        "EB",
        "IEB",
        "5",
        "6",
        "6E",
        "6I",
        "6T",
        "6IT",
        "LA",
        "B",
        "AUT",
    ];

    public static function get(string $type): array
    {
        $types = [
            'product' => self::PRODUCT_KEYS,
            'product_delete' => self::PRODUCT_DELETE_KEYS,
            'author' => self::AUTHOR_KEYS,
            'author_delete' => self::AUTHOR_DELETE_KEYS,
            'editorial' => self::EDITORIAL_KEYS,
            'editorial_delete' => self::EDITORIAL_DELETE_KEYS,
            'categoria' => self::CATEGORIA_KEYS,
            'categoria_delete' => self::CATEGORIA_DELETE_KEYS,
            'coleccion' => self::COLECCION_KEYS,
        ];

        return $types[$type] ?? [];
    }
}