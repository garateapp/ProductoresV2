# Traslado Parcial Y Consumo Manual Basados En Posiciones De Stock

**Fecha:** 2026-04-15

**Objetivo**

Rediseñar el manejo de stock del modulo de inventario para que la fuente de verdad sea una capa de posiciones de stock, no el `LPN`. Esto debe permitir traslados parciales, traslados completos de pallet, consumo manual por seleccion de posicion y devoluciones consistentes sin crear nuevos pallets para cada fraccion movida.

**Alcance**

- Introducir una entidad de posiciones de stock como base operativa del inventario.
- Permitir traslados parciales de cantidad entre ubicaciones sin crear un nuevo `LPN`.
- Permitir traslados completos de `LPN` resolviendo todas las posiciones asociadas en la ubicacion origen.
- Preparar el modulo para consumo manual donde el usuario elige explicitamente la posicion a descontar.
- Mantener `LPN` como referencia logistica y de trazabilidad, no como fuente de verdad del saldo.
- Exponer en UI una vista resumen del movimiento del `LPN` con detalle desplegable por posicion.

**Fuera de alcance**

- Reemplazar de una sola vez todo el modulo de inventario existente.
- Automatizar reglas de consumo tipo FIFO o FEFO.
- Crear sub-LPN o nuevos pallets para cada traslado parcial.
- Rehacer todos los reportes o conciliaciones historicas en esta iteracion.
- Eliminar de inmediato tablas agregadas o campos legacy que aun se necesiten para compatibilidad temporal.

## Problema actual

El modelo actual tiende a asociar el stock de un pallet a una sola ubicacion mediante el `LPN` o mediante campos agregados en la unidad logistica. Eso funciona mientras el pallet se mueve completo, pero se vuelve fragil cuando:

- solo se traslada una parte del stock
- distintas ubicaciones consumen el mismo material de forma distinta
- se necesita elegir manualmente desde que saldo exacto consumir
- hay rechazos, retornos o mermas parciales

Si se intenta resolver esto repartiendo saldo sobre el mismo `LPN` por ubicacion, el pallet pasa a existir logicamente en varios lugares a la vez. Eso es posible contablemente, pero no es una base solida para la siguiente etapa de consumo manual.

## Arquitectura

La fuente de verdad del stock debe pasar a ser una tabla de posiciones de inventario. Cada posicion representa una cantidad concreta y homogenea de material en una ubicacion determinada. Opcionalmente, esa posicion puede estar asociada a un `LPN`, pero el stock deja de depender estructuralmente del pallet.

Piezas:

- Nueva tabla/modelo `inventory_stock_positions`.
- Servicios de inventario ajustados para mover, consumir, devolver o mermar posiciones concretas.
- `LPN` mantenido como entidad logistica y de trazabilidad.
- UI de unidades logisticas capaz de mostrar resumen por `LPN` y detalle por posiciones.
- Flujos existentes de traslado, rechazo y retorno adaptados para operar contra posiciones.

## Datos y modelo

Se agregara una tabla tipo `inventory_stock_positions` con campos conceptuales como:

- `id`
- `material_id`
- `location_id`
- `quantity`
- `logistic_unit_id` nullable
- `lot_code` o referencia tecnica nullable
- `status`
- timestamps

Reglas del modelo:

- una posicion representa stock elegible como una sola unidad operativa
- una ubicacion puede tener multiples posiciones del mismo material
- un `LPN` puede referenciar una o varias posiciones
- una posicion puede existir con o sin `LPN`
- el stock disponible para mover o consumir siempre se valida contra una posicion o grupo explicito de posiciones

## Semantica del LPN

`inventory_logistic_units` se mantiene, pero cambia de rol:

- deja de ser la fuente primaria del saldo
- sigue siendo el contenedor operativo que se escanea o identifica en bodega
- sirve para agrupar posiciones relacionadas en un mismo movimiento visible para el usuario
- mantiene trazabilidad, etiquetas y contexto logistico

Esto permite que un usuario siga trabajando con el concepto de pallet, pero el sistema descuenta y mueve stock real desde posiciones concretas.

## Flujo de traslado parcial

1. El usuario entra a `resources/js/Pages/Inventory/LogisticUnits/Index.jsx`.
2. Selecciona un `LPN` o una combinacion de contexto equivalente.
3. La UI muestra las posiciones asociadas a ese `LPN` en la ubicacion elegida.
4. El usuario selecciona la posicion origen o un subconjunto claro de cantidad.
5. Ingresa la cantidad a mover.
6. Selecciona la ubicacion destino.
7. El sistema valida saldo suficiente en la posicion origen.
8. Al confirmar, descuenta la cantidad de la posicion origen y crea o incrementa una posicion destino compatible.
9. No se crea un nuevo `LPN` por este solo hecho.

## Flujo de traslado completo de LPN

Un traslado completo no es un flujo separado. Es un caso particular del mismo motor de posiciones:

- el usuario elige `mover LPN completo`
- el backend resuelve todas las posiciones asociadas a ese `LPN` en la ubicacion origen
- el sistema genera un movimiento resumen visible como una sola operacion logistica
- la UI permite desplegar el detalle para ver cada posicion y cantidad incluidas

Si el mismo `LPN` tiene saldo asociado en otras ubicaciones, ese saldo no se mueve salvo que pertenezca a la ubicacion origen seleccionada.

## Flujo de consumo

El consumo futuro debe operar sobre posiciones seleccionadas por el usuario. No se aplicara una regla global de salida automatica.

Reglas:

- cada ubicacion puede decidir desde que posicion consumir
- el sistema mostrara posiciones elegibles por material y ubicacion
- el usuario selecciona explicitamente la posicion a consumir
- el backend descuenta solo desde esa posicion

Esto evita imponer FIFO o FEFO donde la operacion real no lo usa y deja el modulo alineado con la forma en que cada area maneja su inventario.

## Integracion con flujos existentes

El rediseño debe convivir con lo ya construido:

- el flujo de traslado multi-pallet ya implementado debe poder resolverse contra posiciones origen y destino
- el rechazo con retorno pendiente debe reponer stock sobre la posicion correcta en origen
- la confirmacion de recepcion debe operar sobre posiciones asociadas al movimiento, no solo sobre la entidad del pallet
- el ledger puede seguir existiendo como auditoria de eventos
- tablas o proyecciones agregadas existentes pueden mantenerse temporalmente como lectura o compatibilidad, pero no deben seguir siendo la fuente de verdad

## UX y validaciones

La pantalla `resources/js/Pages/Inventory/LogisticUnits/Index.jsx` debe mostrar:

- resumen por `LPN`
- material y cantidad total
- accion de traslado parcial
- accion de traslado completo del `LPN`
- detalle desplegable con posiciones asociadas:
  - ubicacion
  - cantidad
  - referencia tecnica o lote si aplica

Validaciones minimas:

- la posicion origen debe existir
- la cantidad debe ser numerica y positiva
- la cantidad no puede superar el saldo disponible de la posicion
- origen y destino deben ser diferentes
- en `mover LPN completo`, debe existir al menos una posicion asociada al `LPN` en la ubicacion origen

Mensajes minimos:

- Exito: `Cantidad trasladada correctamente.`
- Exito traslado completo: `Pallet trasladado correctamente.`
- Error de saldo: `La posicion seleccionada no tiene stock suficiente.`
- Error de pallet: `El LPN no tiene stock disponible en la ubicacion origen.`

## Implementacion esperada

Archivos probables a modificar o crear:

- `database/migrations/*_create_inventory_stock_positions_table.php`
- `app/Models/InventoryStockPosition.php`
- `app/Models/InventoryLogisticUnit.php`
- `app/Models/InventoryLocation.php`
- `app/Services/Inventory/LogisticUnitService.php`
- `app/Services/Inventory/InventoryTransactionService.php`
- `app/Http/Controllers/Inventory/LogisticUnitController.php`
- `app/Http/Controllers/Inventory/WorkflowController.php`
- `resources/js/Pages/Inventory/LogisticUnits/Index.jsx`
- `resources/js/Pages/Inventory/Movements/Index.jsx`
- `tests/Feature/InventoryLogisticUnitPartialTransferTest.php`
- `tests/Feature/InventoryConsumptionSelectionTest.php`

## Estrategia de transicion

No conviene reemplazar todo en un solo paso. La transicion debe considerar:

1. crear la tabla de posiciones
2. poblarla a partir del stock actual
3. hacer que los nuevos traslados operen sobre posiciones
4. adaptar rechazo y retorno
5. usar posiciones como base del consumo
6. dejar agregados legacy solo como compatibilidad temporal mientras se migra la lectura

## Riesgos

- parte del modulo actual probablemente asume una ubicacion unica por `LPN`
- si no se define claramente una sola fuente de verdad, se pueden duplicar saldos entre posiciones y agregados legacy
- algunos movimientos existentes podrian requerir metadata adicional para enlazar posiciones origen/destino
- la migracion inicial de datos debe mapear correctamente stock actual a posiciones sin perder trazabilidad

## Criterio de aceptacion

- el stock operable vive en posiciones de inventario
- un traslado parcial mueve cantidad entre posiciones sin crear otro pallet
- un traslado completo de `LPN` mueve todas las posiciones asociadas de la ubicacion origen seleccionada
- la UI muestra movimiento resumen y detalle desplegable por posicion
- el consumo futuro puede descontar desde una posicion elegida por el usuario
- rechazo y retorno pueden reponer posiciones correctas
- la funcionalidad queda cubierta por pruebas de integracion
