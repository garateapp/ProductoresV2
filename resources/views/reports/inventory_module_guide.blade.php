<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Módulo de Inventario · Guía de Operación</title>
<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
        font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
        font-size: 12px;
        color: #1f2937;
        line-height: 1.55;
    }

    /* Cover */
    .cover {
        page-break-after: always;
        height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 60px;
        background: linear-gradient(160deg, #0f5c3b 0%, #0b7a4b 55%, #18a05d 100%);
        color: #ffffff;
    }
    .cover .brand { font-size: 13px; letter-spacing: 3px; text-transform: uppercase; opacity: .85; margin-bottom: 8px; }
    .cover h1 { font-size: 40px; line-height: 1.15; margin: 8px 0 16px; font-weight: 800; }
    .cover .subtitle { font-size: 18px; opacity: .95; max-width: 640px; }
    .cover .meta { margin-top: 40px; font-size: 12px; opacity: .8; }
    .cover .toc { margin-top: 48px; font-size: 13px; }
    .cover .toc div { padding: 3px 0; opacity: .9; }

    /* Content */
    .content { padding: 40px 52px 56px; }

    h2.section {
        font-size: 20px; color: #0b7a4b; font-weight: 800;
        border-bottom: 2px solid #0b7a4b; padding-bottom: 6px;
        margin: 0 0 14px;
        break-after: avoid; page-break-after: avoid;
    }
    h3 { font-size: 15px; color: #0f5c3b; margin: 22px 0 8px; break-after: avoid; page-break-after: avoid; }
    h4 { font-size: 13px; color: #374151; margin: 16px 0 6px; break-after: avoid; page-break-after: avoid; }

    p { margin: 6px 0; }
    ul, ol { margin: 6px 0 12px; padding-left: 22px; }
    li { margin: 4px 0; }

    .lead { font-size: 13.5px; color: #374151; }

    .rule, .note, .warn {
        break-inside: avoid; page-break-inside: avoid;
    }
    .rule {
        background: #f0faf4; border-left: 4px solid #18a05d;
        padding: 10px 14px; border-radius: 6px; margin: 10px 0;
    }
    .rule b { color: #0b7a4b; }
    .note {
        background: #fff7ed; border-left: 4px solid #f59e0b;
        padding: 10px 14px; border-radius: 6px; margin: 10px 0;
    }
    .warn {
        background: #fef2f2; border-left: 4px solid #ef4444;
        padding: 10px 14px; border-radius: 6px; margin: 10px 0;
    }

    .card-grid { display: flex; flex-wrap: wrap; gap: 12px; margin: 12px 0; break-inside: avoid; page-break-inside: avoid; }
    .card {
        flex: 1 1 30%; min-width: 220px;
        border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px;
        background: #fafafa;
        break-inside: avoid; page-break-inside: avoid;
    }
    .card .t { font-weight: 700; color: #0b7a4b; margin-bottom: 4px; font-size: 12.5px; }
    .card .d { font-size: 11.5px; color: #4b5563; }

    table { width: 100%; border-collapse: collapse; margin: 10px 0 14px; font-size: 11px; break-inside: auto; page-break-inside: auto; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; page-break-inside: avoid; }
    th, td { padding: 6px 9px; border: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
    th { background: #ecfdf3; color: #0f5c3b; font-weight: 700; }
    td.num, th.num { text-align: right; }

    code {
        background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px;
        padding: 0 4px; font-size: 11px; color: #b91c1c;
    }

    .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
    .badge.consumo { background: #e0f2fe; color: #0369a1; }
    .badge.semielaborado { background: #dcfce7; color: #15803d; }
    .badge.retornable { background: #fef3c7; color: #b45309; }

    .page-mark {
        break-inside: avoid; page-break-inside: avoid;
        margin-bottom: 18px; border: 1px solid #eef2f7; border-radius: 10px; padding: 14px 16px;
    }

    .page-break { page-break-before: always; }

    /* Footer */
    .footer {
        margin-top: 40px; padding-top: 12px; border-top: 1px solid #e5e7eb;
        color: #9ca3af; font-size: 10px; text-align: center;
        break-inside: avoid; page-break-inside: avoid;
    }
    @page { size: Letter; margin: 0; }
</style>
</head>
<body>

<!-- ============ PORTADA ============ -->
<div class="cover">
    <div class="brand">AppGreenEx · Sistema de Bodega y Producción</div>
    <h1>Módulo de Inventario<br>Guía de Operación</h1>
    <div class="subtitle">Cómo se registra, controla y consulta el movimiento de materiales: entradas, traslados, consumos, mermas y semielaborados.</div>
    <div class="meta">Documento para personal de bodega, producción y control · Generado: {{ $generatedAt }}</div>
    <div class="toc">
        <div>1. El inventario y por qué importa</div>
        <div>2. Conceptos y objetos clave</div>
        <div>3. El flujo del stock, paso a paso</div>
        <div>4. Operaciones del día a día</div>
        <div>5. Consumo automático y consumo manual</div>
        <div>6. Semielaborados</div>
        <div>7. Mermas y desperdicios</div>
        <div>8. Reportes y control</div>
        <div>9. Reglas operativas y buenas prácticas</div>
        <div>10. Preguntas frecuentes</div>
    </div>
</div>

<!-- ============ CONTENIDO ============ -->
<div class="content">

    <!-- 1 -->
    <h2 class="section">1. El inventario y por qué importa</h2>
    <p class="lead">El módulo de Inventario administra los materiales, embalajes, ubicaciones, pallets y el stock que se mueve dentro de la planta. Lleva el control desde que un material llega, pasando por su almacenamiento, traslado y consumo, hasta el manejo de mermas y semielaborados.</p>

    <div class="rule"><b>Regla de oro:</b> todo movimiento que cambia el stock debe dejar evidencia de <b>qué</b> material se movió, <b>cuánto</b>, <b>desde dónde</b>, <b>hacia dónde</b>, bajo <b>qué referencia</b> (solicitud, folio, producción), <b>quién</b> lo hizo y <b>cuándo</b>. Sin evidencia no hay movimiento.</div>

    <p>Esto permite que en cualquier momento se pueda responder: <i>¿cuánto hay de este material?, ¿dónde está?, ¿quién lo consumió?, ¿cuándo?, y ¿de qué pallet/LPN salió?</i></p>

    <p>El módulo integra el stock con SAP (saldos y valores), con la vista de producción (folios de salida) y con los reportes de trazabilidad, de modo que la información de la bodega coincida con la contable y la de producción.</p>

    <!-- 2 -->
    <h2 class="section">2. Conceptos y objetos clave</h2>

    <h3>Objetos principales</h3>
    <table>
        <thead>
            <tr><th>Objeto</th><th>Qué es</th><th>Ejemplo</th></tr>
        </thead>
        <tbody>
            <tr><td><b>Material</b></td><td>El insumo o producto que se controla. Tiene un código único, nombre, unidad y familia.</td><td>Caja 5 KG, Bolsa film, Especia</td></tr>
            <tr><td><b>Embalaje</b></td><td>Formato estándar (caja, saco, pallet) con peso y cantidad de cajas definidos.</td><td>Caja cereza 5 KG</td></tr>
            <tr><td><b>Ubicación</b></td><td>Lugar físico donde se almacena (bodega, cámara, línea de producción).</td><td>BOD-PROD, CAMARA-1</td></tr>
            <tr><td><b>Ficha técnica</b></td><td>Define cuánto de cada material se consume por caja o por pallet.</td><td>Receta de la línea de packing</td></tr>
            <tr><td><b>Semielaborado</b></td><td>Producto intermedio compuesto por varios materiales. Al consumirlo se descuentan sus componentes.</td><td>Pulpa seleccionada</td></tr>
            <tr><td><b>LPN / Pallet</b></td><td>Unidad logística física con código (QR), material, cantidad y ubicación.</td><td>LPN-0041</td></tr>
            <tr><td><b>Posición</b></td><td>Stock preciso ubicado en un LPN, lote y ubicación.</td><td>LPN-0041 · Lote A · BOD-PROD</td></tr>
            <tr><td><b>Movimiento</b></td><td>Registro de cambio de stock (entrada, salida, transferencia).</td><td>Consumo, Merma, Traslado</td></tr>
            <tr><td><b>Folio de salida</b></td><td>Referencia de producción de la vista TERMO que identifica una salida.</td><td>FOLIO-5001</td></tr>
        </tbody>
    </table>

    <h3>Tipos de material</h3>
    <p>Todo material se clasifica según cómo se usa:</p>
    <ul>
        <li><span class="badge consumo">CONSUMO</span> Materiales que se consumen en el proceso (insumos, envases, materias primas).</li>
        <li><span class="badge semielaborado">SEMIELABORADO</span> Productos intermedios que se componen de otros materiales. Al trabajarlos se consumen sus componentes, no el semielaborado en sí.</li>
        <li><span class="badge retornable">RETORNABLE</span> Elementos que se devuelven o reutilizan (pallets, bins).</li>
    </ul>

    <!-- 3 -->
    <h2 class="section">3. El flujo del stock, paso a paso</h2>
    <p>El recorrido típico del material en la planta:</p>
    <ol>
        <li><b>Llegada / recepción:</b> el material ingresa y se registra en una ubicación.</li>
        <li><b>Almacenamiento:</b> el material queda ubicado en una bodega o cámara, dentro de un LPN/pallet con su lote.</li>
        <li><b>Traslado:</b> cuando un pallet o posición se mueve de lugar, se registra el movimiento (por escaneo).</li>
        <li><b>Consumo:</b> la producción retira material desde un LPN específico y se descuenta el stock.</li>
        <li><b>Merma:</b> si hay pérdida o descarte, se registra donde ocurrió y se separa el material (cuarentena / destrucción).</li>
        <li><b>Semielaborado / producción:</b> del consumo de varios materiales se genera un producto nuevo (el semielaborado).</li>
    </ol>

    <div class="note"><b>Importante:</b> cada paso deja un movimiento con su detalle, usuario y hora. El stock "agregado" por ubicación y el stock "por posición/LPN" se actualizan en el mismo proceso, para que siempre coincidan.</div>

    <h3>¿De dónde sale el folio de origen en los consumos?</h3>
    <p>En los consumos vinculados a producción, el <b>folio de origen</b> se selecciona desde la <b>vista de producción (V_PKG_Produccion_Salidas)</b>. El sistema valida que el folio elegido exista en esa vista antes de aplicar el consumo; si no existe, el consumo queda en <b>borrador</b> hasta que el folio esté disponible o se corrija.</p>

    <!-- 4 -->
    <h2 class="section">4. Operaciones del día a día</h2>

    <div class="card-grid">
        <div class="card"><div class="t">Resumen</div><div class="d">Indicadores generales del inventario en una sola pantalla (Datos de inventario/dashboard).</div></div>
        <div class="card"><div class="t">Stock por ubicación</div><div class="d">Consulta el stock por material y ubicación, con filtros y saldos positivos, cero o negativos.</div></div>
        <div class="card"><div class="t">Movimientos</div><div class="d">Registro, aplicación, confirmación y rechazo de todos los movimientos de inventario.</div></div>
    </div>
    <div class="card-grid">
        <div class="card"><div class="t">Escaneo operativo</div><div class="d">Traslados y mermas por escaneo de código. Valida ubicación y pallet antes de confirmar.</div></div>
        <div class="card"><div class="t">Pallets / LPN</div><div class="d">Crear, consultar y transferir pallets; muestra posición espacial (ubicación, columna, fila) bajo el QR.</div></div>
        <div class="card"><div class="t">Mermas</div><div class="d">Registrar pérdidas, enviar a cuarentena, disponer material y generar actas.</div></div>
    </div>
    <div class="card-grid">
        <div class="card"><div class="t">Solicitudes</div><div class="d">Crear, aprobar y rechazar solicitudes de materiales entre áreas; desde la solicitud se genera la transferencia.</div></div>
        <div class="card"><div class="t">Entregas a personas</div><div class="d">Entrega formal de material a una persona, con acta PDF y firma dibujada.</div></div>
        <div class="card"><div class="t">Simulador de planificación</div><div class="d">Estima cajas/pallets y los materiales requeridos según la ficha, sin descontar stock.</div></div>
    </div>

    <h3>Estados de un movimiento o consumo</h3>
    <table>
        <thead><tr><th>Estado</th><th>Significado</th></tr></thead>
        <tbody>
            <tr><td><b>Borrador</b></td><td>Se intentó registrar pero no se pudo aplicar (por ejemplo, falta de stock o folio no disponible). Queda pendiente para revisión o reintento.</td></tr>
            <tr><td><b>Aplicado</b></td><td>El movimiento ya modificó el stock y quedó firme.</td></tr>
            <tr><td><b>Confirmado / Rechazado</b></td><td>Etapa de aprobación del movimiento por un responsable.</td></tr>
        </tbody>
    </table>

    <!-- 5 -->
    <h2 class="section">5. Consumo automático y consumo manual</h2>
    <p>El inventario descuenta material de dos formas: automática y manual.</p>

    <h3>5.1 Consumo automático</h3>
    <ul>
        <li>Toma los folios de producción y, cuando tienen ficha técnica y stock, aplica el consumo automáticamente.</li>
        <li>Si falta ficha o stock, deja la línea en <b>borrador</b> (control) para que se complete y se reintente.</li>
        <li>Permite reprocesar folios sin volver a consumir los que ya se aplicaron.</li>
    </ul>

    <h3>5.2 Consumo manual</h3>
    <p><b>¿Cuándo se usa?</b> Cuando un consumo no proviene del automático: un <b>reembalaje</b>, un <b>reproceso</b> o un <b>completar saldos</b>. Cada consumo manual genera un movimiento tipo <b>CONSUMO</b> separado en el reporte.</p>

    <p>Al registrar un consumo manual se elige:</p>
    <ul>
        <li><b>Tipo de acción:</b> Reembalaje, Reproceso o Completar saldos.</li>
        <li><b>Folio(s) de origen:</b> desde la vista de producción. En reembalaje/reproceso se elige <b>uno</b>; en completar saldos se eligen <b>varios</b> que se consolidan.</li>
        <li><b>Uno o más materiales</b> con su cantidad. El formulario permite <b>agregar o quitar materiales</b> según lo que realmente se consumió.</li>
        <li><b>Folio nuevo</b> (opcional, reembalaje/reproceso): folio de destino del pallet. Es texto libre (no es un LPN del sistema).</li>
    </ul>

    <div class="rule"><b>Varios materiales en un solo consumo:</b> el consumo manual ya no se limita a un material por registro. Puedes declarar 1 o más materiales y cada uno se descuenta del stock en el mismo movimiento.</div>

    <h4>Estados del consumo manual</h4>
    <ul>
        <li><b>Aplicado:</b> se descontó el stock correctamente.</li>
        <li><b>Borrador:</b> no se pudo aplicar. Las causas más comunes son <b>stock insuficiente</b> o que el <b>folio de origen no esté en la vista de producción</b>. El sistema muestra el motivo y permite <b>Reintentar</b> cuando se corrija.</li>
    </ul>

    <!-- 6 -->
    <h2 class="section">6. Semielaborados</h2>
    <p>Un <b>semielaborado</b> es un producto intermedio que <b>se compone de varios materiales</b> (definidos en su ficha técnica). Ejemplos: una pulpa seleccionada, una mezcla o una base preparada.</p>

    <h3>¿Cómo se consume un semielaborado?</h3>
    <ol>
        <li>En el consumo manual se selecciona el <b>semielaborado</b>.</li>
        <li>El sistema <b>precarga automáticamente sus materiales componentes</b> (los que indica la ficha técnica), con sus cantidades.</li>
        <li>Puedes <b>quitar componentes</b> o <b>ajustar cantidades</b> para reflejar lo que realmente se consumió.</li>
    </ol>

    <div class="warn"><b>Regla importante:</b> al consumir un semielaborado <b>solo se descuenta stock de los componentes efectivamente listados</b>. El semielaborado en sí <b>no</b> genera un descuento doble: actúa como referencia para reunir y descontar sus materiales. Si usas <b>parte</b> de sus componentes (y no todos), solo se descuentan los que dejaste.</div>

    <table>
        <thead><tr><th>Pregunta</th><th>Respuesta</th></tr></thead>
        <tbody>
            <tr><td>¿Necesito registrar el semielaborado <b>y</b> sus componentes?</td><td>No. Se registra el semielaborado como referencia y se descuentan solo los componentes listados.</td></tr>
            <tr><td>¿Puedo consumir <b>una parte</b> de los materiales del semielaborado?</td><td>Sí. El formulario precarga todos los componentes, y tú decides cuáles dejas y en qué cantidad.</td></tr>
            <tr><td>¿De dónde sale la lista de componentes?</td><td>De la <b>ficha técnica activa</b> más reciente del semielaborado.</td></tr>
        </tbody>
    </table>

    <!-- 7 -->
    <h2 class="section">7. Mermas y desperdicios</h2>
    <ul>
        <li>La merma se registra <b>donde ocurre</b>, indicando material, LPN y ubicación.</li>
        <li>El material se puede <b>enviar a cuarentena</b> o <b>disponer</b>.</li>
        <li>Cuando corresponde se genera un <b>acta de destrucción</b>.</li>
        <li>La merma queda identificada por <b>tipo</b> y <b>motivo</b>.</li>
    </ul>

    <!-- 8 -->
    <h2 class="section">8. Reportes y control</h2>
    <div class="card-grid">
        <div class="card"><div class="t">Reporte de consumo</div><div class="d">Consumo normal, temporal y por servicio/material, con o sin mermas, por rango de fechas. Exporta a CSV o PDF.</div></div>
        <div class="card"><div class="t">Trazabilidad</div><div class="d">Sigue el flujo desde la solicitud hasta el consumo final, con línea de tiempo, responsable, LPN y ubicación.</div></div>
        <div class="card"><div class="t">Simulador</div><div class="d">Estima necesidades de material para planificar, sin mover stock.</div></div>
    </div>

    <h3>¿Qué aparece en el reporte de consumo?</h3>
    <ul>
        <li>El <b>consumo automático</b> y el <b>consumo manual</b> quedan separados para su control.</li>
        <li>Los consumos manuales se identifican con su tipo de acción (reembalaje, reproceso, completar saldos).</li>
        <li>El reporte filtra por fechas, servicio y material, e incluye un <b>generado el</b> con fecha/hora de emisión.</li>
    </ul>

    <!-- 9 -->
    <h2 class="section">9. Reglas operativas y buenas prácticas</h2>
    <ul>
        <li><b>Todo lo que mueve stock debe registrarse.</b> Nada de descontar "en la práctica" sin dejar el movimiento en el sistema.</li>
        <li>Elige siempre el <b>LPN correcto</b> al consumir; la trazabilidad depende de eso.</li>
        <li>Valida que el <b>folio de origen</b> exista en la vista de producción antes de aplicarlo.</li>
        <li>Si un consumo queda en <b>borrador</b>, lee el <b>motivo</b> que muestra el sistema y corrige (stock o folio) antes de reintentar.</li>
        <li>Para <b>semielaborados</b>, revisa que la lista de componentes coincida con lo que realmente se usó antes de registrar.</li>
        <li>Si una cantidad sale mal ya aplicada, no se edita el registro histórico: se corrige con un <b>ajuste/reverso</b> aprobado.</li>
    </ul>

    <!-- 10 -->
    <h2 class="section">10. Preguntas frecuentes</h2>
    <div class="page-mark">
        <h4>¿Por qué mi consumo quedó en borrador?</h4>
        <p>Por lo general por <b>stock insuficiente</b> o porque el <b>folio de origen no aparece en la vista de producción</b>. El sistema indica el motivo exacto en el registro.</p>
        <h4>¿Qué pasa si no encuentro un folio en el desplegable?</h4>
        <p>El desplegable trae los folios de la vista de producción más reciente. Si no aparece, significa que no está disponible en esa vista; el consumo no podrá aplicarse con ese folio hasta que exista.</p>
        <h4>¿El semielaborado y sus componentes se descuentan dos veces?</h4>
        <p>No. Solo se descuentan los componentes que dejas listados. El semielaborado es una referencia.</p>
        <h4>¿Siempre debo registrar el folio nuevo en reembalaje?</h4>
        <p>No es obligatorio. Es el folio de destino del pallet; si no lo tienes aún, déjalo vacío.</p>
        <h4>¿Puedo registrar un consumo manual con varios materiales?</h4>
        <p>Sí. Usa el botón <b>"+ Agregar material"</b> para añadir todos los que necesites y sus cantidades.</p>
        <h4>¿Dónde veo el historial de consumos manuales?</h4>
        <p>En la pantalla de Consumo Manual, en la tabla <b>Historial de consumos manuales</b>, con su estado y material(es) consumidos.</p>
    </div>

    <div class="footer">
        Módulo de Inventario · Guía de Operación · Generado: {{ $generatedAt }}<br>
        AppGreenEx — documento operativo, no reemplaza los manuales técnicos ni las instrucciones de auditoría.
    </div>
</div>

</body>
</html>
