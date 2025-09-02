import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
// Select components are available for future use
// import {
//   Select,
//   SelectContent,
//   SelectItem,
//   SelectTrigger,
//   SelectValue,
// } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Link } from "@inertiajs/react";
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Search,
} from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";

export interface Column<T> {
  key: string;
  label: string;
  render?: (value: unknown, row: T) => React.ReactNode;
  sortable?: boolean;
  className?: string;
}

interface DataTableProps<T> {
  data: T[];
  columns: Column<T>[];
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  searchable?: boolean;
  searchPlaceholder?: string;
  onSearch?: (query: string) => void;
  filters?: React.ReactNode;
  actions?: React.ReactNode;
  emptyMessage?: string;
  className?: string;
}

export function DataTable<T extends Record<string, unknown>>({
  data,
  columns,
  pagination,
  searchable = false,
  searchPlaceholder = "Search...",
  onSearch,
  filters,
  actions,
  emptyMessage = "No data available",
  className,
}: DataTableProps<T>) {
  const [searchQuery, setSearchQuery] = useState("");

  const handleSearch = (query: string) => {
    setSearchQuery(query);
    onSearch?.(query);
  };

  return (
    <div className={cn("space-y-4", className)}>
      {/* Header with search and actions */}
      {(searchable || filters || actions) && (
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-4 flex-1">
            {searchable && (
              <div className="relative max-w-sm">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder={searchPlaceholder}
                  value={searchQuery}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="pl-9"
                />
              </div>
            )}
            {filters}
          </div>
          {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
      )}

      {/* Table */}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((column) => (
                <TableHead
                  key={column.key}
                  className={cn(column.className, column.sortable && "cursor-pointer")}
                >
                  {column.label}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={columns.length}
                  className="h-24 text-center text-muted-foreground"
                >
                  {emptyMessage}
                </TableCell>
              </TableRow>
            ) : (
              data.map((row, index) => (
                <TableRow key={row.id || index}>
                  {columns.map((column) => (
                    <TableCell key={`${row.id || index}-${column.key}`} className={column.className}>
                      {column.render
                        ? column.render(row[column.key], row)
                        : row[column.key]}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {pagination && pagination.last_page > 1 && (
        <div className="flex items-center justify-between">
          <div className="text-sm text-muted-foreground">
            Showing {pagination.from} to {pagination.to} of {pagination.total} results
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              asChild={!!pagination.links[0].url}
              disabled={!pagination.links[0].url}
            >
              {pagination.links[0].url ? (
                <Link href={pagination.links[0].url}>
                  <ChevronsLeft className="h-4 w-4" />
                </Link>
              ) : (
                <>
                  <ChevronsLeft className="h-4 w-4" />
                </>
              )}
            </Button>
            <Button
              variant="outline"
              size="sm"
              asChild={!!pagination.links[1]?.url}
              disabled={!pagination.links[1]?.url}
            >
              {pagination.links[1]?.url ? (
                <Link href={pagination.links[1].url}>
                  <ChevronLeft className="h-4 w-4" />
                </Link>
              ) : (
                <>
                  <ChevronLeft className="h-4 w-4" />
                </>
              )}
            </Button>
            
            <span className="text-sm">
              Page {pagination.current_page} of {pagination.last_page}
            </span>
            
            <Button
              variant="outline"
              size="sm"
              asChild={!!pagination.links[pagination.links.length - 2]?.url}
              disabled={!pagination.links[pagination.links.length - 2]?.url}
            >
              {pagination.links[pagination.links.length - 2]?.url ? (
                <Link href={pagination.links[pagination.links.length - 2].url!}>
                  <ChevronRight className="h-4 w-4" />
                </Link>
              ) : (
                <>
                  <ChevronRight className="h-4 w-4" />
                </>
              )}
            </Button>
            <Button
              variant="outline"
              size="sm"
              asChild={!!pagination.links[pagination.links.length - 1]?.url}
              disabled={!pagination.links[pagination.links.length - 1]?.url}
            >
              {pagination.links[pagination.links.length - 1]?.url ? (
                <Link href={pagination.links[pagination.links.length - 1].url!}>
                  <ChevronsRight className="h-4 w-4" />
                </Link>
              ) : (
                <>
                  <ChevronsRight className="h-4 w-4" />
                </>
              )}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

// Helper components for common table cells
export const StatusBadge = ({ 
  status, 
  variant = "secondary" 
}: { 
  status: string; 
  variant?: "default" | "secondary" | "destructive" | "outline" 
}) => (
  <Badge variant={variant}>{status}</Badge>
);

export const ActionButton = ({ 
  href, 
  children, 
  variant = "ghost",
  size = "sm"
}: { 
  href: string; 
  children: React.ReactNode;
  variant?: "ghost" | "outline" | "secondary";
  size?: "sm" | "default";
}) => (
  <Button variant={variant} size={size} asChild>
    <Link href={href}>{children}</Link>
  </Button>
);