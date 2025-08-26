// Components/Layout/Dropdowns/DocumentationMenu.jsx
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";

export default function ControlCalidadMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange transition-colors">
        Control de Calidad
      </DropdownMenuTrigger>

      <DropdownMenuContent
        align="start" // 👈 puedes poner "end" si lo quieres pegado al lado derecho
        className="w-64 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >


        <DropdownMenuItem asChild>

           <Link href={route('control-calidad.index')} className="w-full">Recepción</Link>

        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route('processed-fruit-quality.index')} className="w-full">Producto Terminado</Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route('mrl-samples.index')} className="w-full">MRL - Gestión de Muestras</Link>
        </DropdownMenuItem>



      </DropdownMenuContent>
    </DropdownMenu>
  );
}
