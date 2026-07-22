import React, { InputHTMLAttributes, useEffect, useState } from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/password-input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DynamicFieldConfig } from '@/types/dynamic-field-config';
import InputError from '@/components/ui/input-error';
import { FormField } from '@/components/ui/form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { TriangleAlertIcon } from 'lucide-react';

type CustomFieldComponent = React.ComponentType<{
  value: any;
  onValueChange: (value: any) => void;
  id?: string;
  error?: string;
}>;

const registry: Record<string, CustomFieldComponent> = {};

/**
 * Register a custom component to be used inside DynamicField.
 * This allows plugins/features to register their custom inputs (e.g. server provider selects).
 */
export function registerDynamicFieldComponent(name: string, component: CustomFieldComponent) {
  registry[name] = component;
}

interface DynamicFieldProps {
  value: string | number | boolean | string[] | undefined;
  onChange: (value: string | number | boolean | string[]) => void;
  config: DynamicFieldConfig;
  error?: string;
}

export default function DynamicField({ value, onChange, config, error }: DynamicFieldProps) {
  const defaultLabel = config.name.replaceAll('_', ' ');
  const label = config?.label || defaultLabel;
  const [initialValue, setInitialValue] = useState(false);

  useEffect(() => {
    if (initialValue) {
      return;
    }
    if ((config?.type === 'boolean' || config?.type === 'checkbox') && value === undefined) {
      onChange(config.default === undefined ? false : !!config.default);
      setInitialValue(true);
    }
  }, [config, value, onChange, initialValue]);

  if (config?.type === 'select') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Select value={value as string} onValueChange={onChange}>
          <SelectTrigger id={`field-${config.name}`}>
            <SelectValue placeholder={config.placeholder || 'Select option'} />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              {config.options &&
                Object.entries(config.options).map(([key, label]) => (
                  <SelectItem key={`select-option-${key}`} value={key}>
                    {label}
                  </SelectItem>
                ))}
            </SelectGroup>
          </SelectContent>
        </Select>
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  if (config?.type === 'boolean' || config?.type === 'checkbox') {
    return (
      <FormField className="flex items-center space-x-2">
        <Switch id={`field-${config.name}`} checked={!!value} onCheckedChange={onChange} />
        <div className="grid gap-1.5 leading-none">
          <Label htmlFor={`field-${config.name}`} className="capitalize">
            {label}
          </Label>
          {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
          <InputError message={error} />
        </div>
      </FormField>
    );
  }

  if (config?.type === 'textarea') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Textarea
          id={`field-${config.name}`}
          value={(value as string) || ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={config?.placeholder ?? undefined}
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  if (config?.type === 'password') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <PasswordInput
          id={`field-${config.name}`}
          value={(value as string) || ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={config?.placeholder ?? undefined}
          autoComplete="off"
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  if (config?.type === 'alert') {
    return (
      <Alert className="mb-4">
        <TriangleAlertIcon className="h-4 w-4" />
        <AlertTitle>{label}</AlertTitle>
        <AlertDescription>{config.description}</AlertDescription>
      </Alert>
    );
  }

  if (config?.type === 'hidden') {
    return <input type="hidden" name={config.name} value={(value as string) || ''} />;
  }

  if (config?.type === 'file') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Input
          type="file"
          id={`field-${config.name}`}
          onChange={(e) => {
            if (e.target.files && e.target.files.length > 0) {
              onChange(e.target.files[0] as unknown as string);
            }
          }}
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  // Handle custom component fields registered by plugins/features
  if (config?.type === 'component') {
    const Component = registry[config.name];
    if (Component) {
      return (
        <FormField>
          <Label htmlFor={`field-${config.name}`} className="capitalize">
            {label}
          </Label>
          <Component
            value={value}
            onValueChange={onChange}
            id={`field-${config.name}`}
            error={error}
          />
          {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
          <InputError message={error} />
        </FormField>
      );
    }

    // Fallback if component is not registered
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Input
          type="text"
          id={`field-${config.name}`}
          value={(value as string) || ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={config?.placeholder ?? undefined}
        />
        <p className="text-destructive mt-1 text-xs font-semibold">
          Component "{config.name}" is not registered.
        </p>
        <InputError message={error} />
      </FormField>
    );
  }

  if (config?.type === 'number') {
    const step = ((config.componentProps as any)?.step as string | number) || 'any';
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Input
          type="number"
          step={step}
          id={`field-${config.name}`}
          value={value !== undefined && value !== null ? String(value) : ''}
          onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
          placeholder={config?.placeholder ?? undefined}
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  // Default to text input
  const props: InputHTMLAttributes<HTMLInputElement> = {};
  if (config?.placeholder) {
    props.placeholder = config.placeholder ?? undefined;
  }

  return (
    <FormField>
      <Label htmlFor={`field-${config.name}`} className="capitalize">
        {label}
      </Label>
      <Input
        type="text"
        id={`field-${config.name}`}
        value={(value as string) || ''}
        onChange={(e) => onChange(e.target.value)}
        autoComplete="off"
        spellCheck={false}
        {...props}
      />
      {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
      <InputError message={error} />
    </FormField>
  );
}
