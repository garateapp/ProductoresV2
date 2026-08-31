$view = config("services.termo.sqlsrv_view", "V_PKG_Produccion_Salidas_XXX");
try {
    $cols = DB::connection("sqlsrv")->select("SELECT c.name AS column_name FROM sys.columns c JOIN sys.views v ON c.object_id = v.object_id WHERE v.name = ?", [$view]);
    echo "VIEW: " . $view . PHP_EOL;
    foreach ($cols as $c) { echo $c->column_name . PHP_EOL; }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
