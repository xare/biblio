# GeslibPLUS Internet Export Format — Vendor Specification

Transcribed from `Formato exportación GeslibPLUS a Internet.pdf` (vendor document, version 30-05-2016) located in the repository root. The PDF cannot be read by models, so this file is the text source of truth for the format. Page header/footer noise ("Exportación datos Geslib+ a Web. Versión 30-05-2016.", "Página N") has been stripped. Spanish field names, tokens, and table names are preserved verbatim from the vendor document.

## General rules

- Field separator: `|` (pipe, ALT+124).
- Line layout: `<type token>|<operation code>|<fields…>|`. Every field, including the last, is followed by the separator, so valid lines end with a trailing `|`.
- Operation codes: `A` (Añadir), `M` (Modificar), `B` (Borrar). Example of a book modification: `GP4|M|…`.
- The export is **incremental**: each generated file contains every entity modified or created since the previous generation. Geslib+ can generate the file unattended, compress it, and upload it to a parameterized FTP server.
- The file name **must not be used as a sequencer**; process files by date/time (mtime) of the file on the FTP server.
- The initial full load ("Carga Inicial") is flagged by line type `I` on the first line, meaning all data of all entities is being sent.
- The format may gain columns across Geslib+ versions, but the order of existing columns is **never** modified; new columns are always appended at the end of each line type so importers stay compatible with earlier versions.
- Operation code presence: entity types (`CLI`, `AUT`, `1L`, `1A`, `1P`, `1R`, `2`, `3`, `GP4`, `EB`) carry "Tipo movimiento" as the second field. Relation, catalog, and document types (`5`, `BIC`, `LA`, `6` family, `ATRA`, `IEB`, `B`, `B2`, `7`, `8`, `9`, `E`, `PAIS`, `TIPART`, `CLASIF`, `TCL`, `IPC`, `P`, `PROCEN`, `PC`, `VTA`, lotes, discounts, `CDG`, `LDISCEG`, `AUTBIO`, `EMBALA`, `PACK`) start directly with data fields after the token.

## Line type index

Tokens appear exactly as in the vendor summary list (pages 2–3) plus tokens documented only in the detailed sections. Ambiguities inside the PDF are preserved and flagged.

| Token | Meaning | Notes |
|-------|---------|-------|
| `1L` | Editoriales | |
| `1A` | Compañías discográficas | |
| `1P` | Familias de papelería | |
| `1R` | Publicaciones de prensa | |
| `2` | Colecciones editoriales | |
| `3` | Materias | código is `varchar(12)` |
| `GP4` | Artículos (books) | |
| `EB` | eBooks | identical to `GP4` except the token |
| `IEB` | Información propia del eBook | |
| `5` | Materias asociadas a los artículos | |
| `BIC` | Materias IBIC asociadas a los artículos | |
| `6` | Referencias de la librería | |
| `6E` | Referencias del editor | |
| `6I` | Índice del libro | |
| `6T` | Referencias de la librería (traducidas) | |
| `6TE` | Referencias del editor (traducidas) | |
| `6IT` | Índice del libro (traducido) | **Ambiguity:** summary list says `6IT`; the detailed section documents `6TI`. |
| `6TI` | Índice del libro (traducido) | Token used in the detailed section (see `6IT` row). |
| `LA` | Autores normalizados asociados a un artículo | |
| `7` | Formatos de encuadernación | |
| `8` | Idiomas | |
| `9` | Palabras vacías | |
| `B` | Stock | no operation code |
| `B2` | Stock por centros | only generated when Geslib+ is configured to transmit stock per center |
| `E` | Estados de artículos | |
| `CLI` | Clientes | |
| `AUT` | Autores | |
| `I` | Indicador de carga inicial | flag on the first line, not an entity type |
| `IPC` | Incidencias en pedidos de clientes | |
| `P` | Promociones de artículos (globales a todos los centros) | |
| `PROCEN` | Promociones de artículos por centros | |
| `PC` | Pedidos de clientes | |
| `VTA` | Ventas | |
| `PAIS` | Países | |
| `CLOTE` | Lotes de artículos: Cabecera | |
| `LLOTE` | Lotes de artículos: Líneas | |
| `TIPART` | Tipos de artículos | |
| `CLASIF` | Clasificaciones de artículos | |
| `ATRA` | Traducciones asociadas a los artículos | |
| `CLOTCLI` | Lotes de clientes: Cabecera | |
| `CLOTCLI` / `LLOTCLI` | Lotes de clientes: Líneas | **Ambiguity:** the summary list repeats `CLOTCLI` for lines; the detailed section uses `LLOTCLI`. |
| `PROFES` | Profesiones | |
| `PROVIN` | Provincias | |
| `CAGRDTV` | Agrupaciones de descuentos de ventas: Cabecera | |
| `LAGRDTV` | Agrupaciones de descuentos de ventas: Líneas | |
| `CLIDTO` | Descuentos de cliente | |
| `CDG` | Disponibilidad de Cegal: Cabecera | detailed section titled "Disponibilidad de Cegal: Distribuidores" |
| `LDG` / `LDISCEG` | Disponibilidad de Cegal: Líneas | **Ambiguity:** the summary list says `LDG`; the detailed section uses `LDISCEG`. |
| `AUTBIO` | Biografías de autores | |
| `EMBALA` | Embalajes de artículos | |
| `TCL` | Tipos de clientes | documented only in the detailed section; absent from the summary list |
| `PACK` | Packs de artículos | documented only in the detailed section; absent from the summary list |

## Field sequences per type

Wire formats below show every field in column order. `<angle brackets>` hold the value; the trailing `|` after the last field is always present.

### `CLI` — Clientes

- Delete: `CLI|B|<Código de cliente>|`
- Create / modify (24 fields after the token): `CLI|<Tipo movimiento>|<Código de cliente>|<Nombre>|<Localidad>|<Nombre de país>|<Código postal>|<telefono1>|<Fax>|<Cif>|<Email>|<Dirección>|<Código de país>|<Tipo de cliente>|<Idioma>|<Publicidad>|<Agrupación de descuentos>|<Tipo de bonus>|<Profesión>|<Provincia>|<Sexo>|<Fecha de nacimiento>|<Newsletter_tarjeta>|<Autorización_lopd>|`
- Merge: `CLI|F|<Cliente_old>|<Cliente_New>|`

### `AUT` — Autores

- Delete: `AUT|B|<Código de autor>|`
- Create / modify: `AUT|<Tipo movimiento>|<Código de autor>|<Nombre de autor>|`

### `1L` — Editoriales

- Delete: `1L|B|<Código_editorial>|`
- Create / modify: `1L|<Tipo movimiento>|<Código_editorial>|<Nombre>|<nombre_externo>|<País>|`

### `2` — Colecciones editoriales

- Delete: `2|B|<Código_editorial>|<Código_colección>|`
- Create / modify: `2|<Tipo movimiento>|<Código_editorial>|<Código_colección>|<Nombre_coleccion>|`

### `1A` — Discográficas

- Delete: `1A|B|<Código de editorial>|`
- Create / modify: `1A|<Tipo movimiento>|<Código de editorial>|<Nombre>|<Nombre externo>|<País>|`

### `1P` — Familias de papelería

- Delete: `1P|B|<Código de editorial>|` (vendor labels the delete field "Código de editorial" while create/modify says "Código de familia" — see Spec ambiguities)
- Create / modify: `1P|<Tipo movimiento>|<Código de familia>|<Nombre>|<Nombre externo>|<País>|`

### `1R` — Publicaciones de prensa

- Delete: `1R|B|<Código de publicación>|`
- Create / modify: `1R|<Tipo de movimiento>|<Código de publicación>|<Nombre>|<País>|`

### `3` — Materias definidas en la aplicación

- Delete: `3|B|<Código de materia (varchar(12))>|`
- Create / modify: `3|<Tipo movimiento>|<Código de materia (varchar(12))>|<Descripción>|<Descripción_castellano>|<Descripción_ingles>|`

### `GP4` — Libros (artículos)

- Delete: `GP4|B|<Código de articulo>|`
- Create / modify: `GP4|<Tipo de movimiento>|<Código de articulo>|…|<Titulo original>|`

Complete wire column list (0-based wire index; position 0 is the line-type token, positions 1–57 are the 57 documented columns):

| # | Column |
|---|--------|
| 0 | `GP4` (line-type token) |
| 1 | Tipo de movimiento (operation code) |
| 2 | Código de articulo |
| 3 | Descripción |
| 4 | Autor |
| 5 | Pvp_ptas |
| 6 | Isbn |
| 7 | Ean |
| 8 | Num_paginas |
| 9 | Num_edicion |
| 10 | Origen edición |
| 11 | fecha_edicion |
| 12 | fecha_reedicion |
| 13 | año_primera_edicion |
| 14 | año_ultima_edicion |
| 15 | Ubicación |
| 16 | stock |
| 17 | Materia |
| 18 | fecha_alta |
| 19 | fecha_novedad |
| 20 | Idioma |
| 21 | formato_encuadernacion |
| 22 | Traductor |
| 23 | Ilustrador |
| 24 | Colección |
| 25 | numero_coleccion |
| 26 | Subtitulo |
| 27 | Estado |
| 28 | Tmr |
| 29 | Pvp |
| 30 | Tipo de articulo |
| 31 | Clasificación |
| 32 | Editorial |
| 33 | pvp_sin_iva |
| 34 | num_ilustraciones |
| 35 | Peso |
| 36 | Ancho |
| 37 | Alto |
| 38 | Fecha aparición |
| 39 | Descripción externa |
| 40 | Palabras asociadas |
| 41 | Ubicación alternativa |
| 42 | valor_iva |
| 43 | Valoración |
| 44 | Calidad literaria |
| 45 | Precio referencia |
| 46 | CDU |
| 47 | (EN BLANCO - SIN VALOR) — documented blank column, always sent empty |
| 48 | Libre_1 |
| 49 | Libre_2 |
| 50 | Premiado (S/N) |
| 51 | POD (S/N) |
| 52 | Distribuidor POD |
| 53 | CódigoOLD |
| 54 | Talla |
| 55 | Color |
| 56 | Idioma original |
| 57 | Titulo original |

Notes:

- A `GP4` line therefore carries 58 pipe-delimited fields including the token; every field is followed by `|`, so the line ends with a trailing separator.
- For non-book articles the format is the same; columns without meaning are sent blank.
- The stock transmitted in the record is the stock of the center configured as the "centro de referencia" for Internet.

### `EB` — eBooks

Exactly the same as `GP4` (same columns, same operation codes) except the line-type token is `EB`: delete `EB|B|<Código de articulo>|`, create/modify `EB|<Tipo de movimiento>|…|<Titulo original>|`.

### `5` — Materias asociadas a los artículos

`5|<Código de materia (varchar(12))>|<Código de articulo>|` — no operation code. All materias of each article are always sent.

### `BIC` — Materias IBIC asociadas a los artículos

`BIC|<Código de articulo>|<Código IBIC>|<Descripción IBIC>|` — no operation code. All IBIC materias of each article are sent; the vendor notes that each day the materias IBIC of new or modified articles are sent.

### `LA` — Autores normalizados asociados a los "libros"

`LA|<Código de articulo>|<Código de autor>|<Tipo de autor>|<Orden>|` — no operation code. All authors of each book are always sent.

Author-type codes ("tipos de autor"):

| Código | Descripción |
|--------|-------------|
| `A` | Autor |
| `I` | Ilustrador |
| `IC` | Ilustrador contraportada |
| `IP` | Ilustrador Portada |
| `T` | Traductor |

### `6` family — references and index texts

All of these are sent on a single line; carriage returns inside the text are replaced with `<BR>`.

- `6` — Referencias de la librería: `6|<Articulo>|<Contador>|<Texto>|`
- `6T` — Referencias de la librería (traducidas): `6T|<Articulo>|<Idioma>|<Contador>|<Texto>|`
- `6E` — Referencias del editor: `6E|<Articulo>|<Contador>|<Texto>|`
- `6TE` — Referencias del editor (traducidas): `6TE|<Articulo>|<Idioma>|<Contador>|<Texto>|`
- `6I` — Índice: `6I|<Articulo>|<Contador>|<Texto>|`
- `6TI` — Índice (traducido): `6TI|<Articulo>|<Idioma>|<Contador>|<Texto>|` (the summary list calls this token `6IT`)

### `ATRA` — Traducciones asociadas al artículo

`ATRA|<Articulo>|<Idioma>|<Descripción>|<Descripción externa>|`

### `IEB` — Información propia del eBook

`IEB|<Articulo>|<Ref_Proveedor>|<Ref_Trevenque>|<Tamaño>|<Derechos>|<URL>|<Nº descargas>|`

### `B` — Stock

`B|<Artículo>|<Stock>|` — no operation code. All articles with stock are always sent.

### `B2` — Stock por centros

`B2|<Código de Centro>|<Artículo>|<Stock>|` — generated only when Geslib+ is configured to transmit stock broken down per center.

### `7` — Formatos de encuadernación

`7|<Código de formato>|<Descripción>|`

### `8` — Idiomas

`8|<Código idioma>|<Descripción>|`

### `9` — Palabras vacías

`9|<Palabra>|`

### `E` — Estados de los artículos

`E|<Estado>|<Descripción>|`

### `PAIS` — Países

`PAIS|<Código>|<Descripción>|<Codigo_ISO>|`

### `TIPART` — Tipos de artículos

`TIPART|<Código>|<Descripción>|`

### `CLASIF` — Clasificaciones

`CLASIF|<Tipo de articulo>|<Código clasificación>|<Descripción>|`

### `TCL` — Tipos de clientes

`TCL|<Tipo cliente>|<Descripción>|<cod_agrupacion>|<desc_agrupacion>|`

### `IPC` — Incidencias de pedidos de clientes

`IPC|<Cliente>|<Articulo>|<Cantidad>|<Fecha>|<Descripción>|<Observaciones>|`

### `P` — Promociones de artículos (globales a todos los centros)

`P|<Articulo>|<PVP>|<PVP sin IVA>|<Fecha de vencimiento>|` — all promotions active in Geslib+ are always sent.

### `PROCEN` — Promociones de artículos (por centro)

`PROCEN|<Articulo>|<Centro>|<PVP>|<PVP sin IVA>|<Fecha de vencimiento>|` — all promotions active in Geslib+ are always sent.

### `PC` — Pedidos de clientes

`PC|<Cliente>|<Articulo>|<Cantidad>|<Fecha>|<Descripción>|<Cantidad recibida>|<Cantidad retirada>|<Referencia Pedido 1>|<Referencia Pedido 2>|<Observaciones>|<Tipo de Recepción>|`

### `VTA` — Ventas

`VTA|<Cliente>|<Articulo>|<Cantidad>|<Fecha>|<Descripción>|`

### `CLOTE` — Cabecera de lote de artículos

`CLOTE|<Código>|<Nombre>|<Centro>|<Nemotécnico>|<Información_web>|<Nombre Categoría>|<Tipo de lote>|`

### `LLOTE` — Líneas de lote de artículos

`LLOTE|<Código de lote>|<Articulo>|<Referencia gt>|<Cantidad>|`

### `CLOTCLI` — Cabecera de lote de clientes

`CLOTCLI|<Código>|<Nombre>|<Centro>|<Nemotécnico>|<Información_web>|<Nombre Categoría>|`

### `LLOTCLI` — Líneas de lote de clientes

`LLOTCLI|<Código de lote>|<Cliente>|` (the summary list labels this `CLOTCLI`)

### `PROFES` — Profesiones

`PROFES|<Profesión>|<Descripción>|`

### `PROVIN` — Provincias

`PROVIN|<Provincia>|<Descripción>|`

### `CAGRDTV` — Cabecera de agrupaciones de descuentos en ventas

`CAGRDTV|<Agrupación>|<Descripción>|`

### `LAGRDTV` — Líneas de agrupaciones de descuentos en ventas

`LAGRDTV|<Agrupación>|<Tipo de artículo>|<Familia>|<Descuento>|<Dto. Libros de texto>|<Dto. txt Enseñanza oblig.>|<Dto. Txt precio libre>|`

### `CLIDTO` — Descuentos de clientes

`CLIDTO|<Cliente>|<Tipo de artículo>|<Familia>|<Descuento>|<Dto. Libros de texto>|<Dto. txt Enseñanza oblig.>|<Dto. Txt precio libre>|`

### `CDG` — Disponibilidad de Cegal: Distribuidores / Cabecera

`CDG|<Codigo_CSL>|<Nombre>|<Prioridad>|<Código_distri>|`

### `LDISCEG` — Disponibilidad de Cegal: Artículos / Líneas

`LDISCEG|<Código_distri>|<Isbn>|` (the summary list labels this `LDG`)

### `AUTBIO` — Biografías de autores

`AUTBIO|<Autor>|<Biografía>|`

### `EMBALA` — Embalajes de artículos

`EMBALA|<Artículo>|<Referencia>|<Descripción>|<Cantidad>|<PVP unitario>|`

### `PACK` — Packs de artículos

`PACK|<Artículo>|<Pack_Articulo>|<Pack_Cantidad>|`

## Snapshot / replace semantics

The vendor distinguishes types whose content must be recreated from scratch on the web from types that arrive incrementally per entity.

### Snapshot types (delete all existing records, recreate from the file)

For these the vendor explicitly says all records must be deleted on the web and recreated according to what the file says ("Se pasan todas… eliminar … en la web y volver a crearlos en base a lo que el fichero nos diga"):

| Type | Domain |
|------|--------|
| `7` | Formatos de encuadernación |
| `8` | Idiomas |
| `9` | Palabras vacías |
| `E` | Estados de artículos |
| `PAIS` | Países |
| `TIPART` | Tipos de artículos |
| `CLASIF` | Clasificaciones de artículos |
| `TCL` | Tipos de clientes |
| `PROFES` | Profesiones |
| `PROVIN` | Provincias |
| `CAGRDTV` | Agrupaciones de descuentos de ventas: Cabecera |
| `LAGRDTV` | Agrupaciones de descuentos de ventas: Líneas |
| `CLIDTO` | Descuentos de cliente |
| `CDG` | Disponibilidad de Cegal: Cabecera |
| `LDISCEG` | Disponibilidad de Cegal: Líneas |
| `5` | Materias asociadas — for each article, delete that article's materias on the web and recreate them from the file |
| `BIC` | Materias IBIC — same per-article replace rule |

Reference/text sets passed whole (the web must treat them as the authoritative full set for the referenced entity):

| Type | Domain |
|------|--------|
| `6`, `6T`, `6E`, `6TE`, `6I`, `6TI` | references and index, sent on a single line per article/counter |
| `LA` | all normalized authors of each book |
| `B` | all articles with stock |
| `P`, `PROCEN` | all promotions active in Geslib+ |

### Incremental per-entity types (A / M / B per record)

`CLI`, `AUT`, `1L`, `1A`, `1P`, `1R`, `2`, `3`, `GP4`, `EB` — each line adds, modifies, or deletes a single entity. Deletes are explicit `B` lines carrying only the key(s).

Additional semantics:

- Stock in a `GP4` record is the stock of the center configured as the "centro de referencia" for Internet.
- `B` (stock) and `B2` (stock per center) always carry the complete set of applicable articles, not deltas.
- Initial full load: line type `I` on the first line announces that every entity of every type follows.

## Spec ambiguities (preserved from the vendor PDF)

1. **`CLOTCLI` listed twice** — the summary type list uses `CLOTCLI` both for "Lotes de clientes: Cabecera" and "Lotes de clientes: Líneas"; the detailed section uses `LLOTCLI` for lines.
2. **`LDG` vs `LDISCEG`** — the summary list says `LDG` (Disponibilidad de Cegal: Líneas); the detailed section documents `LDISCEG`.
3. **`6IT` vs `6TI`** — the summary list says `6IT` (Índice del libro traducido); the detailed section documents `6TI`.
4. **`TCL` and `PACK` missing from the summary list** — both are documented only in the detailed sections.
5. **`1P` delete label** — the delete form labels the key "Código de editorial" while create/modify says "Código de familia"; the create/modify label is the coherent one.
6. **`TCL` section wording** — the vendor text says "eliminar los formatos", a copy-paste from the `7` (encuadernaciones) section; the intent is to replace the client types.
