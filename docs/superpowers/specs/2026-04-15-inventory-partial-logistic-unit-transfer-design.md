# Traslado Parcial De Unidades Logisticas Sin Crear Nuevo LPN

**Fecha:** 2026-04-15

**Objetivo**

Permitir que un mismo `LPN` pueda mover solo una parte de sus unidades hacia otra ubicacion sin crear un pallet nuevo. El mismo codigo logistico debe poder quedar con saldo simultaneo en origen y destino, manteniendo trazabilidad operativa y stock consistente por ubicacion.

**Alcance**

- Soportar saldos distribuidos por ubicacion para un mismo `inventory_logistic_units`.
- Permitir traslados parciales desde `resources/js/Pages/Inventory/LogisticUnits/Index.jsx`.
- Reutilizar el mismo `LPN` en origen y destino sin duplicar registros de pallet.
- Validar cantidad disponible por ubicacion origen antes de mover.
- Reflejar los saldos por ubicacion en backend y frontend.
- Mantener compatibilidad funcional con traslados completos, rechazos y retornos ya implementados.

**Fuera de alcance**

- Crear nuevos LPN o sub-LPN para representar fracciones.
- Cambiar la semantica de materiales, empaques o unidades de medida.
- Rehacer todo el flujo de escaneo multi-pallet en esta iteracion.
- Resolver consolidacion automatica de saldos entre multiples destinos.
- Replantear auditoria historica mas alla del ledger y movimientos existentes.

## Flujo de usuario

1. El usuario entra a `Inventario > Unidades logisticias`.
2. Selecciona un `LPN` y ve sus saldos actuales por ubicacion.
3. Elige la ubicacion origen desde la cual saldra la cantidad.
4. Ingresa la cantidad a mover.
5. Selecciona la ubicacion destino.
6. El sistema valida que el saldo disponible en origen sea suficiente.
7. Al confirmar, descuenta la cantidad del saldo origen y suma la misma cantidad al saldo destino sin crear otro `LPN`.
8. Si la cantidad movida consume todo el saldo de la ubicacion origen, ese saldo queda en cero y puede ocultarse o limpiarse.

## Arquitectura

El cambio introduce una capa explicita de saldos por ubicacion para desacoplar el `LPN` maestro de una ubicacion unica. El pallet sigue existiendo como entidad logistica unica, pero su disponibilidad deja de depender de `current_location_id` y pasa a depender de una tabla de balances.

Piezas:

- Nueva tabla/modelo para balances de unidad logistica por ubicacion.
- Servicio de unidades logisticas extendido para mover cantidades parciales.
- Ajustes en controlador para exponer saldos por ubicacion y aceptar traslados parciales.
- Pantalla React para visualizar y operar sobre esos saldos.
- Tests de integracion para validar consistencia de stock y trazabilidad.

## Datos y modelo

Se agregara una tabla tipo `inventory_logistic_unit_balances` con al menos:

- `id`
- `logistic_unit_id`
- `location_id`
- `quantity`
- timestamps

Reglas del modelo:

- Un `LPN` puede tener cero o mas balances por ubicacion.
- La suma de balances por ubicacion representa la cantidad total disponible del `LPN`.
- `inventory_logistic_units.available_quantity` puede mantenerse temporalmente como total agregado para compatibilidad, pero no debe ser la fuente de verdad para validar traslados parciales por ubicacion.
- `current_location_id` deja de ser fuente de verdad para movimientos parciales. Puede mantenerse solo como compatibilidad temporal o quedar nulo cuando un `LPN` tenga saldo repartido.

## Reglas de negocio

- Un traslado parcial nunca crea un nuevo `LPN`.
- El usuario siempre debe indicar desde que ubicacion sale la cantidad.
- La cantidad a mover debe ser mayor que cero y menor o igual al saldo disponible en la ubicacion origen.
- Origen y destino no pueden ser la misma ubicacion.
- Si no existe saldo previo en destino para ese `LPN`, se crea el balance destino.
- Si despues del traslado el saldo origen queda en cero, el balance origen puede eliminarse.
- Los traslados completos siguen funcionando como un caso particular de traslado parcial donde la cantidad coincide con todo el saldo del origen.

## Integracion con flujos existentes

El flujo nuevo debe convivir con los traslados ya implementados:

- En traslados por escaneo de pallet completo, el backend debe mover todo el saldo del `LPN` desde la ubicacion origen seleccionada o resuelta.
- Si en el futuro el flujo de escaneo acepta cantidad por `LPN`, debe reutilizar la misma primitiva de traslado parcial.
- En rechazo con retorno pendiente, el retorno debe reponer saldo en la ubicacion origen sobre la tabla de balances, no contra una ubicacion unica del pallet.

## UX y validaciones

La pantalla `resources/js/Pages/Inventory/LogisticUnits/Index.jsx` debe mostrar:

- `LPN`
- Material
- Cantidad total
- Saldos por ubicacion
- Formulario de traslado parcial:
  - ubicacion origen
  - cantidad
  - ubicacion destino
  - accion de confirmar

Validaciones:

- El `LPN` debe existir.
- Debe existir saldo en la ubicacion origen.
- La cantidad debe ser numerica y positiva.
- La cantidad no puede superar el saldo origen.
- La ubicacion destino debe existir.
- Origen y destino deben ser distintos.

Mensajes minimos:

- Exito: `Cantidad trasladada correctamente.`
- Error de saldo: `La ubicacion origen no tiene saldo suficiente para este LPN.`
- Error de validacion: mensaje claro por campo.

## Implementacion esperada

Archivos probables a modificar o crear:

- `database/migrations/*_create_inventory_logistic_unit_balances_table.php`
- `app/Models/InventoryLogisticUnit.php`
- `app/Models/InventoryLocation.php`
- `app/Models/InventoryLogisticUnitBalance.php`
- `app/Services/Inventory/LogisticUnitService.php`
- `app/Http/Controllers/Inventory/LogisticUnitController.php`
- `resources/js/Pages/Inventory/LogisticUnits/Index.jsx`
- `tests/Feature/InventoryLogisticUnitPartialTransferTest.php`

## Riesgos

- Parte del codigo actual asume que un `LPN` tiene una sola ubicacion via `current_location_id`; esos supuestos deben revisarse antes de reutilizar helpers existentes.
- `available_quantity` y los balances por ubicacion pueden desalinearse si no se define una sola fuente de verdad durante la migracion.
- El flujo de traslados ya implementado para `inventory_transfer_units` podria requerir adaptaciones posteriores para soportar rechazos o retornos parciales sobre un mismo `LPN`.

## Criterio de aceptacion

- Un mismo `LPN` puede tener saldo simultaneo en dos ubicaciones.
- Desde `LogisticUnits/Index.jsx` se puede mover una cantidad parcial sin crear otro pallet.
- El backend valida saldo por ubicacion origen.
- El saldo origen disminuye y el saldo destino aumenta por la misma cantidad.
- El `LPN` mantiene su identidad unica durante todo el proceso.
- La funcionalidad queda cubierta por pruebas de integracion.
