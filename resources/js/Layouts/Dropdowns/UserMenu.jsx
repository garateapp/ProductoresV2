import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu";
import { Link, usePage } from "@inertiajs/react";
import { User } from "lucide-react";

export default function UserMenu({ user }) {
  const { unread_notifications_count: unreadCount = 0 } = usePage().props;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="inline-flex h-9 items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange transition-colors">
        <span className="inline-flex items-center gap-2">
          <User className="h-4 w-4" />
          {user.name}
        </span>
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="end"
        className="w-48 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >
        <DropdownMenuItem asChild>
          <Link href={route("profile.edit")} className="w-full">
            Perfil
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("notifications.index")} className="w-full flex items-center justify-between gap-2">
            Notificaciones
            {unreadCount > 0 && (
              <span className="inline-flex items-center justify-center rounded-full bg-greenex-orange px-2 text-xs font-semibold text-greenex-dark-green">
                {unreadCount}
              </span>
            )}
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("logout")} method="post" as="button" className="w-full text-left">
            Cerrar Sesión
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
