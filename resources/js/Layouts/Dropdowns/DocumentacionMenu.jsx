// Components/Layout/Dropdowns/DocumentationMenu.jsx
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";

export default function DocumentationMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange transition-colors">
        Certificación
      </DropdownMenuTrigger>

      <DropdownMenuContent
        align="start" // 👈 puedes poner "end" si lo quieres pegado al lado derecho
        className="w-64 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >


        <DropdownMenuItem asChild>
          <Link href={route("authorization-types.index")} className="w-full">
            Tipos de Autorización
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("certifying-houses.index")} className="w-full">
            Casas Certificadoras
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("certificate-types.index")} className="w-full">
            Tipos de Certificado
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("producer-certifications.index")} className="w-full">
            Certificaciones
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("markets.index")} className="w-full">
            Mercados
          </Link>
        </DropdownMenuItem>

      </DropdownMenuContent>
    </DropdownMenu>
  );
}
