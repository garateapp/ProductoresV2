<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QualityChartsService
{
    public static function getSizeDistributionData(Collection $receptions): array
    {
        $first = $receptions->first();
        if ($first && ($first->n_especie === 'Cherries')) {
            $reception_numbers = $receptions->pluck('numero_g_recepcion')->filter()->unique()->map(fn($n)=>(string)$n)->values()->all();
            if (empty($reception_numbers)) return ['categories'=>[], 'series'=>[], 'countsSeries'=>[]];

            $colors = ['Rojo','Rojo Caoba','Santina','Caoba Oscuro','Black'];
            $grades = ['L','XL','J','2J','3J','4J'];
            $colores = DB::raw("(VALUES ('Rojo'),('Rojo Caoba'),('Santina'),('Caoba Oscuro'),('Black')) AS c(nombre_color)");
            $calibres = DB::raw("(VALUES ('L'),('XL'),('J'),('2J'),('3J'),('4J'),('5J'),('6J'),('7J')) AS f(categoria_calibres)");
            $caseCategoria = "CASE
                    WHEN calibre < 22 THEN 'L'
                    WHEN calibre BETWEEN 22 AND 23.9 THEN 'L'
                    WHEN calibre BETWEEN 24 AND 25.9 THEN 'XL'
                    WHEN calibre BETWEEN 26 AND 27.9 THEN 'J'
                    WHEN calibre BETWEEN 28 AND 29.9 THEN '2J'
                    WHEN calibre BETWEEN 30 AND 31.9 THEN '3J'
                    WHEN calibre BETWEEN 32 AND 33.9 THEN '4J'
                    WHEN calibre BETWEEN 34 AND 35.9 THEN '4J'
                    WHEN calibre BETWEEN 36 AND 37.9 THEN '6J'
                    WHEN calibre > 38  THEN '7J'
                END";
            $datosSub = DB::connection('firmpro')->table('fpdatos as fpd')
                ->selectRaw("fpd.nombre_color, {$caseCategoria} AS categoria_calibres, COUNT(*) AS cantidad")
                ->whereIn('fpd.numero_recepcion', $reception_numbers)
                ->groupBy('fpd.nombre_color', DB::raw($caseCategoria));
            $resultado = DB::connection('firmpro')->query()
                ->from($colores)->crossJoin($calibres)
                ->leftJoinSub($datosSub, 'd', function($join){
                    $join->on('d.nombre_color','=','c.nombre_color')->on('d.categoria_calibres','=','f.categoria_calibres');
                })
                ->selectRaw("c.nombre_color, f.categoria_calibres, COALESCE(d.cantidad,0) AS cantidad")
                ->orderBy('f.categoria_calibres')->orderBy('c.nombre_color')->get();

            $counts = [];$totalsByGrade=[];
            foreach ($resultado as $row){
                $counts[$row->categoria_calibres][$row->nombre_color]=($counts[$row->categoria_calibres][$row->nombre_color]??0)+(int)$row->cantidad;
                $totalsByGrade[$row->categoria_calibres]=($totalsByGrade[$row->categoria_calibres]??0)+(int)$row->cantidad;
            }
            $series=[];$countsSeries=[];
            foreach ($colors as $c){
                $data=[];$countRow=[];
                foreach ($grades as $g){
                    $val=$counts[$g][$c]??0; $total=$totalsByGrade[$g]??0;
                    $data[] = $total>0? round(($val/$total)*100,2):0.0; $countRow[]=$val;
                }
                $series[]=['name'=>$c,'data'=>$data]; $countsSeries[]=['name'=>$c,'data'=>$countRow];
            }
            return ['categories'=>$grades,'series'=>$series,'countsSeries'=>$countsSeries];
        }

        $chartData=[]; $calibreCounts=[];
        foreach ($receptions as $reception){
            if ($reception->calidad){
                foreach ($reception->calidad->detalles as $detail){
                    if ($detail->tipo_item==='DISTRIBUCIÓN DE CALIBRES'){
                        $name=$detail->detalle_item??'N/A';
                        $calibreCounts[$name]=($calibreCounts[$name]??0)+($detail->porcentaje_muestra??0);
                    }
                }
            }
        }
        foreach ($calibreCounts as $calibre=>$count){ $chartData[]=['calibre'=>$calibre,'count'=>$count]; }
        return array_values($chartData);
    }

    public static function getPromedioFirmezasData(Collection $receptions): array
    {
        $categories=['Muy Firme','Firme','Sensible','Blando']; $colors=['Light','Dark','Black'];
        $acc=[]; foreach($categories as $cat){ $acc[$cat]=['Light'=>[],'Dark'=>[],'Black'=>[]]; }
        foreach ($receptions as $reception){
            if ($reception->calidad){
                $details=$reception->calidad->detalles->where('tipo_item','DISTRIBUCIÓN DE FIRMEZA')->values();
                for ($i=0;$i<$details->count();$i++){
                    $categoryIndex=floor($i/3); if ($categoryIndex>=count($categories)) break;
                    $cat=$categories[$categoryIndex]; $detail=$details[$i];
                    $color=ucfirst(strtolower($detail->detalle_item)); $value=$detail->valor_ss??0;
                    if (in_array($color,$colors)) $acc[$cat][$color][]=$value;
                }
            }
        }
        $final=[]; foreach($acc as $cat=>$colorData){ foreach($colorData as $color=>$values){ $final[$cat][$color]=count($values)>0? array_sum($values)/count($values):0; } }
        $series=[['name'=>'Light','data'=>[]],['name'=>'Dark','data'=>[]],['name'=>'Black','data'=>[]]];
        foreach($final as $cat=>$colorCounts){ $series[0]['data'][]=round($colorCounts['Light'],2); $series[1]['data'][]=round($colorCounts['Dark'],2); $series[2]['data'][]=round($colorCounts['Black'],2); }
        return ['categories'=>$categories,'series'=>$series];
    }

    public static function getDistribucionFirmezasData(Collection $receptions): array
    {
        $chartData=[]; $firmness=[];
        foreach($receptions as $reception){ if($reception->calidad){ foreach($reception->calidad->detalles as $detail){ if($detail->tipo_item==='FIRMEZAS'){ $name=$detail->detalle_item??'N/A'; $firmness[$name]=$detail->valor_ss??0; } } } }
        foreach($firmness as $name=>$data){ $chartData[]=['reading_name'=>$name,'avg_firmness'=>$data]; }
        return array_values($chartData);
    }

    public static function getSolidosSolublesData(Collection $receptions): array
    {
        $chartData=[]; $brix=[];
        foreach($receptions as $reception){ if($reception->calidad){ foreach($reception->calidad->detalles as $detail){ if(in_array($detail->detalle_item,["LIGHT","DARK","BLACK"]) && $detail->tipo_item==='SOLIDOS SOLUBLES'){ $size=$detail->detalle_item??'N/A'; $brix[$size]=$detail->valor_ss??0; } } } }
        foreach($brix as $size=>$data){ $chartData[]=['size'=>$size,'avg_brix'=>$data]; }
        return array_values($chartData);
    }

    public static function getColorCubrimientoData(Collection $receptions): array
    {
        $first=$receptions->first();
        if($first && ($first->n_especie==='Cherries')){
            $reception_numbers=$receptions->pluck('numero_g_recepcion')->filter()->unique()->map(fn($n)=>(string)$n)->values()->all();
            if(empty($reception_numbers)) return ['categories'=>[], 'series'=>[], 'countsSeries'=>[]];
            $colors=['Rojo','Rojo Caoba','Santina','Caoba Oscuro','Black']; $grades=['L','XL','J','2J','3J','4J','5J','6J','7J'];
            $colores=DB::raw("(VALUES ('Rojo'),('Rojo Caoba'),('Santina'),('Caoba Oscuro'),('Black')) AS c(nombre_color)");
            $calibres=DB::raw("(VALUES ('L'),('XL'),('J'),('2J'),('3J'),('4J'),('5J'),('6J'),('7J')) AS f(categoria_calibres)");
            $caseCategoria="CASE
                    WHEN calibre < 22 THEN 'L'
                    WHEN calibre BETWEEN 22 AND 23.9 THEN 'L'
                    WHEN calibre BETWEEN 24 AND 25.9 THEN 'XL'
                    WHEN calibre BETWEEN 26 AND 27.9 THEN 'J'
                    WHEN calibre BETWEEN 28 AND 29.9 THEN '2J'
                    WHEN calibre BETWEEN 30 AND 31.9 THEN '3J'
                    WHEN calibre BETWEEN 32 AND 33.9 THEN '4J'
                    WHEN calibre BETWEEN 34 AND 35.9 THEN '4J'
                    WHEN calibre BETWEEN 36 AND 37.9 THEN '6J'
                    WHEN calibre > 38  THEN '7J'
                END";
            $datosSub=DB::connection('firmpro')->table('fpdatos as fpd')
                ->selectRaw("fpd.nombre_color, {$caseCategoria} AS categoria_calibres, COUNT(*) AS cantidad")
                ->whereIn('fpd.numero_recepcion',$reception_numbers)
                ->groupBy('fpd.nombre_color', DB::raw($caseCategoria));
            $resultado=DB::connection('firmpro')->query()->from($colores)->crossJoin($calibres)
                ->leftJoinSub($datosSub,'d',function($join){ $join->on('d.nombre_color','=','c.nombre_color')->on('d.categoria_calibres','=','f.categoria_calibres'); })
                ->selectRaw("c.nombre_color, f.categoria_calibres, COALESCE(d.cantidad,0) AS cantidad")
                ->orderBy('c.nombre_color')->orderBy('f.categoria_calibres')->get();
            $counts=[]; $totalsByColor=[];
            foreach($resultado as $row){ $counts[$row->nombre_color][$row->categoria_calibres]=($counts[$row->nombre_color][$row->categoria_calibres]??0)+(int)$row->cantidad; $totalsByColor[$row->nombre_color]=($totalsByColor[$row->nombre_color]??0)+(int)$row->cantidad; }
            $series=[]; $countsSeries=[];
            foreach($grades as $g){ $data=[]; $countRow=[]; foreach($colors as $c){ $val=$counts[$c][$g]??0; $total=$totalsByColor[$c]??0; $data[]=$total>0? round(($val/$total)*100,2):0.0; $countRow[]=$val; } $series[]=['name'=>$g,'data'=>$data]; $countsSeries[]=['name'=>$g,'data'=>$countRow]; }
            return ['categories'=>$colors,'series'=>$series,'countsSeries'=>$countsSeries];
        }
        $chartData=[]; $coverage=[];
        foreach($receptions as $reception){ if($reception->calidad){ foreach($reception->calidad->detalles as $detail){ if($detail->tipo_item==='COLOR DE CUBRIMIENTO'){ $color=$detail->detalle_item??'N/A'; $pct=$detail->valor_ss??0; $coverage[$color]=($coverage[$color]??0)+$pct; } } } }
        foreach($coverage as $color=>$sum){ $chartData[]=['color'=>$color,'percentage'=>$sum]; }
        return array_values($chartData);
    }
}

