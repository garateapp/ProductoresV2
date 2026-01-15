import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";

export default function ValidacionesMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange transition-colors">
        Validaciones
      </DropdownMenuTrigger>

      <DropdownMenuContent
        align="start"
        className="w-64 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >
        <DropdownMenuItem asChild>
          <Link href={route('validaciones.recepciones-sin-contrato')} className="w-full">
            Datos de Productor
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
