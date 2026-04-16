# Inventory Stock Positions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introducir posiciones de stock como fuente de verdad del inventario para soportar traslados parciales, traslado completo de LPN y preparar consumo manual por posicion.

**Architecture:** La implementacion se divide en cinco capas: esquema y migracion de datos, modelo y servicios de posiciones, integracion con traslados existentes, UI de unidades logisticas y proyeccion para consumo manual. La transicion mantiene tablas y flujos actuales como compatibilidad temporal, pero mueve la logica nueva a `inventory_stock_positions`.

**Tech Stack:** Laravel, Eloquent, Inertia React, PHPUnit, Vite.

---

### Task 1: Crear la capa de posiciones de stock

**Files:**
- Create: `database/migrations/2026_04_16_000000_create_inventory_stock_positions_table.php`
- Create: `app/Models/InventoryStockPosition.php`
- Modify: `app/Models/InventoryLogisticUnit.php`
- Modify: `app/Models/InventoryLocation.php`
- Modify: `app/Models/InventoryMaterial.php`
- Test: `tests/Feature/InventoryStockPositionModelTest.php`

- [ ] **Step 1: Write the failing test for stock position persistence and relationships**
- [ ] **Step 2: Run the targeted test to verify it fails because the table/model do not exist**
- [ ] **Step 3: Add the migration for `inventory_stock_positions` with unique constraints and indexes for material, location and optional `logistic_unit_id`**
- [ ] **Step 4: Add the `InventoryStockPosition` model with casts, fillable fields and relations to material, location and logistic unit**
- [ ] **Step 5: Add `stockPositions()` relations on logistic units, locations and materials**
- [ ] **Step 6: Run the targeted test to verify the model and relationships pass**
- [ ] **Step 7: Commit**

### Task 2: Poblar posiciones desde el stock actual

**Files:**
- Create: `app/Console/Commands/BootstrapInventoryStockPositionsCommand.php`
- Modify: `app/Models/InventoryStockLocation.php`
- Modify: `app/Models/InventoryLogisticUnit.php`
- Test: `tests/Feature/BootstrapInventoryStockPositionsCommandTest.php`

- [ ] **Step 1: Write the failing test for bootstrapping positions from current stock and LPN data**
- [ ] **Step 2: Run the targeted test to verify it fails because the command does not exist**
- [ ] **Step 3: Implement a bootstrap command that creates positions from current aggregated stock without duplicating existing rows**
- [ ] **Step 4: Reconcile optional `logistic_unit_id` when the current stock row or LPN data can resolve a pallet reference**
- [ ] **Step 5: Add safety checks so the command is idempotent and logs unresolved rows**
- [ ] **Step 6: Run the targeted test to verify bootstrap behavior passes**
- [ ] **Step 7: Commit**

### Task 3: Mover stock parcial entre posiciones

**Files:**
- Modify: `app/Services/Inventory/LogisticUnitService.php`
- Modify: `app/Services/Inventory/InventoryTransactionService.php`
- Create: `tests/Feature/InventoryStockPositionTransferTest.php`

- [ ] **Step 1: Write the failing test for partial transfer between two locations using a single stock position**
- [ ] **Step 2: Run the targeted test to verify it fails because transfers still depend on LPN-level quantities**
- [ ] **Step 3: Add a service method that validates source position, quantity and destination, then decrements source and increments or creates destination in one transaction**
- [ ] **Step 4: Ensure source positions that reach zero are deleted or marked closed consistently**
- [ ] **Step 5: Emit or persist the movement/ledger metadata needed to trace source and destination positions**
- [ ] **Step 6: Run the targeted test to verify the partial transfer passes**
- [ ] **Step 7: Commit**

### Task 4: Resolver traslado completo de LPN sobre posiciones

**Files:**
- Modify: `app/Services/Inventory/LogisticUnitService.php`
- Modify: `app/Http/Controllers/Inventory/WorkflowController.php`
- Modify: `app/Http/Requests/Inventory/StoreTransferScanRequest.php`
- Test: `tests/Feature/InventoryTransferWorkflowTest.php`

- [ ] **Step 1: Write the failing test for moving a complete LPN that owns multiple positions in the origin location**
- [ ] **Step 2: Run the targeted test to verify it fails because full transfer still uses a single pallet quantity assumption**
- [ ] **Step 3: Update the transfer workflow to resolve all stock positions linked to the selected LPN in the origin location**
- [ ] **Step 4: Reuse the position transfer primitive to move every resolved position inside one grouped movement**
- [ ] **Step 5: Preserve the grouped movement summary while storing the detail needed to expand positions later**
- [ ] **Step 6: Run the targeted test to verify grouped full-LPN transfer passes**
- [ ] **Step 7: Commit**

### Task 5: Adaptar rechazo, recepcion y retorno a posiciones

**Files:**
- Modify: `app/Services/Inventory/InventoryTransactionService.php`
- Modify: `app/Models/InventoryTransferUnit.php`
- Modify: `tests/Feature/InventoryTransferWorkflowTest.php`

- [ ] **Step 1: Write the failing test for reject and return flows restoring the correct source stock position**
- [ ] **Step 2: Run the targeted test to verify it fails because reject/return still restore stock at pallet-level assumptions**
- [ ] **Step 3: Extend transfer unit metadata so each dispatched unit knows the source and destination stock positions involved**
- [ ] **Step 4: Update destination receipt, rejection and origin return flows to mutate positions instead of only relocating pallets**
- [ ] **Step 5: Keep parent movement completion rules intact when all transfer units end in `received` or `returned`**
- [ ] **Step 6: Run the targeted transfer workflow test to verify reject/return now restore the right positions**
- [ ] **Step 7: Commit**

### Task 6: Exponer posiciones y resumen de LPN en la UI

**Files:**
- Modify: `app/Http/Controllers/Inventory/LogisticUnitController.php`
- Modify: `resources/js/Pages/Inventory/LogisticUnits/Index.jsx`
- Test: `tests/Feature/InventoryLogisticUnitIndexTest.php`

- [ ] **Step 1: Write the failing test for logistic unit index payload including grouped position detail**
- [ ] **Step 2: Run the targeted test to verify it fails because the controller does not include stock positions**
- [ ] **Step 3: Extend the controller payload to include total quantity, grouped per-LPN summary and expandable positions**
- [ ] **Step 4: Update `LogisticUnits/Index.jsx` to render the summary row, expansion state and per-position detail**
- [ ] **Step 5: Add the partial transfer form to choose source position, quantity and destination**
- [ ] **Step 6: Add the full-LPN transfer action that shows the grouped move and expandable detail before submit**
- [ ] **Step 7: Run the targeted backend test and `npm run build` to verify the UI compiles against the new payload**
- [ ] **Step 8: Commit**

### Task 7: Exponer referencia de posiciones para consumo manual futuro

**Files:**
- Modify: `app/Http/Controllers/Inventory/MovementController.php`
- Modify: `resources/js/Pages/Inventory/Movements/Index.jsx`
- Create: `tests/Feature/InventoryConsumptionSelectionTest.php`

- [ ] **Step 1: Write the failing test for a positions endpoint or payload that lists selectable stock positions by material and location**
- [ ] **Step 2: Run the targeted test to verify it fails because movement screens only expose aggregate stock references**
- [ ] **Step 3: Add the controller contract to list eligible positions for a selected material and origin location**
- [ ] **Step 4: Update `Movements/Index.jsx` to display selectable positions as a preparation layer for manual consumption**
- [ ] **Step 5: Keep the current aggregate stock reference visible until the consumption flow switches fully to positions**
- [ ] **Step 6: Run the targeted test and `npm run build` to verify the contract is ready for the next feature**
- [ ] **Step 7: Commit**

### Task 8: Proteger compatibilidad temporal y limpieza de proyecciones

**Files:**
- Modify: `app/Services/Inventory/InventoryTransactionService.php`
- Modify: `app/Models/InventoryStockLocation.php`
- Modify: `tests/Feature/InventoryMovementServiceTest.php`
- Modify: `tests/Feature/InventoryTheoreticalConsumptionServiceTest.php`

- [ ] **Step 1: Write the failing regression test for aggregate stock projections staying consistent after position-based moves**
- [ ] **Step 2: Run the targeted regression test to verify it fails because aggregates are not updated from position mutations**
- [ ] **Step 3: Add a projection sync path so legacy aggregate tables remain readable while positions become the source of truth**
- [ ] **Step 4: Update existing movement and theoretical consumption tests where assumptions still point at pallet-level stock**
- [ ] **Step 5: Run the targeted regression tests to verify no duplicate stock appears in legacy reads**
- [ ] **Step 6: Commit**

### Task 9: Verificacion final del rediseño

**Files:**
- Modify: `docs/superpowers/specs/2026-04-15-inventory-partial-logistic-unit-transfer-design.md` only if implementation drift requires a spec correction
- Modify: `docs/superpowers/plans/2026-04-16-inventory-stock-positions.md` only if execution changes the task order materially

- [ ] **Step 1: Run `C:\Users\Lenovo\.config\herd\bin\php83\php.exe artisan test tests/Feature/InventoryStockPositionModelTest.php tests/Feature/BootstrapInventoryStockPositionsCommandTest.php tests/Feature/InventoryStockPositionTransferTest.php tests/Feature/InventoryTransferWorkflowTest.php tests/Feature/InventoryConsumptionSelectionTest.php tests/Feature/InventoryMovementServiceTest.php tests/Feature/InventoryTheoreticalConsumptionServiceTest.php`**
- [ ] **Step 2: Run `npm run build`**
- [ ] **Step 3: Review the resulting movement payloads and stock summaries manually for one partial move and one full-LPN move**
- [ ] **Step 4: Update docs only if the implemented contracts differ from the approved spec**
- [ ] **Step 5: Commit**
