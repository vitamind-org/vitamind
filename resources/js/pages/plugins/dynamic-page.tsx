import SectionLayout from '@/layouts/section/layout';
import { Head, router, useForm } from '@inertiajs/react';
import Container from '@/components/container';
import Heading from '@/components/heading';
import { Button } from '@vitamind/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@vitamind/ui/tabs';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/data-table';
import { Badge } from '@vitamind/ui/badge';
import { Edit2Icon, Trash2Icon } from 'lucide-react';
import { FormEventHandler, ReactNode, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@vitamind/ui/dialog';
import { Form, FormFields } from '@vitamind/ui/form';
import DynamicField from '@vitamind/ui/dynamic-field';
import { DynamicFieldConfig } from '@/types/dynamic-field-config';
import Layout from '@/layouts/app/layout';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@vitamind/ui/select';

type PageConfig = {
  key: string;
  title: string;
  icon: string;
  admin_only: boolean;
  description?: string | null;
  tabs: Record<string, {
    key: string;
    model: string;
    columns: any[];
    form: DynamicFieldConfig[];
    filters?: Array<{
      key: string;
      label: string;
      type: 'select' | 'text' | 'boolean';
      options: Record<string, string>;
    }>;
  }>;
};

type Props = {
  page: PageConfig;
  activeTab: string;
  tableData: any;
  options?: Record<string, any>;
};

interface FormDialogProps {
  fields: DynamicFieldConfig[];
  item?: any;
  options?: Record<string, any>;
  pageKey: string;
  tabKey: string;
  children: ReactNode;
}

function FormDialog({ fields, item, options, pageKey, tabKey, children }: FormDialogProps) {
  const [open, setOpen] = useState(false);

  // Pre-fill initial form data based on field configs and current item values
  const initialData: Record<string, any> = {};
  fields.forEach((f) => {
    if (f.type === 'alert') return;
    initialData[f.name] = item ? item[f.name] : (f.default !== undefined ? f.default : '');
    if (item && f.type === 'select') {
      initialData[f.name] = String(item[f.name]);
    }
    if (f.type === 'boolean' || f.type === 'checkbox') {
      initialData[f.name] = item ? Boolean(item[f.name]) : Boolean(f.default);
    }
  });

  const form = useForm(initialData);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    // @ts-ignore
    const targetRouteStore = route('plugins.page.store', [pageKey, tabKey]);
    // @ts-ignore
    const targetRouteUpdate = item ? route('plugins.page.update', [pageKey, tabKey, item.id]) : '';

    if (item) {
      form.patch(targetRouteUpdate, {
        onSuccess() {
          setOpen(false);
        },
      });
      return;
    }

    form.post(targetRouteStore, {
      onSuccess() {
        form.reset();
        setOpen(false);
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>{item ? `Edit ${item.name || 'Record'}` : `Create New`}</DialogTitle>
        </DialogHeader>
        <Form onSubmit={submit} className="p-4">
          <FormFields>
            {fields.map((f) => {
              if (f.type === 'alert') {
                return <DynamicField key={`f-${f.name}`} value={undefined} onChange={() => {}} config={f} />;
              }

              const fieldConfig = { ...f };
              if (options && options[f.name]) {
                fieldConfig.options = options[f.name];
              }

              return (
                <DynamicField
                  key={`f-${f.name}`}
                  config={fieldConfig}
                  value={form.data[f.name]}
                  onChange={(val) => form.setData(f.name, val)}
                  error={form.errors[f.name]}
                />
              );
            })}
          </FormFields>
          <div className="mt-6 flex justify-end gap-3">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={form.processing}>
              {item ? 'Save Changes' : 'Create'}
            </Button>
          </div>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export default function DynamicPluginPage({ page, activeTab, tableData, options }: Props) {
  const activeTable = page.tabs[activeTab];

  const getNestedValue = (obj: any, path: string) => {
    return path.split('.').reduce((acc, part) => acc && acc[part], obj);
  };

  const columns: ColumnDef<any>[] = activeTable.columns.map((col: any) => {
    return {
      id: col.key,
      accessorKey: col.key,
      header: col.label,
      enableSorting: col.sortable,
      cell: ({ row }) => {
        const val = row.original[col.key];
        const displayVal = col.key.includes('.') ? getNestedValue(row.original, col.key) : val;

        if (col.type === 'badge') {
          const label = col.options[displayVal] || (displayVal ? 'Active' : 'Inactive');
          const isDangerOrInactive = displayVal === false || displayVal === 0 || String(displayVal).toLowerCase() === 'inactive';
          return (
            <Badge variant={isDangerOrInactive ? 'gray' : 'default'}>
              {label}
            </Badge>
          );
        }

        if (col.key === 'price') {
          return <span>${Number(displayVal).toFixed(2)}</span>;
        }

        return <span>{displayVal !== null && displayVal !== undefined ? String(displayVal) : '-'}</span>;
      },
    };
  });

  columns.push({
    id: 'actions',
    header: () => <div className="text-right">Actions</div>,
    cell: ({ row }) => (
      <div className="flex items-center justify-end gap-2">
        <FormDialog
          fields={activeTable.form}
          item={row.original}
          options={options}
          pageKey={page.key}
          tabKey={activeTab}
        >
          <Button variant="ghost" size="icon" className="h-8 w-8">
            <Edit2Icon className="h-4 w-4" />
          </Button>
        </FormDialog>
        <Button
          variant="ghost"
          size="icon"
          className="text-destructive hover:text-destructive h-8 w-8"
          onClick={() => {
            if (confirm('Are you sure you want to delete this item?')) {
              // @ts-ignore
              router.delete(route('plugins.page.destroy', [page.key, activeTab, row.original.id]));
            }
          }}
        >
          <Trash2Icon className="h-4 w-4" />
        </Button>
      </div>
    ),
  });

  const handleTabChange = (val: string) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', val);
    url.searchParams.delete('page');
    url.searchParams.delete('search');
    url.searchParams.delete('sort_by');
    url.searchParams.delete('sort_dir');
    
    // Clear filters of the previous tab
    if (activeTable.filters) {
      activeTable.filters.forEach((f) => {
        url.searchParams.delete(f.key);
      });
    }

    router.get(url.toString(), {}, { preserveState: false });
  };

  const getFilterValue = (key: string) => {
    if (typeof window === 'undefined') return '';
    const params = new URLSearchParams(window.location.search);
    return params.get(key) || '';
  };

  const handleFilterChange = (key: string, value: string) => {
    const url = new URL(window.location.href);
    if (value === '_all') {
      url.searchParams.delete(key);
    } else {
      url.searchParams.set(key, value);
    }
    url.searchParams.delete('page'); // Reset page when filter changes
    router.get(url.toString(), {}, { preserveState: false });
  };

  const pageContent = (
    <Container className="max-w-5xl py-6">
      <Heading
        title={page.title}
        description={page.description || `Manage plugin database records for ${page.title}.`}
      />

      <Tabs defaultValue={activeTab} onValueChange={handleTabChange} className="mt-6 w-full">
        <TabsList className="mb-4">
          {Object.keys(page.tabs).map((tabKey) => (
            <TabsTrigger key={`tab-tr-${tabKey}`} value={tabKey} className="capitalize">
              {tabKey}
            </TabsTrigger>
          ))}
        </TabsList>

        {Object.entries(page.tabs).map(([tabKey, table]) => (
          <TabsContent key={`tab-content-${tabKey}`} value={tabKey}>
            <div className="mb-4 flex justify-between items-center">
              <h2 className="text-lg font-semibold capitalize">{tabKey} List</h2>
              <FormDialog
                fields={table.form}
                options={options}
                pageKey={page.key}
                tabKey={tabKey}
              >
                <Button>Add {tabKey.substring(0, tabKey.length - 1) || 'Item'}</Button>
              </FormDialog>
            </div>

            {/* Dynamic Grid Filters */}
            {tabKey === activeTab && table.filters && table.filters.length > 0 && (
              <div className="mb-6 flex flex-wrap gap-4 items-center rounded-lg border bg-card p-4 text-card-foreground shadow-sm">
                <div className="text-sm font-medium text-muted-foreground mr-2">Filters:</div>
                {table.filters.map((filter) => (
                  <div key={`filter-${filter.key}`} className="flex items-center gap-2">
                    <span className="text-xs font-medium text-muted-foreground capitalize">{filter.label}:</span>
                    {filter.type === 'select' && (
                      <Select
                        value={getFilterValue(filter.key) || '_all'}
                        onValueChange={(val) => handleFilterChange(filter.key, val)}
                      >
                        <SelectTrigger className="w-[180px] h-9">
                          <SelectValue placeholder={`All ${filter.label}s`} />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="_all">All {filter.label}s</SelectItem>
                          {Object.entries(filter.options).map(([val, label]) => (
                            <SelectItem key={`opt-${val}`} value={String(val)}>
                              {String(label)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                  </div>
                ))}
              </div>
            )}

            {activeTab === tabKey && tableData && (
              <DataTable
                columns={columns}
                paginatedData={tableData}
                searchable
                sortable
              />
            )}
          </TabsContent>
        ))}
      </Tabs>
    </Container>
  );

  if (page.admin_only) {
    return (
      <SectionLayout title="Admin" groupKey="admin">
        <Head title={page.title} />
        {pageContent}
      </SectionLayout>
    );
  }

  return (
    <Layout>
      <Head title={page.title} />
      {pageContent}
    </Layout>
  );
}
