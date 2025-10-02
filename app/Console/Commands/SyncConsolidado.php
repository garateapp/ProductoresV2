<?php

namespace App\Console\Commands;

use App\Models\Proceso;
use App\Models\User;
use Illuminate\Console\Command;

class SyncConsolidado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:consolidado';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolidado Sincronizados Con Éxito';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {   $users=User::all();
        foreach ($users as $user){

            $procesos=Proceso::where('agricola',$user->name)->latest('n_proceso')->get();
            
            $kilos_netos=0;
            $exportacion=0;
            $comercial=0;
            $desecho=0;
            $merma=0;

            foreach ($procesos as $proceso){
            
                $kilos_netos+=$proceso->kilos_netos;
                $exportacion+=$proceso->exp;
                $comercial+=$proceso->comercial;
                $desecho=+$proceso->desecho;
                $merma+=($proceso->kilos_netos-$proceso->exp-$proceso->comercial-$proceso->desecho);
            
            }

            $user->update(['kilos_netos'=>$kilos_netos,
                            'comercial'=>$comercial,
                            'desecho'=>$desecho,
                            'merma'=>$merma,
                            'exp'=>$exportacion]);
        }
      
        return Command::SUCCESS;
    }
}
