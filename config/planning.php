<?php

return [
    'sag_label_email' => env('PLANNING_SAG_LABEL_EMAIL'),

    // Ventana FIFO: dentro de los N más antiguos, elegimos el menor costo de cambio.
    'fifo_window' => (int) env('PLANNING_FIFO_WINDOW', 10),

    // Por defecto NO dividimos lotes. Si se activa, el motor puede partir un lote en varios tramos.
    'allow_split' => filter_var(env('PLANNING_ALLOW_SPLIT', false), FILTER_VALIDATE_BOOL),

    // Límite de búsqueda adicional cuando ningún lote "cabe" en la ventana FIFO.
    'max_scan' => (int) env('PLANNING_MAX_SCAN', 50),

    // Heurística Greenex: costo de cambio por atributo de SETUP.
    'setup_costs' => [
        'color' => (int) env('PLANNING_COST_COLOR', 1),
        'calibre' => (int) env('PLANNING_COST_CALIBRE', 3),
        'variedad' => (int) env('PLANNING_COST_VARIEDAD', 5),
        'nota_calidad' => (int) env('PLANNING_COST_NOTA_CALIDAD', 8),
    ],

    /**
     * Conversión de unidades a bins en inventario (SQLSRV).
     *
     * Algunas existencias vienen en unidades que NO son bins 1:1.
     * Ej:
     * - "Tote Wenco Genérico": convertir a bins dividiendo por 24 (según regla de operación).
     *
     * Clave = texto a buscar dentro de n_embalaje (case-insensitive)
     * Valor = divisor numérico (> 0)
     */
    'inventory_bin_divisors' => [
        'TOTE WENCO' => 24,
    ],

    /**
     * Matriz de embalajes (carozos) para sugerir c_item según:
     * especie + destino + nota calidad + variedad + color/categoría + calibre.
     *
     * - Se mantiene como CSV (delimitador ;) para que sea fácil de editar/actualizar.
     * - Por defecto usamos el archivo en storage (si existe); si no existe, usamos el de base_path.
     */
    'packaging_matrix' => [
        'carozos' => [
            'storage_path' => storage_path('app/planning/matrices/matriz-carozos-embalajes.csv'),
            'fallback_path' => base_path('matriz-carozos-embalajes.csv'),
            'cache_ttl_minutes' => (int) env('PLANNING_PACKAGING_MATRIX_TTL', 60),
        ],
    ],
];
