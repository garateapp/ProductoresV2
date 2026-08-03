import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";

export default function SagMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="inline-flex h-9 items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange transition-colors">
       Módulo Sag
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="end"
        className="w-48 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >


      </DropdownMenuContent>
    </DropdownMenu>
  );
}
