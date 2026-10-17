# CEGAL en Red API Spec (V4.00, Sept 2019)

Vendor: CEGAL (Confederacion Espanola de Gremios y Asociaciones de Libreros). All petitions are HTTP GET to `www.cegalenred.com/peticiones/<petition>.xml.php`.

## Common arguments (every petition)

| Arg | Length | Type | Required | Notes |
|-----|--------|------|----------|-------|
| USUARIO | <=20 | A | Yes | Supplied by CEGAL |
| CLAVE | <=20 | A | Yes | Supplied by CEGAL |

ISBN lists (where accepted): <=576 chars, `|` separator, with or without hyphens -> up to 32 ISBNs of 17 chars or ~44 EAN-13s.

## 1. Ficha Basica/Completa — fichalibro.xml.php

**This is the only petition the plugin wires (TIPOFICHA=C).**

Arguments:

| Arg | Required | Value |
|-----|----------|-------|
| USUARIO, CLAVE | Yes | see above |
| TIPOFICHA | Yes | `B` basic \| `C` complete |
| ISBN | Yes | <=576 chars, `|` separator |
| FORMATO | No | `XML` (plain-XML output) |
| VERSION_SINLI | No | `03` \| `05` \| `07`; default `03` |

Two output shapes:

**(a) SINLI format (default, no FORMATO)** — root `FICHALIBROS` -> `LIBRO{ISBN, VERSION_SINLI, DETALLE (1700-char SINLI line, CDATA), optional RESUMEN, INDICE, PORTADA{IMAGEN_PORTADA (base64, no size limit), FORMATO_PORTADA (gif|bmp|jpg|jpeg|tiff...)}, AUTORES{AUTOR{NOMBRE, BIOGRAFIA}}}`. Schema: `www.cegalenred.com/esquemas/fichalibro.xsd`.

**(b) Plain XML (FORMATO=XML)** — richer one-node-per-field set: EAN, TITULO, SUBTITULO, NOMBRE_EDITORIAL, ENCUADERNACION, LENGUA_PUBLICACION (ISO 639-2), FECHA_PUBLICACION (mmaaaa), PRECIO_SIN_IVA, PRECIO_CON_IVA, COLECCION, PORTADA{...}, etc. Schema: `www.cegalenred.com/esquemas/fichalibroxml.xsd`.

TIPOFICHA=B limits fields to the basic set (ISBN, title, author, translator, original language, edition, country, publisher, date, pages, height/width, binding, PVP, VAT, matter/CDU, collection). TIPOFICHA=C = maximum fields, INCLUDING the cover image.

Example (placeholders only):

```
http://www.cegalenred.com/peticiones/fichalibro.xml.php?USUARIO=nombreusuario&CLAVE=claveusuario&TIPOFICHA=C&ISBN=978-84-...
```

## 2. Disponibilidad — disponibilidad.xml.php (not wired)

Args: USUARIO, CLAVE, ISBN (<=576, `|`).

Root `DISPONIBILIDAD`: per-ISBN list of associated bookstores/distributors — ISBN, ID_SINLI_ASOCIADO, NOMBREA_ASOCIADO, TIPO_ASOCIADO (`L` libreria | `D` distribuidora), DIRECCION, CODIGO_POSTAL, LOCALIDAD, PROVINCIA, PERSONA_CONTACTO, TELEFONO_CONTACTO, EMAIL_CONTACTO, WEB, PAIS_ISO, FECHA_COMUNICACION (aaaammdd). Schema: `www.cegalenred.com/esquemas/disponobilidad.xsd` (vendor spelling).

Example (placeholders only):

```
http://www.cegalenred.com/peticiones/disponibilidad.xml.php?USUARIO=nombreusuario&CLAVE=claveusuario&ISBN=978-0-000-00000-0
```

## 3. Ranking de ventas — rankingventas.xml.php (not wired)

Args: USUARIO, CLAVE, TIPO_RANKING (`S` weekly | `M` monthly, required), NUMERO_SEMANA (1-53), NUMERO_MES (1-12), AGNO (>=2007), IDIOMA (`cat|spa|glg|baq`; null = national ranking).

Root `RANKINGVENTAS`: INICIO_RANKING / FIN_RANKING / FECHA_GENERACION (aaaammdd), then `LIBRO{ISBN, TITULO, AUTOR, EDITORIAL, PRECIO (cents, sin IVA x100), PRECIO_CON_IVA (x100), POSICION, NUMERO_LIBRERIAS, NUMERO_EJEMPLARES, FECHA_PRIMERA_APARICION, NUMERO_SEMANAS}`. Always returns the latest stored ranking; book count set by CEGAL. Schema: `www.cegalenred.com/esquemas/rankingventas.xsd`.

Example (placeholders only):

```
http://www.cegalenred.com/peticiones/rankingventas.xml.php?USUARIO=nombreusuario&CLAVE=claveusuario&TIPO_RANKING=S&IDIOMA=glg
```

## 4. Ventas "relacionadas" — ventasrel.xml.php (not wired)

Args: USUARIO, CLAVE, single ISBN (17 chars, with or without hyphens).

Root `VENTASREL`: `RELACION{ISBN, OPERACIONES, TITULO, SUBTITULO, AUTOR, EDITORIAL, COLECCION, PRECIO_SIN_IVA (x100), IBIC (';'-separated)}` — co-purchased books ranked by OPERACIONES. Schema: `www.cegalenred.com/esquemas/ventasrel.xsd`.

Example (placeholders only):

```
http://www.cegalenred.com/peticiones/ventasrel.xml.php?USUARIO=nombreusuario&CLAVE=claveusuario&ISBN=978-84-272-0210-8
```

## 5. Disponibilidad "GLOBAL" — disponibilidad_global.xml.php (not wired)

Args: USUARIO, CLAVE only.

Response: XML compressed as ZIP, utf-8. Root `DISPONIBILIDAD_GLOBAL{DISTRIBUIDORES{DISTRIBUIDOR{CSL, NOMBRE, PAIS, LOCALIDAD}}, EXISTENCIAS{LIBRO{ISBN, DISTRI{CSL...}}}}`. Schema: `www.cegalenred.com/esquemas/disponibilidad_global.xsd`.

Example (placeholders only):

```
http://www.cegalenred.com/peticiones/disponibilidad_global.xml.php?USUARIO=nombreusuario&CLAVE=claveusuario
```

## 6. Estadistica de ventas — estadisticaventas.xml.php (not wired)

Args: USUARIO, CLAVE, ISBN list (<=576, `|`).

Root `VENTAS_CER`: `LIBRO{ISBN, VENTAS_MESES{VENTA_MES{MES (yyyy-mm), VENTAS}}, VENTAS_SEMANA (last 8 weeks; VENTA_SEMANA{D_FECHA (dd/mm/yyyy), H_FECHA, VENTAS})}`. Schema: `www.cegalenred.com/esquemas/estadistica_cer.xsd`.

Example (placeholders only):

```
http://www.cegalenred.com/peticiones/estadisticaventas.xml.php?USUARIO=nombreusuario&CLAVE=claveusuario&ISBN=978-84-261-3924-5|978-84-96208-49-0
```

## Errors (spec section 7) — IN-BAND

Errors arrive inside the response root as `<ERRORES><ERROR>{ISBN?, CODIGO, DESCRIPCION}</ERROR>...</ERRORES>`. HTTP can be 200 with errors inside; ERRORES may coexist with partial LIBRO results (e.g. one bad ISBN among many).

| CODIGO | DESCRIPCION |
|--------|-------------|
| 0 | ERROR INTERNO |
| 1 | ACCESO DENEGADO |
| 2 | USUARIO O CONTRASENA NO VALIDA |
| 3 | CREDITO FINALIZADO |
| 4 | ISBN NO VALIDO |
| 5 | ISBN NO ENCONTRADO |
| 6 | SIN ESPECIFICAR |
| 7 | ARGUMENTO NO VALIDO |
| 8 | NO HAY RESULTADOS PARA EL RANKING SOLICITADO |

## Provenance

Distilled from `ESPECIFICACIONES_PETICIONES_SEPTIEMBRE_2019_V4.00.pdf` (repo root). Never copy real credentials into this file or any derived doc.
